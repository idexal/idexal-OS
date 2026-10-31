<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Referral;
use App\Models\Setting;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_configure_the_referral_programme(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/referrals')
            ->assertOk();

        $this->actingAs($admin)
            ->patch('/admin/referrals', [
                'referral_enabled' => '1',
                'referral_reward_amount' => '12.5',
                'referral_reward_currency' => 'usd',
                'referral_referee_bonus' => '5',
                'referral_auto_reward' => '1',
                'referral_cookie_days' => '45',
            ])
            ->assertRedirect('/admin/referrals');

        $this->assertSame(12.5, (float) Setting::get('referral_reward_amount'));
        $this->assertSame('USD', Setting::get('referral_reward_currency'));
        $this->assertSame(5.0, (float) Setting::get('referral_referee_bonus'));
        $this->assertSame(45, (int) Setting::get('referral_cookie_days'));
        $this->assertTrue(app(ReferralService::class)->enabled());
    }

    public function test_the_programme_can_be_switched_off(): void
    {
        Setting::put('referral_enabled', false, false, 'referrals');

        $referrer = User::factory()->create();
        $code = app(ReferralService::class)->codeFor($referrer);
        $referred = User::factory()->create();

        $this->assertNull(app(ReferralService::class)->attribute($referred, $code));
        $this->assertDatabaseCount('referrals', 0);

        $this->get('/r/'.$code)
            ->assertRedirect('/register')
            ->assertSessionMissing('referral_code');
    }

    public function test_a_referral_link_is_remembered_and_offered_at_registration(): void
    {
        $referrer = User::factory()->create();
        $code = app(ReferralService::class)->codeFor($referrer);

        $this->get('/r/'.$code)
            ->assertRedirect('/register')
            ->assertSessionHas('referral_code', $code);

        $this->get('/register')
            ->assertOk()
            ->assertSee('name="ref"', false)
            ->assertSee('value="'.$code.'"', false);
    }

    public function test_signing_up_through_a_code_creates_a_pending_referral(): void
    {
        $referrer = User::factory()->create();
        $code = app(ReferralService::class)->codeFor($referrer);

        $this->post('/register', [
            'name' => 'Invited Friend',
            'email' => 'friend@example.com',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
            'ref' => $code,
        ])->assertRedirect('/dashboard');

        $referred = User::query()->where('email', 'friend@example.com')->firstOrFail();

        $this->assertDatabaseHas('referrals', [
            'referrer_user_id' => $referrer->getKey(),
            'referred_user_id' => $referred->getKey(),
            'status' => Referral::STATUS_PENDING,
        ]);
    }

    public function test_a_referral_is_rewarded_when_the_invited_workspace_pays_an_invoice(): void
    {
        $referrer = User::factory()->create();
        $referrerWallet = $this->wallet($referrer);

        Setting::put('referral_reward_amount', 25, false, 'referrals');
        Setting::put('referral_reward_currency', 'USD', false, 'referrals');

        [$referred, $organization] = $this->workspace();

        $referral = app(ReferralService::class)->attribute(
            $referred,
            app(ReferralService::class)->codeFor($referrer),
        );

        $this->assertInstanceOf(Referral::class, $referral);
        $this->assertSame(Referral::STATUS_PENDING, $referral->status);

        $invoice = $this->invoice($organization, 40);
        app(InvoiceService::class)->markPaid($invoice);

        $referral->refresh();
        $this->assertSame(Referral::STATUS_REWARDED, $referral->status);
        $this->assertNotNull($referral->rewarded_at);
        $this->assertSame(25.0, (float) $referrerWallet->fresh()->credit_balance);

        $this->assertDatabaseHas('credit_transactions', [
            'organization_id' => $referrerWallet->getKey(),
            'reference_type' => 'referral',
            'reference_id' => $referral->getKey(),
        ]);
    }

    public function test_a_reward_is_only_credited_once(): void
    {
        $referrer = User::factory()->create();
        $referrerWallet = $this->wallet($referrer);

        Setting::put('referral_reward_amount', 25, false, 'referrals');

        [$referred] = $this->workspace();

        $service = app(ReferralService::class);
        $referral = $service->attribute($referred, $service->codeFor($referrer));

        $this->assertInstanceOf(Referral::class, $referral);

        $service->reward($referral);
        $service->reward($referral->fresh());

        $this->assertSame(25.0, (float) $referrerWallet->fresh()->credit_balance);
        $this->assertDatabaseCount('credit_transactions', 1);
    }

    public function test_a_manual_reward_is_required_when_automatic_payouts_are_off(): void
    {
        Setting::put('referral_reward_amount', 25, false, 'referrals');
        Setting::put('referral_auto_reward', false, false, 'referrals');

        $referrer = User::factory()->create();
        $referrerWallet = $this->wallet($referrer);

        [$referred, $organization] = $this->workspace();

        $referral = app(ReferralService::class)->attribute(
            $referred,
            app(ReferralService::class)->codeFor($referrer),
        );

        $this->assertInstanceOf(Referral::class, $referral);

        app(InvoiceService::class)->markPaid($this->invoice($organization, 40));

        $referral->refresh();
        $this->assertSame(Referral::STATUS_QUALIFIED, $referral->status);
        $this->assertSame(0.0, (float) $referrerWallet->fresh()->credit_balance);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch("/admin/referrals/{$referral->getKey()}", ['action' => 'reward'])
            ->assertRedirect('/admin/referrals');

        $this->assertSame(Referral::STATUS_REWARDED, $referral->fresh()->status);
        $this->assertSame(25.0, (float) $referrerWallet->fresh()->credit_balance);
    }

    public function test_an_admin_can_reject_a_referral(): void
    {
        $referrer = User::factory()->create();
        $referrerWallet = $this->wallet($referrer);

        Setting::put('referral_reward_amount', 25, false, 'referrals');

        [$referred] = $this->workspace();

        $referral = app(ReferralService::class)->attribute(
            $referred,
            app(ReferralService::class)->codeFor($referrer),
        );

        $this->assertInstanceOf(Referral::class, $referral);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch("/admin/referrals/{$referral->getKey()}", [
                'action' => 'reject',
                'reason' => 'Duplicate account',
            ])
            ->assertRedirect('/admin/referrals');

        $referral->refresh();
        $this->assertSame(Referral::STATUS_REJECTED, $referral->status);
        $this->assertSame(0.0, (float) $referrerWallet->fresh()->credit_balance);
    }

    public function test_a_user_cannot_refer_themselves(): void
    {
        $user = User::factory()->create();
        $code = app(ReferralService::class)->codeFor($user);

        $this->assertNull(app(ReferralService::class)->attribute($user, $code));
        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_the_dashboard_referral_page_renders_the_invite_link(): void
    {
        [$user] = $this->workspace();

        $code = app(ReferralService::class)->codeFor($user);

        $this->actingAs($user)
            ->get('/dashboard/referrals')
            ->assertOk()
            ->assertSee(__('ui.referrals.title'))
            ->assertSee($code);
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

    private function wallet(User $user): Organization
    {
        return Organization::factory()->create([
            'owner_id' => $user->getKey(),
            'type' => Organization::TYPE_PERSONAL,
            'credit_balance' => 0,
        ]);
    }

    private function invoice(Organization $organization, float $total): Invoice
    {
        return Invoice::factory()->create([
            'organization_id' => $organization->getKey(),
            'subtotal' => $total,
            'total' => $total,
        ]);
    }
}
