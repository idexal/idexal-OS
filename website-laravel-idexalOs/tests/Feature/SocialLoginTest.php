<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SocialLoginService;
use App\Support\SettingsChanges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_enable_a_social_provider(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/social-login')
            ->assertOk();

        $this->actingAs($admin)
            ->patch('/admin/social-login', [
                'social_google_enabled' => '1',
                'social_google_client_id' => 'google-client',
                'social_google_secret' => 'google-secret',
            ])
            ->assertRedirect('/admin/social-login');

        $this->assertSame('google-client', Setting::get('social_google_client_id'));

        $secret = Setting::query()->whereKey('social_google_secret')->firstOrFail();
        $this->assertTrue($secret->encrypted);
        $this->assertNotSame('google-secret', $secret->value);

        $this->assertTrue(app(SocialLoginService::class)->enabled('google'));
    }

    public function test_enabling_a_social_provider_is_recorded_without_its_secret(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch('/admin/social-login', [
                'social_google_enabled' => '1',
                'social_google_client_id' => 'google-client',
                'social_google_secret' => 'google-secret',
            ])
            ->assertRedirect('/admin/social-login');

        $entry = AuditLog::query()->where('action', 'admin.social_login.updated')->sole();
        $settings = $entry->meta['settings'];

        $this->assertSame(['from' => null, 'to' => true], $settings['social_google_enabled']);
        $this->assertSame(['from' => null, 'to' => 'google-client'], $settings['social_google_client_id']);
        $this->assertSame(['from' => null, 'to' => SettingsChanges::SECRET_MARKER], $settings['social_google_secret']);
        $this->assertStringNotContainsString('google-secret', (string) json_encode($entry->meta));
    }

    public function test_turning_the_last_provider_off_is_described_without_english(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch('/admin/social-login', [
            'social_google_enabled' => '1',
            'social_google_client_id' => 'google-client',
            'social_google_secret' => 'google-secret',
        ])->assertRedirect('/admin/social-login');

        $this->actingAs($admin)->patch('/admin/social-login', [
            'social_google_enabled' => '0',
        ])->assertRedirect('/admin/social-login');

        $this->assertSame([], app(SocialLoginService::class)->enabledProviders());

        $latest = AuditLog::query()->where('action', 'admin.social_login.updated')->orderByDesc('id')->firstOrFail();

        $this->assertSame(__('ui.admin.social_login.none_enabled'), $latest->description);
    }

    public function test_a_provider_cannot_be_enabled_without_its_credentials(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch('/admin/social-login', [
                'social_google_enabled' => '1',
                'social_google_client_id' => '',
            ])
            ->assertSessionHasErrors('social_google_client_id');

        $this->assertFalse((bool) Setting::get('social_google_enabled'));
        $this->assertFalse(app(SocialLoginService::class)->enabled('google'));
    }

    public function test_a_disabled_provider_is_not_offered_and_its_route_is_missing(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('Continue with Google');

        $this->get('/auth/google/redirect')->assertNotFound();
    }

    public function test_an_enabled_provider_shows_a_button_and_redirects_to_the_provider(): void
    {
        $this->enableProvider('google');

        $this->get('/login')->assertOk()->assertSee('Continue with Google');

        Socialite::fake('google', $this->socialUser());

        $this->get('/auth/google/redirect')
            ->assertRedirect('https://socialite.fake/google/authorize');
    }

    public function test_a_social_callback_creates_a_user_with_a_workspace(): void
    {
        $this->enableProvider('google');

        Socialite::fake('google', $this->socialUser([
            'id' => 'google-1',
            'email' => 'newcomer@example.com',
        ]));

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'newcomer@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);

        $this->assertDatabaseHas('organizations', [
            'owner_id' => $user->getKey(),
            'type' => Organization::TYPE_PERSONAL,
        ]);

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->getKey(),
            'provider' => 'google',
            'provider_user_id' => 'google-1',
        ]);
    }

    public function test_a_social_callback_links_to_an_existing_user_by_email(): void
    {
        $existing = User::factory()->create(['email' => 'member@example.com']);

        $this->enableProvider('google');

        Socialite::fake('google', $this->socialUser([
            'id' => 'google-2',
            'email' => 'member@example.com',
        ]));

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::query()->count());

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $existing->getKey(),
            'provider' => 'google',
            'provider_user_id' => 'google-2',
        ]);
    }

    public function test_a_returning_social_user_does_not_get_a_duplicate_account(): void
    {
        $user = User::factory()->create(['email' => 'returning@example.com']);

        SocialAccount::query()->create([
            'user_id' => $user->getKey(),
            'provider' => 'google',
            'provider_user_id' => 'google-3',
            'email' => 'returning@example.com',
            'name' => 'Returning',
        ]);

        $this->enableProvider('google');

        Socialite::fake('google', $this->socialUser([
            'id' => 'google-3',
            'email' => 'returning@example.com',
            'name' => 'Returning Again',
        ]));

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, SocialAccount::query()->count());
        $this->assertSame('Returning Again', $user->socialAccounts()->firstOrFail()->name);
    }

    public function test_a_suspended_user_cannot_sign_in_with_a_social_account(): void
    {
        User::factory()->suspended()->create(['email' => 'blocked@example.com']);

        $this->enableProvider('google');

        Socialite::fake('google', $this->socialUser([
            'id' => 'google-4',
            'email' => 'blocked@example.com',
        ]));

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_social_sign_in_is_refused_when_registration_is_disabled(): void
    {
        Setting::put('registration_enabled', false, false, 'general');

        $this->enableProvider('google');

        Socialite::fake('google', $this->socialUser([
            'id' => 'google-5',
            'email' => 'latecomer@example.com',
        ]));

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('social');

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_a_provider_that_returns_no_email_still_creates_an_account(): void
    {
        $this->enableProvider('pinterest');

        Socialite::fake('pinterest', $this->socialUser([
            'id' => 'pin-42',
            'name' => null,
            'nickname' => 'pinner',
            'email' => null,
        ]));

        $this->get('/auth/pinterest/callback')->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'pinterest-pin-42@social.invalid')->firstOrFail();

        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->getKey(),
            'provider' => 'pinterest',
            'provider_user_id' => 'pin-42',
        ]);
    }

    private function enableProvider(string $key): void
    {
        Setting::put('social_'.$key.'_enabled', true, false, 'social');
        Setting::put('social_'.$key.'_client_id', $key.'-client', false, 'social');
        Setting::put('social_'.$key.'_secret', $key.'-secret', true, 'social');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function socialUser(array $attributes = []): SocialiteUser
    {
        return SocialiteUser::fake($attributes);
    }
}
