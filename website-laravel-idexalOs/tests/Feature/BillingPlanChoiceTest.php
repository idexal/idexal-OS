<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Plan;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Choosing a plan is the gate in front of the desktop. A workspace with no
 * subscription must be able to choose the free plan from the billing page —
 * the page used to mark the free plan "current" (as a display fallback) and
 * render no action for it, so a new account could never satisfy the gate.
 */
class BillingPlanChoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_workspace_without_a_subscription_is_offered_the_free_plan(): void
    {
        [$user] = $this->workspaceWithoutPlan();

        $response = $this->actingAs($user)->get('/dashboard/billing')->assertOk();

        $response->assertSeeText(__('ui.billing.no_plan'));
        $response->assertSeeText(__('ui.billing.choose_to_activate'));
        // The free plan is a real, submittable choice, not a "current" badge.
        $response->assertSee('name="plan" value="free"', false);
        $response->assertSeeText(__('ui.pricing.choose_plan'));
    }

    public function test_choosing_the_free_plan_unlocks_the_desktop(): void
    {
        [$user, $organization] = $this->workspaceWithoutPlan();

        [, $token] = ApiKey::issue($organization, $user, 'Desktop key');

        $this->getJson('/v1/me/desktop-config', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('desktopAccess.granted', false);

        $this->actingAs($user)
            ->post('/dashboard/billing/plan', ['plan' => 'free', 'billing_cycle' => 'monthly'])
            ->assertRedirect(route('dashboard.billing'));

        $this->assertSame(1, $organization->fresh()->subscriptions()->count());

        $this->getJson('/v1/me/desktop-config', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('desktopAccess.granted', true)
            ->assertJsonPath('desktopAccess.planSlug', 'free');
    }

    public function test_the_registration_notice_has_no_unrendered_placeholders(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertDontSee(':terms', false)
            ->assertDontSee(':privacy', false)
            ->assertSeeText(__('ui.auth.terms_agree'));
    }

    /**
     * @return array{0: User, 1: \App\Models\Organization}
     */
    private function workspaceWithoutPlan(): array
    {
        Plan::factory()->free()->create([
            'slug' => 'free',
            'name' => 'Free',
            'is_active' => true,
            'is_public' => true,
        ]);

        $user = User::factory()->create(['locale' => 'en']);
        $organization = app(OnboardingService::class)->createPersonalOrganization($user);

        $this->assertSame(0, $organization->subscriptions()->count());

        return [$user, $organization];
    }
}
