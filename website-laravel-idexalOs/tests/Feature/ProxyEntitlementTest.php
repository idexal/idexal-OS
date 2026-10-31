<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\ProxyProvider;
use App\Models\Setting;
use App\Models\User;
use App\Services\ProxyEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProxyEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_desktop_config_returns_the_free_proxy_entitlements_and_visible_providers(): void
    {
        $free = $this->plan('free');
        $pro = $this->plan('pro', [
            'features' => [Plan::FEATURE_PROXY_FINGERPRINT => true],
            'limits' => [Plan::LIMIT_PROXY_PROFILES => 50],
        ]);
        $allPlans = ProxyProvider::factory()->create(['slug' => 'all-plans', 'name' => 'All Plans', 'priority' => 10]);
        $freeOnly = ProxyProvider::factory()->create(['slug' => 'free-only', 'name' => 'Free Only', 'priority' => 20]);
        $freeOnly->plans()->attach($free);
        $proOnly = ProxyProvider::factory()->create(['slug' => 'pro-only', 'name' => 'Pro Only', 'priority' => 30]);
        $proOnly->plans()->attach($pro);
        ProxyProvider::factory()->inactive()->create(['slug' => 'inactive', 'name' => 'Inactive', 'priority' => 40]);
        ProxyProvider::factory()->create([
            'slug' => 'private',
            'name' => 'Private',
            'is_public' => false,
            'priority' => 50,
        ]);

        [$user, $organization] = $this->workspace();
        [, $token] = ApiKey::issue($organization, $user, 'Desktop');

        $response = $this->withToken($token)->getJson('/v1/me/desktop-config');

        $response->assertOk()
            ->assertJsonPath('proxy.enabled', true)
            ->assertJsonPath('proxy.plan', 'free')
            ->assertJsonPath('proxy.features.proxy', true)
            ->assertJsonPath('proxy.features.residential', true)
            ->assertJsonPath('proxy.features.rotation', false)
            ->assertJsonPath('proxy.features.fingerprint', false)
            ->assertJsonPath('proxy.features.prewarm', false)
            ->assertJsonPath('proxy.limits.profiles', 1)
            ->assertJsonPath('proxy.limits.bandwidthGb', 5)
            ->assertJsonCount(2, 'proxy.providers')
            ->assertJsonPath('proxy.providers.0.slug', 'all-plans')
            ->assertJsonPath('proxy.providers.1.slug', 'free-only');

        $this->assertSame(
            ['id', 'name', 'slug', 'driver', 'protocol', 'websiteUrl', 'docsUrl', 'description', 'countries'],
            array_keys($response->json('proxy.providers.0')),
        );
    }

    public function test_turning_proxy_off_hides_entitlements_and_cloud_feature_flags(): void
    {
        $this->plan('free');
        Setting::put('proxy_enabled', false);
        [$user, $organization] = $this->workspace();
        [, $token] = ApiKey::issue($organization, $user, 'Desktop');

        $this->withToken($token)->getJson('/v1/me/desktop-config')
            ->assertOk()
            ->assertJsonPath('proxy.enabled', false)
            ->assertJsonPath('proxy.features.proxy', false)
            ->assertJsonPath('proxy.providers', []);

        $this->getJson('/v1/features')
            ->assertOk()
            ->assertJsonPath('features.proxyManager', false)
            ->assertJsonPath('features.browserPrewarm', false);
    }

    public function test_browser_prewarm_can_be_disabled_without_disabling_proxy_manager(): void
    {
        $this->plan('free', [
            'features' => [Plan::FEATURE_BROWSER_PREWARM => true],
        ]);
        Setting::put('browser_prewarm_enabled', false);
        [$user, $organization] = $this->workspace();
        [, $token] = ApiKey::issue($organization, $user, 'Desktop');

        $this->withToken($token)->getJson('/v1/me/desktop-config')
            ->assertOk()
            ->assertJsonPath('proxy.enabled', true)
            ->assertJsonPath('proxy.features.prewarm', false);

        $this->getJson('/v1/features')
            ->assertOk()
            ->assertJsonPath('features.proxyManager', true)
            ->assertJsonPath('features.browserPrewarm', false);
    }

    public function test_local_fallback_keeps_one_residential_profile_and_prewarm_without_an_account(): void
    {
        $entitlements = app(ProxyEntitlementService::class)->localFallback();

        $this->assertTrue($entitlements['enabled']);
        $this->assertSame('local', $entitlements['plan']);
        $this->assertTrue($entitlements['features']['proxy']);
        $this->assertTrue($entitlements['features']['residential']);
        $this->assertFalse($entitlements['features']['rotation']);
        $this->assertFalse($entitlements['features']['fingerprint']);
        $this->assertTrue($entitlements['features']['prewarm']);
        $this->assertSame(1, $entitlements['limits']['profiles']);
        $this->assertSame(5, $entitlements['limits']['bandwidthGb']);
        $this->assertSame([], $entitlements['providers']);
    }

    public function test_desktop_capabilities_follow_the_plan_features(): void
    {
        $this->plan('free', [
            'features' => [
                Plan::FEATURE_CUSTOM_PROVIDERS => false,
                Plan::FEATURE_MULTIPLE_WORKSPACES => false,
                Plan::FEATURE_MANAGE_EXTENSIONS => false,
            ],
        ]);
        [$user, $organization] = $this->workspace();
        [, $token] = ApiKey::issue($organization, $user, 'Desktop');

        $this->withToken($token)->getJson('/v1/me/desktop-config')
            ->assertOk()
            ->assertJsonPath('allowCustomProviders', false)
            ->assertJsonPath('allowMultipleWorkspaces', false)
            ->assertJsonPath('allowManageExtensions', false)
            ->assertJsonPath('allowBuiltInExtensions', false)
            ->assertJsonPath('allowControlSettings', true);
    }

    public function test_missing_capability_keys_on_legacy_plans_stay_allowed(): void
    {
        // BYOK is a platform-wide switch now and it starts off; turn it on so
        // this test is about the legacy default for a missing capability key
        // and not about that separate switch.
        Setting::put('byok_enabled', true);

        Plan::factory()->create([
            'slug' => 'legacy',
            'features' => [Plan::FEATURE_PROXY => true],
        ]);
        [$user, $organization] = $this->workspace();
        [, $token] = ApiKey::issue($organization, $user, 'Desktop');

        $this->withToken($token)->getJson('/v1/me/desktop-config')
            ->assertOk()
            ->assertJsonPath('allowCustomProviders', true)
            ->assertJsonPath('allowMultipleWorkspaces', true)
            ->assertJsonPath('allowManageExtensions', true)
            ->assertJsonPath('agentControl.enabled', true);
    }

    public function test_free_plans_explicitly_deny_agent_desktop_control(): void
    {
        $this->plan('free', [
            'features' => [
                Plan::FEATURE_AGENT_CONTROL => false,
                Plan::FEATURE_AGENT_SETTINGS => false,
                Plan::FEATURE_AGENT_PROXY => false,
                Plan::FEATURE_AGENT_EXTENSIONS => false,
                Plan::FEATURE_AGENT_BROWSER => false,
                Plan::FEATURE_AGENT_PROVIDERS => false,
            ],
        ]);
        [$user, $organization] = $this->workspace();
        [, $token] = ApiKey::issue($organization, $user, 'Desktop');

        $this->withToken($token)->getJson('/v1/me/desktop-config')
            ->assertOk()
            ->assertJsonPath('agentControl.enabled', false)
            ->assertJsonPath('agentControl.settings', false)
            ->assertJsonPath('agentControl.proxies', false)
            ->assertJsonPath('agentControl.extensions', false)
            ->assertJsonPath('agentControl.browser', false)
            ->assertJsonPath('agentControl.providers', false);
    }

    public function test_plans_can_enable_the_agent_desktop_control_families(): void
    {
        $this->plan('free', [
            'features' => [
                Plan::FEATURE_AGENT_CONTROL => true,
                Plan::FEATURE_AGENT_SETTINGS => true,
                Plan::FEATURE_AGENT_PROXY => true,
                Plan::FEATURE_AGENT_EXTENSIONS => true,
                Plan::FEATURE_AGENT_BROWSER => true,
                Plan::FEATURE_AGENT_PROVIDERS => true,
                Plan::FEATURE_MANAGE_EXTENSIONS => true,
                Plan::FEATURE_CUSTOM_PROVIDERS => true,
            ],
        ]);
        [$user, $organization] = $this->workspace();
        [, $token] = ApiKey::issue($organization, $user, 'Desktop');

        $this->withToken($token)->getJson('/v1/me/desktop-config')
            ->assertOk()
            ->assertJsonPath('agentControl.enabled', true)
            ->assertJsonPath('agentControl.settings', true)
            ->assertJsonPath('agentControl.proxies', true)
            ->assertJsonPath('agentControl.extensions', true)
            ->assertJsonPath('agentControl.browser', true)
            ->assertJsonPath('agentControl.providers', true);
    }

    public function test_agent_families_follow_their_base_desktop_entitlement(): void
    {
        $this->plan('free', [
            'features' => [
                Plan::FEATURE_AGENT_CONTROL => true,
                Plan::FEATURE_AGENT_PROXY => true,
                Plan::FEATURE_AGENT_EXTENSIONS => true,
                Plan::FEATURE_AGENT_BROWSER => true,
                Plan::FEATURE_AGENT_PROVIDERS => true,
                Plan::FEATURE_PROXY => false,
                Plan::FEATURE_MANAGE_EXTENSIONS => false,
                Plan::FEATURE_CUSTOM_PROVIDERS => false,
            ],
        ]);
        [$user, $organization] = $this->workspace();
        [, $token] = ApiKey::issue($organization, $user, 'Desktop');

        $this->withToken($token)->getJson('/v1/me/desktop-config')
            ->assertOk()
            ->assertJsonPath('agentControl.enabled', true)
            ->assertJsonPath('agentControl.proxies', false)
            ->assertJsonPath('agentControl.extensions', false)
            ->assertJsonPath('agentControl.browser', false)
            ->assertJsonPath('agentControl.providers', false);
    }

    /**
     * @param  array<string, array<string, mixed>>  $overrides
     */
    private function plan(string $slug, array $overrides = []): Plan
    {
        return Plan::factory()->create([
            'slug' => $slug,
            'features' => array_merge([
                Plan::FEATURE_PROXY => true,
                Plan::FEATURE_PROXY_RESIDENTIAL => true,
                Plan::FEATURE_PROXY_ROTATION => false,
                Plan::FEATURE_PROXY_FINGERPRINT => false,
                Plan::FEATURE_BROWSER_PREWARM => false,
            ], $overrides['features'] ?? []),
            'limits' => array_merge([
                Plan::LIMIT_PROXY_PROFILES => 1,
                Plan::LIMIT_PROXY_BANDWIDTH_GB => 5,
            ], $overrides['limits'] ?? []),
        ]);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function workspace(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['owner_id' => $user->getKey()]);

        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        return [$user, $organization];
    }
}
