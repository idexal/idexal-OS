<?php

namespace App\Services;

use App\Exceptions\SocialLoginDeniedException;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\SocialProviders;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;

/**
 * Everything the platform knows about signing in with a social provider:
 * which providers an operator switched on, how to build a Socialite driver for
 * one, and how to turn a provider profile into a signed-in idexal Os user.
 */
class SocialLoginService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly OnboardingService $onboarding,
        private readonly ReferralService $referrals,
    ) {}

    /**
     * @return array<string, array{label: string, driver: string, scopes: list<string>}>
     */
    public function providers(): array
    {
        return SocialProviders::all();
    }

    /**
     * A provider is usable only when an operator switched it on and supplied
     * both credentials; otherwise its button is hidden and its routes 404.
     */
    public function enabled(string $key): bool
    {
        if (SocialProviders::find($key) === null || ! $this->booleanSetting($key)) {
            return false;
        }

        return $this->clientId($key) !== '' && $this->clientSecret($key) !== '';
    }

    /**
     * The raw on/off switch, independent of whether credentials are complete.
     * The admin checkbox reflects this; the login buttons reflect {@see enabled()}.
     */
    public function booleanSetting(string $key): bool
    {
        return $this->settings->boolean($this->enabledKey($key));
    }

    /**
     * @return array<string, array{label: string, driver: string, scopes: list<string>}>
     */
    public function enabledProviders(): array
    {
        return array_filter(
            SocialProviders::all(),
            fn (array $provider, string $key): bool => $this->enabled($key),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function clientId(string $key): string
    {
        return trim((string) $this->settings->get($this->clientIdKey($key)));
    }

    public function clientSecret(string $key): string
    {
        return trim((string) $this->settings->get($this->secretKey($key)));
    }

    public function enabledKey(string $key): string
    {
        return 'social_'.$key.'_enabled';
    }

    public function clientIdKey(string $key): string
    {
        return 'social_'.$key.'_client_id';
    }

    public function secretKey(string $key): string
    {
        return 'social_'.$key.'_secret';
    }

    public function redirectUrl(string $key): string
    {
        return route('social.callback', $key);
    }

    /**
     * A ready Socialite driver for an enabled provider. The credentials come
     * from the database, so nothing has to live in config/services.php.
     */
    public function driver(string $key): SocialiteProvider
    {
        $provider = SocialProviders::find($key);

        if ($provider === null || ! $this->enabled($key)) {
            throw new RuntimeException('Social provider is not available.');
        }

        Config::set('services.'.$provider['driver'], [
            'client_id' => $this->clientId($key),
            'client_secret' => $this->clientSecret($key),
            'redirect' => $this->redirectUrl($key),
        ]);

        $driver = Socialite::driver($provider['driver']);

        if ($provider['scopes'] !== []) {
            $driver->scopes($provider['scopes']);
        }

        return $driver;
    }

    /**
     * Find or create the idexal Os user behind a provider profile and keep the
     * stored social account in step with the profile.
     *
     * @throws SocialLoginDeniedException when a new account would be created but registration is off
     */
    public function resolveUser(string $key, SocialiteUser $socialUser, ?string $referralCode = null): User
    {
        return DB::transaction(function () use ($key, $socialUser, $referralCode): User {
            $existing = SocialAccount::query()
                ->where('provider', $key)
                ->where('provider_user_id', (string) $socialUser->getId())
                ->first();

            if ($existing?->user instanceof User) {
                $user = $existing->user;
                $this->syncAccount($existing, $socialUser);

                return $user;
            }

            $user = $this->matchingUser($socialUser);
            $created = false;

            if (! $user instanceof User) {
                if (! $this->settings->boolean('registration_enabled', true)) {
                    throw new SocialLoginDeniedException(__('ui.errors.not_allowed'));
                }

                $user = $this->createUser($key, $socialUser);
                $created = true;
            }

            $user->socialAccounts()->updateOrCreate(
                ['provider' => $key, 'provider_user_id' => (string) $socialUser->getId()],
                [
                    'email' => $socialUser->getEmail(),
                    'name' => $socialUser->getName() ?? $socialUser->getNickname(),
                    'avatar_url' => $socialUser->getAvatar(),
                ],
            );

            if ($created) {
                $this->referrals->attribute($user, $referralCode);
            }

            return $user;
        });
    }

    /**
     * An existing account with the same verified email. Soft-deleted users are
     * included so the caller can refuse them instead of tripping the unique
     * email index while creating a duplicate.
     */
    private function matchingUser(SocialiteUser $socialUser): ?User
    {
        $email = $socialUser->getEmail();

        if (! is_string($email) || trim($email) === '') {
            return null;
        }

        return User::withTrashed()->where('email', trim($email))->first();
    }

    private function createUser(string $key, SocialiteUser $socialUser): User
    {
        $email = $this->emailFor($key, $socialUser);

        $user = User::query()->create([
            'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: Str::before($email, '@'),
            'email' => $email,
            'password' => Str::random(40),
            'role' => User::ROLE_USER,
            'status' => User::STATUS_ACTIVE,
            'locale' => app()->getLocale(),
            'timezone' => 'UTC',
            'email_verified_at' => now(),
        ]);

        $this->onboarding->createPersonalOrganization($user);

        return $user;
    }

    /**
     * Some providers (Pinterest, and X without email access) never return an
     * email. A unique, non-routable placeholder keeps the account usable; the
     * person signs in with the provider, not with a password.
     */
    private function emailFor(string $key, SocialiteUser $socialUser): string
    {
        $email = $socialUser->getEmail();

        if (is_string($email) && trim($email) !== '') {
            return trim($email);
        }

        return $key.'-'.$socialUser->getId().'@social.invalid';
    }

    private function syncAccount(SocialAccount $account, SocialiteUser $socialUser): void
    {
        $account->forceFill([
            'email' => $socialUser->getEmail() ?? $account->email,
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? $account->name,
            'avatar_url' => $socialUser->getAvatar() ?? $account->avatar_url,
        ])->save();
    }
}
