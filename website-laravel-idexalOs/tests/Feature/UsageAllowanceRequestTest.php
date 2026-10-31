<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Membership;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageCounter;
use App\Models\UsageLimitResetRequest;
use App\Models\User;
use App\Services\UsageAllowanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The spend-increase loop: a plan grants a monthly cost allowance, the member
 * sees it as a usage bucket, and when it is used up they ask an operator, who
 * approves an extension that the bucket then includes.
 *
 * This is what makes the desktop's "ask for more" button reachable on this
 * platform: it keys on a bucket, and until now this platform returned none.
 */
class UsageAllowanceRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private Organization $organization;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['locale' => 'en']);
        $this->organization = Organization::factory()->create(['owner_id' => $this->owner->getKey()]);
        Membership::query()->create([
            'organization_id' => $this->organization->getKey(),
            'user_id' => $this->owner->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);
        [, $this->token] = ApiKey::issue($this->organization, $this->owner, 'Usage key');

        $this->admin = User::factory()->admin()->create(['locale' => 'en']);
    }

    public function test_a_plan_without_an_allowance_sends_no_bucket(): void
    {
        $this->plan(null);

        $this->withToken($this->token)->getJson('/v1/gateway/usage-limits/me')
            ->assertOk()
            ->assertJsonPath('buckets', []);
    }

    public function test_a_plan_with_an_allowance_sends_a_real_bucket(): void
    {
        $this->plan(50.0);
        $this->spend(12.5);

        $bucket = $this->bucket();

        $this->assertSame('month', $bucket['timeframe']);
        $this->assertSame(50_000_000, $bucket['baseAllowanceMicroUsd']);
        $this->assertSame(0, $bucket['extensionMicroUsd']);
        $this->assertSame(12_500_000, $bucket['usedMicroUsd']);
        $this->assertFalse($bucket['canRequestReset'], 'There is nothing to raise while the allowance is intact.');
        $this->assertNull($bucket['resetRequestStatus']);
        // A soft budget: the hard stop on this platform stays the plan's
        // request and token limits, and the bucket says so rather than claiming
        // to block.
        $this->assertFalse($bucket['hardLimit']);
    }

    public function test_a_member_can_ask_when_the_allowance_is_used_up(): void
    {
        $this->plan(10.0);
        $this->spend(10.0);

        $this->assertTrue($this->bucket()['canRequestReset']);

        $this->withToken($this->token)->postJson('/v1/gateway/usage-limit-reset-requests', [
            'bucketId' => $this->bucketId(),
            'reason' => 'Shipping a release this week.',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('bucketId', $this->bucketId())
            ->assertJsonPath('reason', 'Shipping a release this week.');

        $this->assertSame(1, UsageLimitResetRequest::query()->count());
        // The operator is told, once.
        $this->assertSame(1, Notification::query()->where('type', 'usage_limit_request')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'member.usage_limit_request.created']);

        // The desktop now reads "waiting" instead of offering the ask again.
        $this->assertSame('pending', $this->bucket()['resetRequestStatus']);
        $this->assertFalse($this->bucket()['canRequestReset']);
    }

    public function test_asking_twice_keeps_one_open_request(): void
    {
        $this->plan(10.0);
        $this->spend(10.0);

        $body = ['bucketId' => $this->bucketId(), 'reason' => 'Need more.'];

        $this->withToken($this->token)->postJson('/v1/gateway/usage-limit-reset-requests', $body)->assertOk();
        $this->withToken($this->token)->postJson('/v1/gateway/usage-limit-reset-requests', $body)->assertOk();

        $this->assertSame(1, UsageLimitResetRequest::query()->count());
        $this->assertSame(1, Notification::query()->where('type', 'usage_limit_request')->count());
    }

    public function test_an_an_operator_can_approve_and_the_bucket_grows(): void
    {
        $this->plan(10.0);
        $this->spend(10.0);
        $ask = $this->ask();

        $this->actingAs($this->admin)
            ->post('/admin/gateway/usage-requests/'.$ask->getKey().'/approve', ['extension_usd' => 25])
            ->assertRedirect();

        $ask->refresh();
        $this->assertSame(UsageLimitResetRequest::STATUS_APPROVED, $ask->status);
        $this->assertSame(25_000_000, $ask->extension_micro_usd);

        $bucket = $this->bucket();
        $this->assertSame(25_000_000, $bucket['extensionMicroUsd']);
        $this->assertSame(35_000_000, $bucket['allowanceMicroUsd']);
        $this->assertSame(10_000_000, $bucket['usedMicroUsd']);
        $this->assertFalse($bucket['canRequestReset'], 'The raised allowance is no longer exhausted.');
        $this->assertSame('approved', $bucket['resetRequestStatus']);

        // The member is told.
        $this->assertSame(1, Notification::query()->where('type', 'usage_limit_request_approved')->count());
    }

    public function test_denying_closes_the_ask_and_tells_the_member(): void
    {
        $this->plan(10.0);
        $this->spend(10.0);
        $ask = $this->ask();

        $this->actingAs($this->admin)
            ->post('/admin/gateway/usage-requests/'.$ask->getKey().'/deny')
            ->assertRedirect();

        $this->assertSame(UsageLimitResetRequest::STATUS_DENIED, $ask->fresh()->status);
        $this->assertSame(0, $this->bucket()['extensionMicroUsd'], 'A denial grants nothing.');
        $this->assertSame('denied', $this->bucket()['resetRequestStatus']);
        $this->assertSame(1, Notification::query()->where('type', 'usage_limit_request_denied')->count());
    }

    public function test_the_queue_shows_the_ask_with_the_numbers(): void
    {
        $this->plan(10.0);
        $this->spend(10.0);
        $this->ask();

        $this->actingAs($this->admin)->get('/admin/gateway')
            ->assertOk()
            ->assertSeeText(__('ui.admin.gateway.usage_requests.title'))
            ->assertSeeText($this->owner->name)
            ->assertSeeText('Shipping a release this week.');
    }

    public function test_an_ask_for_a_bucket_the_plan_does_not_grant_is_refused(): void
    {
        $this->plan(50.0);

        $this->withToken($this->token)->postJson('/v1/gateway/usage-limit-reset-requests', [
            'bucketId' => 'something-else',
            'reason' => 'Please.',
        ])->assertNotFound();

        $this->assertSame(0, UsageLimitResetRequest::query()->count());
    }

    public function test_an_ask_while_the_allowance_remains_is_refused(): void
    {
        $this->plan(50.0);
        $this->spend(1.0);

        $this->withToken($this->token)->postJson('/v1/gateway/usage-limit-reset-requests', [
            'bucketId' => $this->bucketId(),
            'reason' => 'Just in case.',
        ])->assertStatus(409);

        $this->assertSame(0, UsageLimitResetRequest::query()->count());
    }

    public function test_a_member_cannot_reach_the_operator_queue(): void
    {
        $this->plan(10.0);
        $this->spend(10.0);
        $ask = $this->ask();

        $this->actingAs($this->owner)
            ->post('/admin/gateway/usage-requests/'.$ask->getKey().'/approve', ['extension_usd' => 999])
            ->assertStatus(403);

        $this->assertSame(UsageLimitResetRequest::STATUS_PENDING, $ask->fresh()->status);
    }

    private function plan(?float $monthlyCostUsd): Plan
    {
        $limits = [
            Plan::LIMIT_REQUESTS => 100000,
            Plan::LIMIT_TOKENS => 1000000,
        ];

        if ($monthlyCostUsd !== null) {
            $limits[Plan::LIMIT_MONTHLY_COST_USD] = $monthlyCostUsd;
        }

        $plan = Plan::factory()->create(['slug' => 'free', 'name' => 'Free', 'limits' => $limits]);

        Subscription::factory()->create([
            'organization_id' => $this->organization->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        return $plan;
    }

    private function spend(float $usd): void
    {
        $counter = UsageCounter::forOrganization($this->organization, UsageCounter::currentPeriod());
        $counter->forceFill(['requests' => 10, 'total_tokens' => 100, 'cost' => $usd])->save();
    }

    private function bucketId(): string
    {
        return UsageAllowanceService::bucketId($this->organization);
    }

    /**
     * @return array<string, mixed>
     */
    private function bucket(): array
    {
        $response = $this->withToken($this->token)->getJson('/v1/gateway/usage-limits/me')->assertOk();

        $buckets = $response->json('buckets');
        $this->assertNotEmpty($buckets, 'A plan with an allowance always sends its bucket.');

        return $buckets[0];
    }

    private function ask(): UsageLimitResetRequest
    {
        return UsageLimitResetRequest::ask(
            $this->organization->getKey(),
            $this->bucketId(),
            UsageCounter::currentPeriod(),
            (string) $this->owner->getKey(),
            'Shipping a release this week.',
        );
    }
}
