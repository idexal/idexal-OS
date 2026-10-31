<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\ConnectorGrant;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\ConnectorGrantPolicy;
use App\Support\DesktopSwitch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Approvals that expire: an operator may approve a connector for a fixed
 * window, and when it passes the connector stops — on the desktop, in the
 * delivery, and in the audit log, without an operator having to remember.
 */
class ConnectorGrantExpiryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $organization;

    private string $token;

    private const SLUG = 'github-mcp';

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['locale' => 'en']);
        $this->organization = $this->workspace($this->admin);
        [, $this->token] = ApiKey::issue($this->organization, $this->admin, 'Expiry key');

        $this->turnOn();
    }

    public function test_an_approval_without_a_duration_never_expires(): void
    {
        $this->grant(['tools:invoke']);

        $grant = ConnectorGrant::query()->sole();
        $this->assertNull($grant->lease_expires_at);
        $this->assertFalse($grant->isExpired());

        $delivered = $this->config()['connectorPermissions']['grants'][0];
        $this->assertSame('active', $delivered['status']);
        $this->assertNull($delivered['expiresAt']);
    }

    public function test_approving_with_a_duration_delivers_an_expiry(): void
    {
        $this->grant(['tools:invoke'], '30');

        $grant = ConnectorGrant::query()->sole();
        $this->assertNotNull($grant->lease_expires_at);
        $this->assertTrue($grant->lease_expires_at->isFuture());
        $this->assertFalse($grant->isExpired());

        $delivered = $this->config()['connectorPermissions']['grants'][0];
        $this->assertSame('active', $delivered['status']);
        $this->assertNotNull($delivered['expiresAt']);
        $this->assertSame($grant->lease_expires_at->toIso8601String(), $delivered['expiresAt']);
    }

    public function test_an_approval_whose_lease_passed_is_delivered_as_expired_before_the_sweep(): void
    {
        $this->grant(['tools:invoke'], '7');

        $this->travel(8)->days();

        // No command has run, but the delivery already reports it expired: an
        // approval cannot outlive its deadline because the platform has not
        // swept the row yet.
        $delivered = $this->config()['connectorPermissions']['grants'][0];
        $this->assertSame('expired', $delivered['status']);

        // The stored row is untouched until the sweep runs.
        $this->assertSame(ConnectorGrant::STATUS_ACTIVE, ConnectorGrant::query()->sole()->status);
    }

    public function test_the_sweep_flips_the_row_bumps_the_revision_and_writes_an_audit_line(): void
    {
        $this->grant(['tools:invoke'], '7');

        $this->travel(8)->days();

        $this->artisan('connectors:expire-grants')->assertSuccessful();

        $grant = ConnectorGrant::query()->sole();
        $this->assertSame(ConnectorGrant::STATUS_EXPIRED, $grant->status);
        $this->assertSame(2, $grant->revision);

        $entry = AuditLog::query()->where('action', 'admin.connector_grant.expired')->sole();
        $this->assertSame($this->organization->getKey(), $entry->organization_id);
        $change = $entry->meta['connector_grants'][self::SLUG];
        $this->assertStringStartsWith('Approved · 1 permissions · expires ', $change['from']);
        $this->assertStringStartsWith('Approval expired · ', $change['to']);
        $this->assertArrayHasKey('connector_grants', $entry->changeSections());

        $permissions = $this->config()['connectorPermissions'];
        $this->assertSame(2, $permissions['revision']);
        $this->assertSame('expired', $permissions['grants'][0]['status']);
        $this->assertNotNull($permissions['grants'][0]['expiresAt']);
    }

    public function test_the_sweep_is_idempotent(): void
    {
        $this->grant(['tools:invoke'], '7');
        $this->travel(8)->days();

        $this->artisan('connectors:expire-grants')->assertSuccessful();
        AuditLog::query()->delete();

        $this->artisan('connectors:expire-grants')->assertSuccessful();

        $this->assertSame(0, AuditLog::query()->count());
        $this->assertSame(2, ConnectorGrant::query()->sole()->revision);
    }

    public function test_approving_again_revives_an_expired_grant_with_a_fresh_lease(): void
    {
        $this->grant(['tools:invoke'], '7');
        $this->travel(8)->days();
        $this->artisan('connectors:expire-grants')->assertSuccessful();

        $this->grant(['tools:invoke'], '90');

        $grant = ConnectorGrant::query()->sole();
        $this->assertSame(ConnectorGrant::STATUS_ACTIVE, $grant->status);
        $this->assertTrue($grant->lease_expires_at->isFuture());
        $this->assertSame(3, $grant->revision);

        $delivered = $this->config()['connectorPermissions']['grants'][0];
        $this->assertSame('active', $delivered['status']);
        $this->assertSame(3, $delivered['revision']);
    }

    public function test_revoking_clears_the_lease(): void
    {
        $this->grant(['tools:invoke'], '30');
        $this->assertNotNull(ConnectorGrant::query()->sole()->lease_expires_at);

        $this->revoke('No longer needed.');

        $grant = ConnectorGrant::query()->sole();
        $this->assertSame(ConnectorGrant::STATUS_REVOKED, $grant->status);
        $this->assertNull($grant->lease_expires_at);
        $this->assertNull($this->config()['connectorPermissions']['grants'][0]['expiresAt']);
    }

    public function test_the_screen_shows_the_duration_and_an_expired_approval(): void
    {
        $this->grant(['tools:invoke'], '7');
        $this->travel(8)->days();

        $page = $this->actingAs($this->admin)
            ->get('/admin/connectors?organization='.$this->organization->getKey())
            ->assertOk();

        $page->assertSee('name="expires_in"', false);
        $page->assertSee(__('ui.admin.connectors.status_expired'));
    }

    public function test_a_duration_outside_the_offered_set_is_rejected(): void
    {
        $this->actingAs($this->admin)->patch('/admin/connectors', [
            'organization' => $this->organization->getKey(),
            'slug' => self::SLUG,
            'action' => 'grant',
            'scopes' => [self::SLUG => ['tools:invoke']],
            'expires_in' => '999',
        ])->assertSessionHasErrors('expires_in');

        $this->assertSame(0, ConnectorGrant::query()->count());
    }

    public function test_a_seven_day_lease_maps_back_to_a_seven_day_choice(): void
    {
        $this->grant(['tools:invoke'], '7');

        $this->assertSame('7', ConnectorGrantPolicy::expiryChoiceFor(ConnectorGrant::query()->sole()));
    }

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
    private function grant(array $scopes, ?string $expiresIn = null): void
    {
        $this->actingAs($this->admin)->patch('/admin/connectors', [
            'organization' => $this->organization->getKey(),
            'slug' => self::SLUG,
            'action' => 'grant',
            'scopes' => [self::SLUG => $scopes],
            ...($expiresIn !== null ? ['expires_in' => $expiresIn] : []),
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

    private function workspace(User $user): Organization
    {
        $organization = Organization::factory()->create(['owner_id' => $user->getKey()]);

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
