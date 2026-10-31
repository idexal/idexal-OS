<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\OnboardingService;
use App\Support\DesktopSwitch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two-stage desktop gate: sign-in is always required on the public
 * installer, and a plan-level grant decides whether a signed-in user can open
 * the app at all. The `desktopAccess` block is delivered under
 * `GET /v1/me/desktop-config`; while the `accountRequiredForDesktopAccess`
 * switch is off every plan grants access, so a deployment that has not adopted
 * the gate behaves exactly as before.
 */
class PlatformDesktopAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $organization;

    private string $memberToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->admin()->create(['locale' => 'en']);
        $this->organization = $this->workspace($this->owner, null, Membership::ROLE_OWNER);

        $member = User::factory()->create(['locale' => 'en']);
        $this->workspace($member, $this->organization, Membership::ROLE_MEMBER);
        [, $this->memberToken] = ApiKey::issue($this->organization, $member, 'Member key');
    }

    public function test_the_block_is_delivered_while_the_switch_is_off_and_every_plan_grants(): void
    {
        $this->assignPlan('free', [Plan::FEATURE_DESKTOP_ACCESS => false]);

        $config = $this->config();

        $this->assertTrue($config['desktopAccess']['required'], 'The public flavor always requires sign-in.');
        $this->assertTrue($config['desktopAccess']['granted'], 'While off, every plan grants access.');
        $this->assertSame('free', $config['desktopAccess']['planSlug']);
    }

    public function test_a_plan_without_the_key_grants_access_when_the_switch_is_on(): void
    {
        $this->assignPlan('legacy');
        $this->turnOn();

        $this->assertTrue($this->config()['desktopAccess']['granted'], 'A plan created before the gate still grants.');
    }

    public function test_a_plan_that_denies_blocks_access_when_the_switch_is_on(): void
    {
        $this->assignPlan('basic', [Plan::FEATURE_DESKTOP_ACCESS => false]);
        $this->turnOn();

        $config = $this->config();

        $this->assertTrue($config['desktopAccess']['required']);
        $this->assertFalse($config['desktopAccess']['granted'], 'A plan that explicitly denies blocks the app.');
        $this->assertSame('basic', $config['desktopAccess']['planSlug']);
    }

    public function test_a_plan_that_grants_allows_access_when_the_switch_is_on(): void
    {
        $this->assignPlan('pro', [Plan::FEATURE_DESKTOP_ACCESS => true]);
        $this->turnOn();

        $this->assertTrue($this->config()['desktopAccess']['granted']);
    }

    public function test_requiring_a_plan_leaves_a_new_account_without_one_and_blocks_the_desktop(): void
    {
        Setting::put('feature_require_plan_selection', 1);
        Plan::factory()->create(['slug' => 'free', 'name' => 'Free', 'is_active' => true, 'is_public' => true]);

        $user = User::factory()->create(['locale' => 'en']);
        $organization = app(OnboardingService::class)->createPersonalOrganization($user);

        $this->assertSame(0, $organization->subscriptions()->count(), 'No free plan is granted while plan selection is required.');

        [, $token] = ApiKey::issue($organization, $user, 'Member key');

        $this->getJson('/v1/me/desktop-config', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('desktopAccess.required', true)
            ->assertJsonPath('desktopAccess.granted', false);
    }

    public function test_the_default_policy_requires_a_plan_without_any_setting_row(): void
    {
        // The commercial promise is the default: a fresh installation has no
        // settings row and a new account still cannot open the desktop before
        // it holds a plan. This is what makes "sign in, then subscribe" real
        // rather than an operator checkbox nobody turns on.
        Plan::factory()->create(['slug' => 'free', 'name' => 'Free', 'is_active' => true, 'is_public' => true]);

        $user = User::factory()->create(['locale' => 'en']);
        $organization = app(OnboardingService::class)->createPersonalOrganization($user);

        $this->assertSame(0, $organization->subscriptions()->count());

        [, $token] = ApiKey::issue($organization, $user, 'Member key');

        $this->getJson('/v1/me/desktop-config', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('desktopAccess.required', true)
            ->assertJsonPath('desktopAccess.granted', false);
    }

    public function test_choosing_a_plan_opens_the_desktop(): void
    {
        Setting::put('feature_require_plan_selection', 1);
        $this->assignPlan('pro', [Plan::FEATURE_DESKTOP_ACCESS => true]);

        $this->assertTrue($this->config()['desktopAccess']['granted'], 'A chosen plan opens the app.');
    }

    public function test_no_active_plan_denies_access_when_the_switch_is_on(): void
    {
        $this->turnOn();

        $config = $this->config();

        $this->assertFalse($config['desktopAccess']['granted'], 'No plan, no access while the gate is on.');
        $this->assertNull($config['desktopAccess']['planSlug']);
    }

    public function test_turning_the_switch_off_restores_universal_access(): void
    {
        $this->assignPlan('basic', [Plan::FEATURE_DESKTOP_ACCESS => false]);
        $this->turnOn();
        $this->assertFalse($this->config()['desktopAccess']['granted']);

        $this->turnOff();

        $this->assertTrue($this->config()['desktopAccess']['granted'], 'The kill switch restores every plan.');
    }

    /**
     * @param  array<string, mixed>  $features
     */
    private function assignPlan(string $slug, array $features = []): Plan
    {
        $plan = Plan::factory()->create([
            'slug' => $slug,
            'features' => $features + [Plan::FEATURE_DESKTOP_ACCESS => true],
        ]);

        Subscription::factory()->create([
            'organization_id' => $this->organization->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        return $plan;
    }

    private function turnOn(): void
    {
        Setting::put(DesktopSwitch::AccountRequiredForDesktopAccess->settingKey(), true);
    }

    private function turnOff(): void
    {
        Setting::put(DesktopSwitch::AccountRequiredForDesktopAccess->settingKey(), false);
    }

    private function config(): array
    {
        $response = $this->getJson(
            '/v1/me/desktop-config',
            ['Authorization' => 'Bearer '.$this->memberToken],
        )->assertOk();

        return $response->json();
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
