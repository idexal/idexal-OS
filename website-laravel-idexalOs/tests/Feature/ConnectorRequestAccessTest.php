<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\ConnectorGrantRequest;
use App\Models\Membership;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\DesktopSwitch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Asking an operator for connector access: a member who needs a connector has
 * a way to say so, the ask lands in the operator's queue, and approving the
 * connector answers everyone who asked for it. The ask is not a grant — an
 * operator still answers on the connectors screen — so nothing here can widen
 * access on its own.
 */
class ConnectorRequestAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Organization $organization;

    private string $memberToken;

    private const SLUG = 'github-mcp';

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->admin()->create(['locale' => 'en']);
        $this->organization = $this->workspace($this->owner, null, Membership::ROLE_OWNER);

        $this->member = User::factory()->create(['locale' => 'en', 'name' => 'Sam Member']);
        $this->workspace($this->member, $this->organization, Membership::ROLE_MEMBER);

        [, $this->memberToken] = ApiKey::issue($this->organization, $this->member, 'Member key');
    }

    public function test_the_feature_ships_off_and_the_endpoint_does_not_exist(): void
    {
        $this->postJson('/v1/me/connector-requests', ['slug' => self::SLUG], [
            'Authorization' => 'Bearer '.$this->memberToken,
        ])->assertNotFound();

        $config = $this->config();

        $this->assertArrayNotHasKey('connectorRequests', $config, 'No block while off.');
        $this->assertSame(0, ConnectorGrantRequest::query()->count());
    }

    public function test_a_member_can_ask_and_the_ask_reaches_the_desktop(): void
    {
        $this->turnOn();

        $this->ask()->assertOk()->assertJsonPath('request.status', 'open');

        $ask = ConnectorGrantRequest::query()->sole();
        $this->assertSame(self::SLUG, $ask->connector_slug);
        $this->assertSame($this->member->getKey(), $ask->user_id);
        // The scopes are the catalogue's, so the ask cannot be widened by the
        // caller and the operator approves exactly what the connector declares.
        $this->assertSame(['net:egress:api.githubcopilot.com', 'tools:invoke'], $ask->scopes);

        $delivered = $this->config()['connectorRequests'];
        $this->assertCount(1, $delivered);
        $this->assertSame(self::SLUG, $delivered[0]['slug']);
        $this->assertSame('open', $delivered[0]['status']);

        // An operator is told, and the ask is on the record.
        $this->assertSame(1, Notification::query()->where('type', 'connector_request')->where('user_id', $this->owner->getKey())->count());
        $entry = AuditLog::query()->where('action', 'member.connector_request.created')->sole();
        $this->assertSame($this->organization->getKey(), $entry->organization_id);
        $this->assertSame(
            ['from' => 'Not approved', 'to' => 'Approval requested'],
            $entry->meta['connector_grants'][self::SLUG],
        );
    }

    public function test_asking_again_does_not_queue_a_second_ask(): void
    {
        $this->turnOn();

        $this->ask()->assertOk();
        $this->ask()->assertOk();

        $this->assertSame(1, ConnectorGrantRequest::query()->count());
        // One request is one notification, however often the member clicks.
        $this->assertSame(1, Notification::query()->where('type', 'connector_request')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'member.connector_request.created')->count());
    }

    public function test_a_member_cannot_ask_for_a_connector_that_is_already_approved(): void
    {
        $this->turnOn();
        $this->grant(['tools:invoke']);

        $this->ask()->assertStatus(409)->assertJsonPath('error.code', 'already_approved');

        $this->assertSame(0, ConnectorGrantRequest::query()->count());
    }

    public function test_an_unknown_connector_is_not_a_request(): void
    {
        $this->turnOn();

        $this->postJson('/v1/me/connector-requests', ['slug' => 'no-such-connector'], [
            'Authorization' => 'Bearer '.$this->memberToken,
        ])->assertNotFound();

        $this->assertSame(0, ConnectorGrantRequest::query()->count());
    }

    public function test_an_operator_sees_the_request_and_can_dismiss_it(): void
    {
        $this->turnOn();
        $this->ask()->assertOk();

        $page = $this->actingAs($this->owner)
            ->get('/admin/connectors?organization='.$this->organization->getKey())
            ->assertOk();
        $page->assertSee(__('ui.admin.connectors.requests_title'));
        $page->assertSee('Sam Member');
        $page->assertSee('GitHub');

        $ask = ConnectorGrantRequest::query()->sole();

        $this->actingAs($this->owner)->patch('/admin/connectors/requests', [
            'organization' => $this->organization->getKey(),
            'request' => $ask->getKey(),
        ])->assertSessionHasNoErrors();

        $ask->refresh();
        $this->assertSame(ConnectorGrantRequest::STATUS_DISMISSED, $ask->status);
        $this->assertSame($this->owner->getKey(), $ask->resolved_by);

        $entry = AuditLog::query()->where('action', 'admin.connector_request.dismissed')->sole();
        $this->assertSame(
            ['from' => 'Approval requested', 'to' => 'Request dismissed'],
            $entry->meta['connector_grants'][self::SLUG],
        );

        // A dismissal closes the ask for the member too: the desktop stops
        // showing "approval requested" once an operator has answered.
        $this->assertSame([], $this->config()['connectorRequests']);
    }

    public function test_approving_the_connector_resolves_every_open_ask(): void
    {
        $this->turnOn();
        $this->ask()->assertOk();

        $this->grant(['net:egress:api.githubcopilot.com', 'tools:invoke']);

        $ask = ConnectorGrantRequest::query()->sole();
        $this->assertSame(ConnectorGrantRequest::STATUS_APPROVED, $ask->status);
        $this->assertSame($this->owner->getKey(), $ask->resolved_by);

        $this->assertSame([], $this->config()['connectorRequests']);
        $this->assertSame('active', $this->config()['connectorPermissions']['grants'][0]['status']);
    }

    public function test_a_request_never_reaches_another_workspace(): void
    {
        $this->turnOn();
        $this->ask()->assertOk();

        $other = User::factory()->create(['locale' => 'en']);
        $otherOrganization = $this->workspace($other, null, Membership::ROLE_OWNER);
        [, $otherToken] = ApiKey::issue($otherOrganization, $other, 'Other key');

        $config = $this->withToken($otherToken)->getJson('/v1/me/desktop-config')->assertOk()->json();

        $this->assertSame([], $config['connectorRequests']);
    }

    public function test_answering_a_request_tells_the_member_who_asked(): void
    {
        $this->turnOn();
        $this->ask()->assertOk();

        // Dismissing tells them, so they are not left guessing.
        $ask = ConnectorGrantRequest::query()->sole();
        $this->actingAs($this->owner)->patch('/admin/connectors/requests', [
            'organization' => $this->organization->getKey(),
            'request' => $ask->getKey(),
        ])->assertSessionHasNoErrors();

        $declined = Notification::query()
            ->where('type', 'connector_request.declined')
            ->where('user_id', $this->member->getKey())
            ->sole();
        $this->assertSame($this->organization->getKey(), $declined->organization_id);
        $this->assertStringContainsString('GitHub', (string) $declined->title);

        // Approving tells them too — the answer to their ask, wherever they
        // happen to be.
        $this->ask()->assertOk();
        $this->grant(['net:egress:api.githubcopilot.com', 'tools:invoke']);

        $this->assertSame(1, Notification::query()
            ->where('type', 'connector_request.approved')
            ->where('user_id', $this->member->getKey())
            ->count());
    }

    public function test_approving_tells_every_member_who_asked_once(): void
    {
        $this->turnOn();

        $other = User::factory()->create(['locale' => 'en', 'name' => 'Ada Member']);
        $this->workspace($other, $this->organization, Membership::ROLE_MEMBER);
        [, $otherToken] = ApiKey::issue($this->organization, $other, 'Ada key');

        // One member asks twice; the other asks once. One answer each.
        $this->ask()->assertOk();
        $this->ask()->assertOk();
        $this->postJson('/v1/me/connector-requests', ['slug' => self::SLUG], [
            'Authorization' => 'Bearer '.$otherToken,
        ])->assertOk();

        $this->grant(['net:egress:api.githubcopilot.com', 'tools:invoke']);

        $this->assertSame(1, Notification::query()
            ->where('type', 'connector_request.approved')
            ->where('user_id', $this->member->getKey())
            ->count());
        $this->assertSame(1, Notification::query()
            ->where('type', 'connector_request.approved')
            ->where('user_id', $other->getKey())
            ->count());
    }

    public function test_a_revocation_is_not_a_request_answered(): void
    {
        $this->turnOn();
        $this->grant(['tools:invoke']);

        $this->actingAs($this->owner)->patch('/admin/connectors', [
            'organization' => $this->organization->getKey(),
            'slug' => self::SLUG,
            'action' => 'revoke',
            'reason' => 'No longer needed',
            'scopes' => [self::SLUG => []],
        ])->assertSessionHasNoErrors();

        // Access taken away is not an answer to anybody's question.
        $this->assertSame(0, Notification::query()
            ->whereIn('type', ['connector_request.approved', 'connector_request.declined'])
            ->count());
    }

    private function turnOn(): void
    {
        $this->actingAs($this->owner)
            ->patch('/admin/features', [
                DesktopSwitch::ConnectorPermissionScopes->fieldKey() => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(app(SettingsService::class)->boolean(DesktopSwitch::ConnectorPermissionScopes->settingKey()));
    }

    /**
     * @param  list<string>  $scopes
     */
    private function grant(array $scopes): void
    {
        $this->actingAs($this->owner)->patch('/admin/connectors', [
            'organization' => $this->organization->getKey(),
            'slug' => self::SLUG,
            'action' => 'grant',
            'scopes' => [self::SLUG => $scopes],
        ])->assertSessionHasNoErrors();
    }

    private function ask(): TestResponse
    {
        return $this->postJson('/v1/me/connector-requests', ['slug' => self::SLUG], [
            'Authorization' => 'Bearer '.$this->memberToken,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return $this->withToken($this->memberToken)->getJson('/v1/me/desktop-config')->assertOk()->json();
    }

    private function workspace(User $user, ?Organization $organization, string $role): Organization
    {
        $organization ??= Organization::factory()->create(['owner_id' => $user->getKey()]);

        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        return $organization;
    }
}
