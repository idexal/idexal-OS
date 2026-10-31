<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\ConnectorGrant;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\DesktopSwitch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Connector permission scopes: an operator approves what each installed
 * connector may read, write, reach and invoke, and the desktop receives that
 * answer at `/v1/me/desktop-config`. The invariant the whole feature exists for
 * is that a revocation is the next revision of the same grant — never a silent
 * rename and never something an older permission set can undo.
 */
class ConnectorPermissionScopesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $organization;

    private string $token;

    /** A catalogue connector that declares scopes. */
    private const SLUG = 'github-mcp';

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['locale' => 'en']);
        $this->organization = $this->workspace($this->admin);
        [, $this->token] = ApiKey::issue($this->organization, $this->admin, 'Connector scopes key');
    }

    public function test_the_feature_ships_off_and_delivers_nothing(): void
    {
        $features = $this->getJson('/v1/features')->assertOk()->json('features');
        $this->assertFalse($features['connectorPermissionScopes'], 'Published off.');

        $config = $this->withToken($this->token)->getJson('/v1/me/desktop-config')->assertOk();
        $this->assertArrayNotHasKey('connectorPermissions', $config->json(), 'No block while off.');

        $presets = $this->withToken($this->token)->getJson('/v1/mcp-connections/presets')->assertOk()->json('presets');
        $this->assertNotEmpty($presets);

        foreach ($presets as $preset) {
            $this->assertArrayNotHasKey('permissions', $preset, 'Requested scopes stay hidden while off.');
        }
    }

    public function test_switching_the_feature_on_publishes_an_empty_permission_set(): void
    {
        $this->turnOn();

        $this->assertTrue($this->getJson('/v1/features')->json('features.connectorPermissionScopes'));

        $permissions = $this->config()['connectorPermissions'];

        $this->assertSame(0, $permissions['revision']);
        $this->assertSame([], $permissions['grants']);
        $this->assertSame(['github-mcp', 'notion-mcp', 'vercel-mcp'], $this->sorted($permissions['scoped']));
        $this->assertSame(0, ConnectorGrant::query()->count());
    }

    public function test_turning_the_feature_on_surfaces_the_requested_scopes_to_the_desktop(): void
    {
        $this->turnOn();

        $presets = collect($this->withToken($this->token)->getJson('/v1/mcp-connections/presets')->json('presets'))
            ->keyBy('presetId');

        $this->assertSame(
            ['net:egress:api.githubcopilot.com', 'tools:invoke'],
            $presets['preset_'.self::SLUG]['permissions'],
        );
    }

    public function test_approving_scopes_reaches_the_desktop_at_a_new_revision(): void
    {
        $this->turnOn();

        $this->grant(["net:egress:api.githubcopilot.com\n", 'tools:invoke']);

        $grant = ConnectorGrant::query()->sole();
        $this->assertSame(ConnectorGrant::STATUS_ACTIVE, $grant->status);
        $this->assertSame(1, $grant->revision);
        $this->assertSame(['net:egress:api.githubcopilot.com', 'tools:invoke'], $grant->scopes);

        $permissions = $this->config()['connectorPermissions'];

        $this->assertSame(1, $permissions['revision']);
        $this->assertSame([
            [
                'slug' => self::SLUG,
                'revision' => 1,
                'status' => 'active',
                'scopes' => ['net:egress:api.githubcopilot.com', 'tools:invoke'],
                'expiresAt' => null,
            ],
        ], $permissions['grants']);

        $entry = AuditLog::query()->where('action', 'admin.connector_grant.granted')->sole();
        $this->assertSame($this->organization->getKey(), $entry->organization_id);
        $this->assertSame(
            ['from' => 'Not approved', 'to' => 'Approved · 2 permissions'],
            $entry->meta['connector_grants'][self::SLUG],
        );
        $this->assertArrayHasKey('connector_grants', $entry->changeSections());
    }

    public function test_an_operator_can_only_approve_what_the_connector_asked_for(): void
    {
        $this->turnOn();

        $this->grant([
            'tools:invoke',
            'fs:write:/etc',
            'net:egress:evil.example',
            'net:egress:api.githubcopilot.com',
        ]);

        // Unknown scopes are dropped, so a scope the catalogue never declared
        // can never be approved by editing the form.
        $this->assertSame(
            ['net:egress:api.githubcopilot.com', 'tools:invoke'],
            ConnectorGrant::query()->sole()->scopes,
        );
    }

    public function test_revoking_keeps_the_row_and_writes_the_reason_at_a_new_revision(): void
    {
        $this->turnOn();
        $this->grant(['net:egress:api.githubcopilot.com', 'tools:invoke']);

        $this->revoke('Rotated the token; access is no longer needed.');

        // The row survives: a revocation is a state, not a deletion.
        $grant = ConnectorGrant::query()->sole();
        $this->assertSame(ConnectorGrant::STATUS_REVOKED, $grant->status);
        $this->assertSame('Rotated the token; access is no longer needed.', $grant->reason);
        $this->assertSame($this->admin->getKey(), $grant->revoked_by);
        $this->assertSame(2, $grant->revision);

        $permissions = $this->config()['connectorPermissions'];

        $this->assertSame(2, $permissions['revision']);
        $this->assertSame('revoked', $permissions['grants'][0]['status']);
        $this->assertSame(
            ['from' => 'Approved · 2 permissions', 'to' => 'Revoked · Rotated the token; access is no longer needed.'],
            AuditLog::query()->where('action', 'admin.connector_grant.revoked')->sole()->meta['connector_grants'][self::SLUG],
        );
    }

    public function test_a_stale_permission_set_can_never_restore_a_revoked_grant(): void
    {
        $this->turnOn();
        $this->grant(['net:egress:api.githubcopilot.com', 'tools:invoke']);

        // What a desktop had before the revocation, kept as delivered.
        $stale = $this->config()['connectorPermissions'];
        $this->assertSame(1, $stale['revision']);
        $this->assertSame('active', $stale['grants'][0]['status']);

        $this->revoke('Ended on purpose.');

        // A revocation never reuses a revision, and every later approval is
        // newer still, so a desktop that reconnects holding the old set sees a
        // revision it must ignore rather than one it can apply.
        $current = $this->config()['connectorPermissions'];
        $this->assertSame(2, $current['revision']);
        $this->assertGreaterThan($stale['revision'], $current['revision']);
        $this->assertSame('revoked', $current['grants'][0]['status']);

        $this->grant(['net:egress:api.githubcopilot.com', 'tools:invoke']);

        $regranted = $this->config()['connectorPermissions'];
        $this->assertSame(3, $regranted['revision']);
        $this->assertGreaterThan($current['revision'], $regranted['revision']);
        $this->assertSame('active', $regranted['grants'][0]['status']);

        // One row, three revisions: the grant is revised, never aliased.
        $this->assertSame(1, ConnectorGrant::query()->count());
        $this->assertSame(3, ConnectorGrant::query()->sole()->revision);
    }

    public function test_a_save_that_changes_nothing_writes_no_audit_entry(): void
    {
        $this->turnOn();
        $this->grant(['net:egress:api.githubcopilot.com', 'tools:invoke']);

        AuditLog::query()->delete();

        $this->grant(['net:egress:api.githubcopilot.com', 'tools:invoke']);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_the_screen_lists_every_connector_that_asks_for_scopes(): void
    {
        $this->turnOn();

        $page = $this->actingAs($this->admin)
            ->get('/admin/connectors?organization='.$this->organization->getKey())
            ->assertOk();

        $page->assertSee(__('ui.admin.connectors.title'));
        $page->assertSee('GitHub');
        $page->assertSee(__('ui.admin.connectors.scope_tools_invoke'));
        $page->assertSee('net:egress:api.githubcopilot.com', false);
        $page->assertSee('scopes['.self::SLUG.'][]', false);
    }

    public function test_a_member_who_is_not_an_admin_cannot_change_connector_permissions(): void
    {
        $member = User::factory()->create();
        $this->workspace($member, $this->organization);

        $this->actingAs($member)->get('/admin/connectors')->assertForbidden();
        $this->actingAs($member)->patch('/admin/connectors', [
            'organization' => $this->organization->getKey(),
            'slug' => self::SLUG,
            'action' => 'grant',
        ])->assertForbidden();

        $this->assertSame(0, ConnectorGrant::query()->count());
    }

    public function test_one_workspace_grant_never_reaches_another(): void
    {
        $this->turnOn();
        $this->grant(['tools:invoke']);

        $otherUser = User::factory()->create();
        $other = $this->workspace($otherUser);
        [, $otherToken] = ApiKey::issue($other, $otherUser, 'Other workspace key');

        $this->assertSame([], $this->withToken($otherToken)->getJson('/v1/me/desktop-config')->json('connectorPermissions.grants'));
        $this->assertNotEmpty($this->config()['connectorPermissions']['grants']);
    }

    public function test_the_screen_reads_in_arabic(): void
    {
        $this->turnOn();
        $this->grant(['tools:invoke']);

        $this->admin->forceFill(['locale' => 'ar'])->save();

        $page = $this->actingAs($this->admin)
            ->get('/admin/connectors?organization='.$this->organization->getKey())
            ->assertOk();

        $page->assertSee(__('ui.admin.connectors.title', [], 'ar'));
        $page->assertSee(__('ui.admin.nav.connectors', [], 'ar'));
        $page->assertSee(__('ui.admin.connectors.status_active', [], 'ar'));
        $page->assertDontSee('Approved');
    }

    /**
     * Turn the desktop switch on the way the Features panel does.
     */
    private function turnOn(): void
    {
        $this->actingAs($this->admin)
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
        $this->actingAs($this->admin)->patch('/admin/connectors', [
            'organization' => $this->organization->getKey(),
            'slug' => self::SLUG,
            'action' => 'grant',
            'scopes' => [self::SLUG => $scopes],
        ])->assertSessionHasNoErrors();
    }

    private function revoke(string $reason): void
    {
        $this->actingAs($this->admin)->patch('/admin/connectors', [
            'organization' => $this->organization->getKey(),
            'slug' => self::SLUG,
            'action' => 'revoke',
            'reason' => $reason,
        ])->assertSessionHasNoErrors();
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return $this->withToken($this->token)->getJson('/v1/me/desktop-config')->assertOk()->json();
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return array_values($values);
    }

    private function workspace(User $user, ?Organization $organization = null): Organization
    {
        $organization ??= Organization::factory()->create(['owner_id' => $user->getKey()]);

        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        return $organization;
    }
}
