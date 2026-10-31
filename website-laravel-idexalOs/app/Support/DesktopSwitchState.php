<?php

namespace App\Support;

use App\Services\SettingsService;

/**
 * The platform-wide state of the desktop switches, and the diff between two
 * such states.
 *
 * These switches do not pass through the gateway, so they have no provider or
 * model overrides: one platform row is the whole truth. The audit entry still
 * has to name what moved, because an operator can flip several in one save.
 */
final class DesktopSwitchState
{
    /**
     * The current platform toggle for every switch, in catalogue order.
     *
     * @return array<string, bool>
     */
    public static function toggles(SettingsService $settings): array
    {
        $toggles = [];

        foreach (DesktopSwitch::cases() as $switch) {
            $toggles[$switch->value] = $settings->boolean($switch->settingKey());
        }

        return $toggles;
    }

    /**
     * Only the switches whose platform state actually moved.
     *
     * @param  array<string, bool>  $before
     * @param  array<string, bool>  $after
     * @return array<string, array{from: bool, to: bool}>
     */
    public static function between(array $before, array $after): array
    {
        $changes = [];

        foreach (DesktopSwitch::cases() as $switch) {
            $was = $before[$switch->value] ?? false;
            $now = $after[$switch->value] ?? false;

            if ($was !== $now) {
                $changes[$switch->value] = ['from' => $was, 'to' => $now];
            }
        }

        return $changes;
    }

    /**
     * A one-line summary of the same diff for the audit description. Null when
     * nothing moved.
     *
     * @param  array<string, array{from: bool, to: bool}>  $changes
     */
    public static function describe(array $changes): ?string
    {
        if ($changes === []) {
            return null;
        }

        $parts = [];

        foreach ($changes as $key => $change) {
            $switch = DesktopSwitch::tryFrom((string) $key);
            $label = $switch instanceof DesktopSwitch ? __($switch->labelKey()) : (string) $key;

            $parts[] = $label.': '.($change['to']
                ? __('ui.admin.providers.feature_on')
                : __('ui.admin.providers.feature_off'));
        }

        return implode(', ', $parts);
    }
}
