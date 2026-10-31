<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\DesktopSwitch;
use App\Support\PlatformFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The desktop hides any feature whose key the platform does not publish, so the
 * two endpoints it reads have to answer the same map. These tests hold that
 * agreement, and hold the operator's ability to switch a desktop experience on.
 */
class DesktopSwitchesTest extends TestCase
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

        [, $this->token] = ApiKey::issue($this->organization, $this->admin, 'Desktop switches key');
    }

    public function test_the_deployment_map_publishes_every_switch_as_off(): void
    {
        $features = $this->getJson('/v1/features')->assertOk()->json('features');

        foreach (DesktopSwitch::cases() as $switch) {
            $this->assertArrayHasKey($switch->value, $features);
            $this->assertFalse($features[$switch->value], $switch->value.' ships off.');
        }

        $this->assertTrue($features['library']);
        $this->assertTrue($features['automations']);
    }

    public function test_the_signed_in_map_agrees_with_the_deployment_map(): void
    {
        $deployment = $this->getJson('/v1/features')->assertOk()->json('features');
        $signedIn = $this->withToken($this->token)->getJson('/v1/org')->assertOk()->json('features');

        $this->assertSame($deployment, $signedIn);
    }

    public function test_an_operator_switch_reaches_both_endpoints(): void
    {
        $this->actingAs($this->admin)
            ->patch('/admin/features', [
                'desktop_switch_last_turn_model_footer' => '1',
            ])
            ->assertRedirect(route('admin.features'))
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->getJson('/v1/features')->json('features.lastTurnModelFooter'));
        $this->assertTrue($this->withToken($this->token)->getJson('/v1/org')->json('features.lastTurnModelFooter'));

        // A switch nobody touched keeps its default.
        $this->assertFalse($this->getJson('/v1/features')->json('features.appBuildProgress'));
    }

    public function test_a_switch_and_its_effect_are_audited(): void
    {
        $this->actingAs($this->admin)->patch('/admin/features', [
            'desktop_switch_offline_recovery' => '1',
        ]);

        $entry = AuditLog::query()->where('action', 'admin.features.updated')->sole();

        $this->assertSame(['offlineRecovery' => ['from' => false, 'to' => true]], $entry->meta['desktop']);
        $this->assertArrayNotHasKey('features', $entry->meta, 'No gateway toggle moved.');
        $this->assertStringContainsString(__('ui.admin.features.desktop.offline_recovery_name'), (string) $entry->description);
    }

    public function test_the_features_page_lists_every_switch(): void
    {
        $page = $this->actingAs($this->admin)->get('/admin/features')->assertOk();

        $page->assertSee(__('ui.admin.features.desktop.title'));

        foreach (DesktopSwitch::cases() as $switch) {
            $page->assertSee(__($switch->labelKey()));
            $page->assertSee(__($switch->bodyKey()));
            $page->assertSee($switch->fieldKey(), false);
        }
    }

    public function test_the_switch_list_names_the_operator_and_the_moment(): void
    {
        $this->actingAs($this->admin)->patch('/admin/features', [
            'desktop_switch_remember_last_organization' => '1',
        ]);

        $this->actingAs($this->admin)->get('/admin/features')
            ->assertOk()
            ->assertSee(__('ui.admin.features.desktop.remember_last_organization_name'))
            ->assertSee($this->admin->name);
    }

    public function test_the_engine_upgrade_switch_is_never_published(): void
    {
        $features = $this->getJson('/v1/features')->assertOk()->json('features');

        $this->assertArrayNotHasKey('engineV2Upgrade', $features);
        $this->assertArrayNotHasKey('engineV2Upgrade', PlatformFeatures::map(app(SettingsService::class)));
    }
}
