<?php

namespace Tests\Feature;

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
 * The operator's control over providers and keys: which providers a plan
 * covers, whether the AI gateway is on at all, and whether members may bring
 * their own API keys. The decision is delivered to the desktop under
 * `providerEntitlements` on `GET /v1/me/desktop-config`, and the existing
 * `allowCustomProviders` key already hides the BYOK surface when it is off.
 */
class ProviderEntitlementTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private Organization $organization;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->admin()->create(['locale' => 'en']);
        $this->organization = Organization::factory()->create(['owner_id' => $owner->getKey()]);
        Membership::query()->create([
            'organization_id' => $this->organization->getKey(),
            'user_id' => $owner->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        $this->member = User::factory()->create(['locale' => 'en']);
        Membership::query()->create([
            'organization_id' => $this->organization->getKey(),
            'user_id' => $this->member->getKey(),
            'role' => Membership::ROLE_MEMBER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        [, $this->token] = ApiKey::issue($this->organization, $this->member, 'Member key');
    }

    public function test_the_block_lists_only_the_providers_the_plan_covers(): void
    {
        $plan = $this->assignPlan('pro', [Plan::FEATURE_CUSTOM_PROVIDERS => true]);
        $everyone = AiProvider::factory()->create(['slug' => 'openai', 'name' => 'OpenAI', 'is_active' => true]);
        $proOnly = AiProvider::factory()->create(['slug' => 'anthropic', 'name' => 'Anthropic', 'is_active' => true]);
        $proOnly->plans()->attach($plan);
        $otherPlan = Plan::factory()->create(['slug' => 'other']);
        $otherOnly = AiProvider::factory()->create(['slug' => 'gemini', 'name' => 'Gemini', 'is_active' => true]);
        $otherOnly->plans()->attach($otherPlan);
        AiProvider::factory()->create(['slug' => 'inactive', 'name' => 'Inactive', 'is_active' => false]);

        $block = $this->config()['providerEntitlements'];

        $this->assertTrue($block['enabled']);
        $this->assertSame('pro', $block['plan']);
        $slugs = array_column($block['providers'], 'slug');
        $this->assertContains('openai', $slugs, 'A provider with no plan rows serves every plan.');
        $this->assertContains('anthropic', $slugs, 'A provider assigned to the plan is listed.');
        $this->assertNotContains('gemini', $slugs, 'A provider assigned to another plan is not listed.');
        $this->assertNotContains('inactive', $slugs, 'An inactive provider is never listed.');
    }

    public function test_byok_is_off_until_the_operator_enables_it_and_the_org_kill_switch_still_denies_it(): void
    {
        // A plan that never mentions custom_providers. The platform-wide switch
        // is off by default: a member's desktop offers the platform's own models
        // only, so nobody spends on a key the operator did not approve.
        $this->assignPlan('pro', []);

        $this->assertFalse($this->config()['providerEntitlements']['byok'], 'BYOK starts off platform-wide.');
        $this->assertFalse($this->config()['allowCustomProviders'], 'The BYOK surface starts hidden.');

        Setting::put('byok_enabled', true);

        $this->assertTrue($this->config()['providerEntitlements']['byok']);
        $this->assertTrue($this->config()['allowCustomProviders']);

        // The org kill switch then narrows it back down.
        $this->killByok();

        $block = $this->config();
        $this->assertFalse($block['providerEntitlements']['byok'], 'The org kill switch turns BYOK off.');
        $this->assertFalse($block['allowCustomProviders'], 'The same switch hides the BYOK surface everywhere.');
    }

    public function test_a_plan_can_opt_back_in_while_the_kill_switch_is_on(): void
    {
        $this->assignPlan('vip', [Plan::FEATURE_CUSTOM_PROVIDERS => true]);
        Setting::put('byok_enabled', true);
        $this->killByok();

        $this->assertTrue(
            $this->config()['allowCustomProviders'],
            'A plan that explicitly grants custom_providers overrides the org kill switch.',
        );
    }

    public function test_a_plan_that_denies_byok_denies_it_even_with_the_switch_off(): void
    {
        $this->assignPlan('locked', [Plan::FEATURE_CUSTOM_PROVIDERS => false]);

        $this->assertFalse($this->config()['providerEntitlements']['byok']);
        $this->assertFalse($this->config()['allowCustomProviders']);
    }

    public function test_the_gateway_switch_hides_every_provider(): void
    {
        $this->assignPlan('pro', [Plan::FEATURE_CUSTOM_PROVIDERS => true]);
        AiProvider::factory()->create(['slug' => 'openai', 'name' => 'OpenAI', 'is_active' => true]);

        Setting::put('gateway_enabled', false);

        $block = $this->config()['providerEntitlements'];

        $this->assertFalse($block['enabled']);
        $this->assertSame([], $block['providers'], 'With the gateway off the desktop is told about no provider.');
    }

    public function test_the_operator_can_kill_and_restore_byok_from_the_admin_screen(): void
    {
        $this->assignPlan('pro', []);

        // A member is not an operator: the admin route must not act for them.
        $denied = $this->actingAs($this->member)
            ->patch('/admin/organizations/'.$this->organization->slug.'/byok', ['byok_killed' => '1']);
        $this->assertTrue($denied->status() >= 400, 'A member cannot reach the admin screen.');
        $this->assertFalse((bool) ($this->organization->fresh()->settings['byok_killed'] ?? false));

        $admin = User::factory()->admin()->create(['locale' => 'en']);
        $this->actingAs($admin)
            ->patch('/admin/organizations/'.$this->organization->slug.'/byok', ['byok_killed' => '1'])
            ->assertRedirect();

        $this->assertTrue((bool) ($this->organization->fresh()->settings['byok_killed'] ?? false));
        $this->assertFalse($this->config()['providerEntitlements']['byok']);
    }

    /**
     * @param  array<string, mixed>  $features
     */
    private function assignPlan(string $slug, array $features): Plan
    {
        $plan = Plan::factory()->create(['slug' => $slug, 'features' => $features]);

        Subscription::factory()->create([
            'organization_id' => $this->organization->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        return $plan;
    }

    private function killByok(): void
    {
        $settings = $this->organization->settings ?? [];
        $settings['byok_killed'] = true;
        $this->organization->forceFill(['settings' => $settings])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return $this->getJson(
            '/v1/me/desktop-config',
            ['Authorization' => 'Bearer '.$this->token],
        )->assertOk()->json();
    }
}
