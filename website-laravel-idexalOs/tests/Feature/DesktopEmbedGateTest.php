<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mandatory first-launch gate, completed inside the app: the desktop shows
 * the platform's sign-in and plan pages in a window of its own and hands back
 * the `idexalos://den-auth` grant without a click. These tests cover the
 * platform half — the embed flag, and the two flows that have to end in a
 * desktop handoff (an existing account signing in, and a new account
 * subscribing to a plan).
 */
class DesktopEmbedGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_entry_records_the_embed_flag_only_when_asked(): void
    {
        $this->get('/?desktopAuth=1&desktopEmbed=1')
            ->assertRedirect(route('login'))
            ->assertSessionHas('desktop_auth', true)
            ->assertSessionHas('desktop_embed', true);
    }

    public function test_a_second_plain_entry_clears_a_previously_embedded_flow(): void
    {
        $this->get('/?desktopAuth=1&desktopEmbed=1')
            ->assertSessionHas('desktop_embed', true);

        // A later browser hand-off states its own flow: the embedded flag is
        // not carried over.
        $this->get('/?desktopAuth=1')
            ->assertRedirect(route('login'))
            ->assertSessionMissing('desktop_embed');
    }

    public function test_the_completion_page_hands_back_on_its_own_in_an_embedded_window(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingAs($user)
            ->withSession(['desktop_grant' => 'grant-embed', 'desktop_embed' => true])
            ->get('/desktop/complete')
            ->assertOk();

        $response->assertSee('grant-embed', false);
        $response->assertSee('window.location.href =', false);
    }

    public function test_the_completion_page_waits_for_a_click_in_a_browser(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingAs($user)
            ->withSession(['desktop_grant' => 'grant-browser'])
            ->get('/desktop/complete')
            ->assertOk();

        $response->assertSee('grant-browser', false);
        $response->assertSee('Open the app');
        $response->assertDontSee('window.location.href =', false);
    }

    public function test_signing_up_from_the_gate_connects_the_desktop(): void
    {
        // Choosing a plan is the default. This covers the operator opt-out,
        // where sign-up connects the desktop directly.
        Setting::put('feature_require_plan_selection', 0);
        Plan::factory()->create(['slug' => 'free', 'name' => 'Free', 'is_active' => true, 'is_public' => true]);

        $response = $this->withSession(['desktop_auth' => true])->post('/register', [
            'name' => 'New User',
            'email' => 'new-user@example.test',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ]);

        $response->assertRedirect(route('desktop.authorize'));
        $this->assertAuthenticated();

        // The flag survives the registration so the authorize step can still
        // see it and issue the handoff.
        $response->assertSessionHas('desktop_auth', true);
    }

    public function test_signing_up_from_the_gate_goes_to_the_plan_chooser_when_a_plan_is_required(): void
    {
        Setting::put('feature_require_plan_selection', 1);
        Plan::factory()->create(['slug' => 'free', 'name' => 'Free', 'is_active' => true, 'is_public' => true]);

        $response = $this->withSession(['desktop_auth' => true])->post('/register', [
            'name' => 'Plan Picker',
            'email' => 'plan-picker@example.test',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ]);

        $response->assertRedirect(route('dashboard.billing'));
        $response->assertSessionHas('desktop_auth', true);
    }

    public function test_the_plan_chooser_hands_the_desktop_connection_on_once_a_plan_exists(): void
    {
        [$user] = $this->member();
        Plan::factory()->create(['slug' => 'pro', 'name' => 'Pro', 'is_active' => true, 'is_public' => true]);

        // No subscription yet: the chooser is shown, not a redirect.
        $this->actingAs($user)
            ->withSession(['desktop_auth' => true])
            ->get('/dashboard/billing')
            ->assertOk();

        // With a plan active, the same visit connects the desktop instead.
        $organization = $user->memberships()->firstOrFail()->organization;
        Subscription::factory()->create([
            'organization_id' => $organization->getKey(),
            'plan_id' => Plan::query()->where('slug', 'pro')->sole()->getKey(),
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->withSession(['desktop_auth' => true])
            ->get('/dashboard/billing')
            ->assertRedirect(route('desktop.authorize'));

        // The flag is consumed: an ordinary later visit is not redirected.
        $this->actingAs($user)->get('/dashboard/billing')->assertOk();
    }

    public function test_an_ordinary_visit_to_billing_is_never_redirected(): void
    {
        [$user] = $this->member();
        Plan::factory()->create(['slug' => 'pro', 'name' => 'Pro', 'is_active' => true, 'is_public' => true]);
        $organization = $user->memberships()->firstOrFail()->organization;
        Subscription::factory()->create([
            'organization_id' => $organization->getKey(),
            'plan_id' => Plan::query()->where('slug', 'pro')->sole()->getKey(),
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)->get('/dashboard/billing')->assertOk();
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function member(): array
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

        return [$user, $organization];
    }
}
