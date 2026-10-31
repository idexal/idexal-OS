<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ApiKey;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Explicit model and provider assignment.
 *
 * The platform's default is that a model or provider the operator never
 * assigned to a plan serves every plan — the behaviour every deployment had
 * before a plan could narrow a catalog. The provider console can require an
 * assignment instead, and then nothing reaches a plan until the operator
 * assigns it. The gateway and the desktop read the same switch, so the catalog
 * a member receives and the "your providers" block the desktop shows can never
 * disagree.
 */
class GatewayExplicitAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['locale' => 'en']);
    }

    public function test_an_unassigned_model_serves_every_plan_by_default(): void
    {
        $provider = $this->provider('openai', 'OpenAI');
        $this->model($provider, 'gpt-x', 'GPT X');

        [$token] = $this->member();

        $catalog = $this->withToken($token)->getJson('/v1/inference-providers')->assertOk();

        $this->assertNotEmpty(
            $catalog->json('inferenceProviders.0.models'),
            'Out of the box, a model nobody assigned is offered to every plan.',
        );
    }

    public function test_requiring_an_assignment_removes_every_unassigned_model(): void
    {
        $provider = $this->provider('openai', 'OpenAI');
        $this->model($provider, 'gpt-x', 'GPT X');

        [$token] = $this->member();

        Setting::put('gateway_require_explicit_assignment', '1');

        $catalog = $this->withToken($token)->getJson('/v1/inference-providers')->assertOk();

        $this->assertSame([], $catalog->json('inferenceProviders'), 'Nothing is assigned, so nothing is served.');

        $config = $this->withToken($token)->getJson('/v1/me/desktop-config')->assertOk();

        $this->assertTrue($config->json('providerEntitlements.enabled'), 'The gateway is still on; only the assignment is missing.');
        $this->assertSame([], $config->json('providerEntitlements.providers'), 'The desktop block agrees with the gateway.');
    }

    public function test_an_assigned_model_reaches_only_the_plan_it_is_assigned_to(): void
    {
        $provider = $this->provider('openai', 'OpenAI');
        $model = $this->model($provider, 'gpt-x', 'GPT X');

        [$token, $organization] = $this->member();
        Setting::put('gateway_require_explicit_assignment', '1');

        $memberPlan = $organization->currentPlan();
        $otherPlan = Plan::factory()->create(['slug' => 'other', 'name' => 'Other', 'is_active' => true, 'is_public' => true]);

        // Both levels are required in strict mode: the provider as well as the
        // model. Assigning only a model leaves the provider gate closed.
        $provider->plans()->sync([$memberPlan->getKey()]);
        $model->plans()->sync([$otherPlan->getKey()]);

        $this->assertSame(
            [],
            $this->withToken($token)->getJson('/v1/inference-providers')->json('inferenceProviders'),
            'A model assigned to another plan does not leak into this one.',
        );

        $model->plans()->sync([$memberPlan->getKey()]);

        $catalog = $this->withToken($token)->getJson('/v1/inference-providers')->assertOk();

        $this->assertNotEmpty(
            $catalog->json('inferenceProviders.0.models'),
            'Assigned to the member\'s own plan, the model is offered.',
        );
    }

    public function test_a_provider_without_an_assignment_reaches_no_plan_in_strict_mode(): void
    {
        $assigned = $this->provider('assigned', 'Assigned');
        $unassigned = $this->provider('unassigned', 'Unassigned');
        $this->model($assigned, 'a-model', 'A model');
        $this->model($unassigned, 'b-model', 'B model');

        [$token, $organization] = $this->member();
        Setting::put('gateway_require_explicit_assignment', '1');

        $assigned->plans()->sync([$organization->currentPlan()->getKey()]);

        $config = $this->withToken($token)->getJson('/v1/me/desktop-config')->assertOk();
        $slugs = array_column($config->json('providerEntitlements.providers'), 'slug');

        $this->assertSame(['assigned'], $slugs, 'Only the assigned provider is offered.');
    }

    public function test_a_platform_wide_cancellation_reaches_the_desktop_block_too(): void
    {
        [$token] = $this->member([Plan::FEATURE_CUSTOM_PROVIDERS => true]);
        $provider = $this->provider('openai', 'OpenAI');
        $this->model($provider, 'gpt-x', 'GPT X');

        Setting::put('byok_enabled', '1');
        $before = $this->withToken($token)->getJson('/v1/me/desktop-config')->assertOk();
        $this->assertTrue($before->json('providerEntitlements.byok'), 'A plan that grants own-keys allows them once the operator opens it.');

        Setting::put('byok_enabled', '0');

        $after = $this->withToken($token)->getJson('/v1/me/desktop-config')->assertOk();

        $this->assertFalse(
            $after->json('providerEntitlements.byok'),
            'The block the desktop reads must agree with the API once the operator cancels BYOK.',
        );
        $this->assertFalse($after->json('allowCustomProviders'));
    }

    public function test_the_operator_can_require_an_assignment_from_the_console(): void
    {
        $this->assertNotSame('1', Setting::get('gateway_require_explicit_assignment'));

        $this->actingAs($this->admin)->get('/admin/providers')
            ->assertOk()
            ->assertSeeText(__('ui.admin.providers.assignment_title'));

        $this->actingAs($this->admin)
            ->post('/admin/providers/assignment', ['explicit_assignment_required' => '1'])
            ->assertRedirect();

        $this->assertSame('1', Setting::get('gateway_require_explicit_assignment'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.provider.assignment_policy']);

        $this->actingAs($this->admin)
            ->post('/admin/providers/assignment', ['explicit_assignment_required' => '0'])
            ->assertRedirect();

        $this->assertSame('0', Setting::get('gateway_require_explicit_assignment'));
    }

    public function test_a_member_cannot_change_the_assignment_policy(): void
    {
        [$token] = $this->member();
        $member = User::query()->where('email', 'member@example.test')->sole();

        // A member's API token is not a web session: it is sent to the sign-in
        // page rather than allowed through.
        $this->withToken($token)->post('/admin/providers/assignment', ['explicit_assignment_required' => '1'])
            ->assertRedirect(route('login'));

        $this->actingAs($member)->post('/admin/providers/assignment', ['explicit_assignment_required' => '1'])
            ->assertForbidden();

        $this->assertNotSame('1', Setting::get('gateway_require_explicit_assignment'));
    }

    private function provider(string $slug, string $name): AiProvider
    {
        return AiProvider::factory()->create([
            'slug' => $slug,
            'name' => $name,
            'driver' => AiProvider::DRIVER_OPENAI,
            'api_key' => 'sk-test',
            'is_active' => true,
        ]);
    }

    private function model(AiProvider $provider, string $modelId, string $name): AiModel
    {
        return AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => $modelId,
            'display_name' => $name,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $planFeatures
     * @return array{0: string, 1: Organization}
     */
    private function member(array $planFeatures = []): array
    {
        $user = User::factory()->create(['email' => 'member@example.test', 'locale' => 'en']);
        $organization = Organization::factory()->create(['owner_id' => $user->getKey()]);
        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        $plan = Plan::factory()->create([
            'slug' => 'free',
            'name' => 'Free',
            'features' => $planFeatures ?: [Plan::FEATURE_CUSTOM_PROVIDERS => true],
        ]);
        Subscription::factory()->create([
            'organization_id' => $organization->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        [, $token] = ApiKey::issue($organization, $user, 'Member key');

        return [$token, $organization];
    }
}
