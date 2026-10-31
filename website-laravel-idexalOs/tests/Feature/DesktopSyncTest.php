<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\ApiKey;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Support\DesktopSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The platform → desktop sync contract: an admin change must move the
 * inventory revision a connected desktop reads, so it re-syncs instead of
 * waiting out its polling interval.
 */
class DesktopSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $organization;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->admin->getKey()]);

        Membership::query()->create([
            'organization_id' => $this->organization->getKey(),
            'user_id' => $this->admin->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        [, $this->token] = ApiKey::issue($this->organization, $this->admin, 'Desktop sync key');
    }

    public function test_announcing_advances_the_sequence_and_records_the_latest_change(): void
    {
        $before = DesktopSync::sequence();

        DesktopSync::announce('provider_updated', ['provider_slug' => 'example-ai']);

        $this->assertSame($before + 1, DesktopSync::sequence());

        $latest = Cache::get('desktop.sync.latest');
        $this->assertSame('desktop-sync', $latest['event']);
        $this->assertSame('provider_updated', $latest['action']);
        $this->assertSame('example-ai', $latest['provider_slug']);
        $this->assertArrayHasKey('timestamp', $latest);
    }

    public function test_the_inventory_revision_changes_when_anything_announces(): void
    {
        $before = $this->revision();

        DesktopSync::announce('settings_updated');

        $this->assertNotSame($before, $this->revision());
    }

    /**
     * The production cache store is the database, whose increment() leaves a
     * missing key missing instead of starting it at zero. Announce must seed
     * the sequence, or the very first change never reaches a desktop.
     */
    public function test_announcing_starts_the_sequence_on_a_cold_database_cache(): void
    {
        config(['cache.default' => 'database']);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');

        Cache::forget('desktop.sync.sequence');

        $this->assertNull(Cache::get('desktop.sync.sequence'));

        DesktopSync::announce('cold_start');

        $this->assertSame(1, DesktopSync::sequence());
    }

    public function test_the_inventory_revision_changes_when_a_plan_is_saved(): void
    {
        $before = $this->revision();

        Plan::factory()->create();

        $this->assertNotSame($before, $this->revision());
    }

    public function test_an_admin_settings_change_reaches_desktops(): void
    {
        $before = $this->revision();

        $this->actingAs($this->admin)
            ->patch('/admin/settings', $this->settingsPayload())
            ->assertRedirect('/admin/settings');

        $this->assertSame('settings_updated', Cache::get('desktop.sync.latest')['action']);
        $this->assertNotSame($before, $this->revision());
    }

    public function test_an_admin_plan_change_reaches_desktops(): void
    {
        $before = $this->revision();

        $this->actingAs($this->admin)
            ->post('/admin/plans', [
                'name' => 'Team',
                'slug' => 'team',
                'price_monthly' => '20',
                'price_yearly' => '200',
                'currency' => 'USD',
                'is_active' => '1',
                'is_public' => '1',
            ])
            ->assertRedirect('/admin/plans');

        $this->assertSame('plan_created', Cache::get('desktop.sync.latest')['action']);
        $this->assertNotSame($before, $this->revision());
    }

    public function test_deleting_a_provider_reaches_desktops(): void
    {
        $provider = AiProvider::factory()->create([
            'slug' => 'example-ai',
            'is_active' => true,
        ]);

        $before = $this->revision();

        $this->actingAs($this->admin)
            ->delete('/admin/providers/'.$provider->getKey())
            ->assertRedirect('/admin/providers');

        $this->assertSame('provider_deleted', Cache::get('desktop.sync.latest')['action']);
        $this->assertNotSame($before, $this->revision());
    }

    /**
     * @return array<string, string>
     */
    private function settingsPayload(): array
    {
        return [
            'site_name' => 'idexal Os',
            'site_tagline' => 'The AI workspace without limits',
            'support_email' => 'os@idexal.com',
            'default_currency' => 'usd',
            'tax_rate' => '0',
            'invoice_due_days' => '14',
            'payment_instructions' => '',
            'desktop_latest_version' => '0.1.0',
            'desktop_min_version' => '0.0.1',
            'maintenance_message' => '',
            'registration_enabled' => '1',
            'require_email_verification' => '0',
            'gateway_enabled' => '1',
            'billing_enabled' => '1',
        ];
    }

    private function revision(): string
    {
        $response = $this->withToken($this->token)->getJson('/v1/org/inventory-revision');

        $response->assertOk();

        return (string) $response->json('revision');
    }
}
