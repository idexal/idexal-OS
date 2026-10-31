<?php

namespace App\Support;

use App\Services\SettingsService;

/**
 * The one feature map this platform publishes.
 *
 * A desktop hides a feature whose key is missing, and it reads the map from two
 * endpoints: GET /v1/features before sign-in and GET /v1/org afterwards. When
 * those two disagreed (the organization map used to answer `library: false`
 * while the deployment map said `true`), a signed-in desktop lost the feature
 * it had a moment earlier. Both endpoints now build their answer here, so they
 * cannot drift apart again.
 */
final class PlatformFeatures
{
    /**
     * Every switch, in the order the desktop reads them: what the platform
     * serves, then the operator-owned toggles.
     *
     * @return array<string, bool>
     */
    public static function map(SettingsService $settings): array
    {
        return [
            'cloud' => true,
            'connect' => false,
            'desktopCloud' => true,
            'automations' => true,
            'dashboard' => true,
            'library' => true,
            'mcpApps' => true,
            'teamWorkspaces' => true,
            'gateway' => $settings->boolean('gateway_enabled', true),
            'proxyManager' => $settings->boolean('proxy_enabled', true),
            'browserPrewarm' => $settings->boolean('proxy_enabled', true)
                && $settings->boolean('browser_prewarm_enabled', true),
            'billing' => $settings->boolean('billing_enabled', true),
            'registration' => $settings->boolean('registration_enabled', true),
            ...DesktopSwitchState::toggles($settings),
        ];
    }
}
