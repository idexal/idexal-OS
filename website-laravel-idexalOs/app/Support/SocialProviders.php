<?php

namespace App\Support;

/**
 * The social providers an operator can switch on from the admin panel.
 *
 * `key` is what idexal Os stores in settings and in `social_accounts`; `driver`
 * is the name Laravel Socialite is asked for. The two differ for providers whose
 * display name is not a valid driver (for example X and LinkedIn OpenID).
 */
final class SocialProviders
{
    /**
     * @return array<string, array{label: string, driver: string, scopes: list<string>}>
     */
    public static function all(): array
    {
        return [
            'google' => ['label' => 'Google', 'driver' => 'google', 'scopes' => []],
            'facebook' => ['label' => 'Facebook', 'driver' => 'facebook', 'scopes' => []],
            'x' => ['label' => 'X', 'driver' => 'x', 'scopes' => []],
            'linkedin' => ['label' => 'LinkedIn', 'driver' => 'linkedin-openid', 'scopes' => []],
            'github' => ['label' => 'GitHub', 'driver' => 'github', 'scopes' => []],
            'gitlab' => ['label' => 'GitLab', 'driver' => 'gitlab', 'scopes' => []],
            'bitbucket' => ['label' => 'Bitbucket', 'driver' => 'bitbucket', 'scopes' => []],
            'pinterest' => ['label' => 'Pinterest', 'driver' => 'pinterest', 'scopes' => []],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array{label: string, driver: string, scopes: list<string>}|null
     */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * The settings keys that hold provider secrets. Used by the settings service
     * to encrypt every secret at rest.
     *
     * @return list<string>
     */
    public static function secretKeys(): array
    {
        return array_map(
            fn (string $key): string => 'social_'.$key.'_secret',
            self::keys(),
        );
    }
}
