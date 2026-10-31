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
 * The providers console: the operator sees which plans each provider belongs
 * to, turns one on or off from the list, and can cancel members' own keys for
 * the whole platform — the same control the proxy manager gives over proxies.
 */
class AdminProviderConsoleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['locale' => 'en']);
    }

    public function test_the_list_shows_which_plans_each_provider_belongs_to(): void
    {
        $proOrBusiness = Plan::factory()->create(['slug' => 'pro', 'name' => 'Pro']);
        $unassigned = AiProvider::factory()->create(['slug' => 'everywhere', 'name' => 'Everywhere', 'is_active' => true]);
        $scoped = AiProvider::factory()->create(['slug' => 'pro-only', 'name' => 'Pro only', 'is_active' => true]);
        $scoped->plans()->attach($proOrBusiness);

        $response = $this->actingAs($this->admin)->get('/admin/providers')->assertOk();

        $response->assertSeeText('Pro');
        $response->assertSeeText(__('ui.admin.providers.all_plans'));
    }

    public function test_the_operator_can_turn_a_provider_off_and_on_from_the_list(): void
    {
        $provider = AiProvider::factory()->create(['slug' => 'toggle-me', 'name' => 'Toggle me', 'is_active' => true]);

        $this->actingAs($this->admin)
            ->post('/admin/providers/'.$provider->getKey().'/toggle')
            ->assertRedirect();

        $this->assertFalse($provider->fresh()->is_active, 'One click takes a provider out of every desktop.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.provider.toggled']);

        $this->actingAs($this->admin)
            ->post('/admin/providers/'.$provider->getKey().'/toggle')
            ->assertRedirect();

        $this->assertTrue($provider->fresh()->is_active);
    }

    public function test_an_inactive_provider_reaches_no_desktop(): void
    {
        $provider = AiProvider::factory()->create([
            'slug' => 'off',
            'name' => 'Off',
            'driver' => AiProvider::DRIVER_OPENAI,
            'api_key' => 'sk-test',
            'is_active' => true,
        ]);
        AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => 'off-model',
            'display_name' => 'Off model',
            'is_active' => true,
        ]);

        [$token, $organization] = $this->member();

        $before = $this->withToken($token)->getJson('/v1/inference-providers')->assertOk();
        $this->assertNotEmpty($before->json('inferenceProviders.0.models'));

        $this->actingAs($this->admin)->post('/admin/providers/'.$provider->getKey().'/toggle')->assertRedirect();

        $after = $this->withToken($token)->getJson('/v1/inference-providers')->assertOk();
        $this->assertSame([], $after->json('inferenceProviders'), 'A provider switched off is gone from the desktop.');
    }

    public function test_the_operator_can_cancel_byok_for_the_whole_platform(): void
    {
        [$token] = $this->member();

        // Off by default: a member's desktop offers the platform's models and
        // no own-keys surface, even when their plan would allow it.
        $this->assertFalse(
            $this->withToken($token)->getJson('/v1/me/desktop-config')->json('allowCustomProviders'),
            'Nobody brings their own key until the operator allows it.',
        );
        $this->actingAs($this->admin)->get('/admin/providers')
            ->assertOk()
            ->assertSeeText(__('ui.admin.providers.byok_title'));

        // The operator opens own-keys again.
        $this->actingAs($this->admin)
            ->post('/admin/providers/byok', ['byok_enabled' => '1'])
            ->assertRedirect();

        $this->assertTrue(Setting::get('byok_enabled') === '1');
        $this->assertTrue(
            $this->withToken($token)->getJson('/v1/me/desktop-config')->json('allowCustomProviders'),
            'The switch opens own-keys for a plan that allows them.',
        );

        // And closes them again, platform-wide.
        $this->actingAs($this->admin)
            ->post('/admin/providers/byok', ['byok_enabled' => '0'])
            ->assertRedirect();

        $this->assertFalse(Setting::get('byok_enabled') === '1');
        $this->assertFalse(
            $this->withToken($token)->getJson('/v1/me/desktop-config')->json('allowCustomProviders'),
            'The platform switch outranks the plan.',
        );
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.byok.toggled']);
    }

    public function test_the_platform_switch_outranks_a_plan_that_grants_byok(): void
    {
        [$token] = $this->member([Plan::FEATURE_CUSTOM_PROVIDERS => true]);

        Setting::put('byok_enabled', '1');
        $this->assertTrue($this->withToken($token)->getJson('/v1/me/desktop-config')->json('allowCustomProviders'));

        Setting::put('byok_enabled', '0');

        $this->assertFalse(
            $this->withToken($token)->getJson('/v1/me/desktop-config')->json('allowCustomProviders'),
            'No plan can bring BYOK back while the platform has cancelled it.',
        );
    }

    public function test_a_member_cannot_reach_the_providers_console(): void
    {
        [$token] = $this->member();
        $provider = AiProvider::factory()->create(['is_active' => true]);
        $member = User::query()->where('email', 'member@example.test')->sole();

        $this->actingAs($member)->get('/admin/providers')->assertForbidden();
        $this->actingAs($member)->post('/admin/providers/'.$provider->getKey().'/toggle')->assertForbidden();

        $this->assertTrue($provider->fresh()->is_active);
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
