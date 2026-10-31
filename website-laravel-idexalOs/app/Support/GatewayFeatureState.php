<?php

namespace App\Support;

use App\Services\SettingsService;

/**
 * The platform-wide state of the gateway feature catalogue, and the diff between
 * two such states.
 *
 * The Features panel is the one screen where a single save can flip several
 * billed or privacy-relevant capabilities at once (Web Search charges a fee, the
 * PII Sanitizer rewrites prompts), so the audit entry has to name the features
 * that moved instead of saying "features updated".
 */
final class GatewayFeatureState
{
    /**
     * The current platform toggle for every feature, in catalogue order.
     *
     * @return array<string, bool>
     */
    public static function toggles(SettingsService $settings): array
    {
        $toggles = [];

        foreach (GatewayFeature::cases() as $feature) {
            $toggles[$feature->value] = $settings->boolean($feature->settingKey());
        }

        return $toggles;
    }

    /**
     * Only the features whose platform state actually moved; an unchanged
     * feature stays out of the audit entry.
     *
     * @param  array<string, bool>  $before
     * @param  array<string, bool>  $after
     * @return array<string, array{from: bool, to: bool}>
     */
    public static function between(array $before, array $after): array
    {
        $changes = [];

        foreach (GatewayFeature::cases() as $feature) {
            $was = $before[$feature->value] ?? false;
            $now = $after[$feature->value] ?? false;

            if ($was !== $now) {
                $changes[$feature->value] = ['from' => $was, 'to' => $now];
            }
        }

        return $changes;
    }

    /**
     * A one-line summary of the same diff for the audit description, using the
     * operator-facing feature names. Null when nothing moved.
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
            $feature = GatewayFeature::tryFrom($key);
            $label = $feature instanceof GatewayFeature ? __($feature->labelKey()) : $key;

            $parts[] = $label.': '.($change['to']
                ? __('ui.admin.providers.feature_on')
                : __('ui.admin.providers.feature_off'));
        }

        return implode(', ', $parts);
    }

    /**
     * The per-provider or per-model pin diff. A missing key means "inherit", so
     * re-pinning a feature to what it already inherited is not a change.
     *
     * @param  array<string, bool>|null  $before
     * @param  array<string, bool>|null  $after
     * @return array<string, array{from: string, to: string}>
     */
    public static function overrideChanges(?array $before, ?array $after): array
    {
        $changes = [];

        foreach (GatewayFeature::cases() as $feature) {
            $was = self::overrideState($before, $feature->value);
            $now = self::overrideState($after, $feature->value);

            if ($was !== $now) {
                $changes[$feature->value] = ['from' => $was, 'to' => $now];
            }
        }

        return $changes;
    }

    /**
     * A one-line summary of an override diff for the audit description, naming
     * both sides so "inherit -> off" reads as a deliberate pin. Null when
     * nothing moved.
     *
     * @param  array<string, array{from: string, to: string}>  $changes
     */
    public static function describeOverrides(array $changes): ?string
    {
        if ($changes === []) {
            return null;
        }

        $parts = [];

        foreach ($changes as $key => $change) {
            $feature = GatewayFeature::tryFrom($key);
            $label = $feature instanceof GatewayFeature ? __($feature->labelKey()) : $key;

            $parts[] = $label.': '.self::overrideLabel($change['from']).' -> '.self::overrideLabel($change['to']);
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array<string, bool>|null  $overrides
     */
    private static function overrideState(?array $overrides, string $feature): string
    {
        if (! is_array($overrides) || ! array_key_exists($feature, $overrides)) {
            return 'inherit';
        }

        return $overrides[$feature] ? 'on' : 'off';
    }

    private static function overrideLabel(string $state): string
    {
        return match ($state) {
            'on' => __('ui.admin.providers.feature_on'),
            'off' => __('ui.admin.providers.feature_off'),
            default => __('ui.admin.providers.feature_inherit'),
        };
    }
}
