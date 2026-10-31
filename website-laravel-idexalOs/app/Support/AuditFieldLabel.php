<?php

namespace App\Support;

/**
 * A human name for a settings key, so the audit page's before/after table does
 * not read as snake_case machine keys.
 *
 * Where a screen already names the field unambiguously, that label is reused, so
 * the log and the form that produced the change say the same thing. Keys whose
 * screen label is too generic to stand alone in a mixed table (four gateways all
 * saying "Enabled") get an entry of their own under `ui.admin.audit.fields`.
 */
final class AuditFieldLabel
{
    /**
     * Setting key => the language key the owning form already shows.
     *
     * Every key here is one a screen actually writes; the label is the one that
     * screen prints above the input.
     */
    private const REUSED = [
        // Platform settings.
        'site_name' => 'ui.admin.settings.site_name',
        'site_tagline' => 'ui.admin.settings.site_tagline',
        'support_email' => 'ui.admin.settings.support_email',
        'registration_enabled' => 'ui.admin.settings.registration_enabled',
        'require_email_verification' => 'ui.admin.settings.require_email_verification',
        'gateway_enabled' => 'ui.admin.settings.gateway_enabled',
        'proxy_enabled' => 'ui.admin.settings.proxy_enabled',
        'browser_prewarm_enabled' => 'ui.admin.settings.browser_prewarm_enabled',
        'billing_enabled' => 'ui.admin.settings.billing_enabled',
        'default_currency' => 'ui.admin.settings.currency',
        'tax_rate' => 'ui.admin.settings.tax_rate',
        'invoice_due_days' => 'ui.admin.settings.invoice_due_days',
        'payment_instructions' => 'ui.admin.settings.payment_instructions',
        'desktop_latest_version' => 'ui.admin.settings.latest_version',
        'desktop_min_version' => 'ui.admin.settings.min_version',
        'maintenance_message' => 'ui.admin.settings.maintenance_message',
        'stripe_key' => 'ui.admin.settings.stripe_key',
        'stripe_secret' => 'ui.admin.settings.stripe_secret',
        'stripe_webhook_secret' => 'ui.admin.settings.stripe_webhook_secret',

        // Referral programme.
        'referral_enabled' => 'ui.admin.referrals.enabled',
        'referral_reward_amount' => 'ui.admin.referrals.reward_amount',
        'referral_reward_currency' => 'ui.admin.referrals.reward_currency',
        'referral_referee_bonus' => 'ui.admin.referrals.referee_bonus',
        'referral_auto_reward' => 'ui.admin.referrals.auto_reward',
        'referral_cookie_days' => 'ui.admin.referrals.cookie_days',

        // Support desk.
        'support_enabled' => 'ui.admin.support.enabled',
        'support_auto_close_days' => 'ui.admin.support.auto_close_days',
        'support_ai_enabled' => 'ui.admin.support.ai_enabled',
        'support_ai_provider_id' => 'ui.admin.support.ai_provider',
        'support_ai_model_id' => 'ui.admin.support.ai_model',
        'support_ai_instructions' => 'ui.admin.support.ai_instructions',
        'support_ai_auto_reply' => 'ui.admin.support.ai_auto_reply',

        // Values set on the Features page.
        'feature_web_search_price' => 'ui.admin.features.price_label',
        'feature_vision_model_id' => 'ui.admin.features.vision_model_label',
        'feature_default_model_id' => 'ui.admin.features.default_model_label',
        'feature_patient_retry_attempts' => 'ui.admin.features.retry_attempts',
        'feature_patient_retry_budget' => 'ui.admin.features.retry_budget',
    ];

    /**
     * Setting key => a label of its own, for keys the owning form names too
     * generically to identify on its own.
     *
     * The payment screen prints "Enabled" and "Mode" for four different
     * gateways, so those rows would be indistinguishable in one audit table.
     */
    private const OWN = [
        'stripe_enabled' => 'ui.admin.audit.fields.stripe_enabled',
        'paypal_enabled' => 'ui.admin.audit.fields.paypal_enabled',
        'paypal_mode' => 'ui.admin.audit.fields.paypal_mode',
        'paypal_client_id' => 'ui.admin.audit.fields.paypal_client_id',
        'paypal_secret' => 'ui.admin.audit.fields.paypal_secret',
        'paypal_webhook_id' => 'ui.admin.audit.fields.paypal_webhook_id',
        'bank_transfer_enabled' => 'ui.admin.audit.fields.bank_transfer_enabled',
        'bank_transfer_bank_name' => 'ui.admin.audit.fields.bank_transfer_bank_name',
        'bank_transfer_account_name' => 'ui.admin.audit.fields.bank_transfer_account_name',
        'bank_transfer_account_number' => 'ui.admin.audit.fields.bank_transfer_account_number',
        'bank_transfer_iban' => 'ui.admin.audit.fields.bank_transfer_iban',
        'bank_transfer_swift' => 'ui.admin.audit.fields.bank_transfer_swift',
        'bank_transfer_routing_number' => 'ui.admin.audit.fields.bank_transfer_routing_number',
        'bank_transfer_instructions' => 'ui.admin.audit.fields.bank_transfer_instructions',
    ];

    /**
     * The label for a stored key, or null when nothing knows it — the caller
     * falls back to showing the key itself, which is always true.
     */
    public static function for(string $key): ?string
    {
        $feature = self::feature($key);

        if ($feature !== null) {
            return $feature;
        }

        $desktop = self::desktop($key);

        if ($desktop !== null) {
            return $desktop;
        }

        $languageKey = self::OWN[$key] ?? self::REUSED[$key] ?? null;

        if ($languageKey !== null) {
            return __($languageKey);
        }

        return self::social($key) ?? self::permission($key);
    }

    /**
     * An agent-permission row is stored under the key the desktop's own
     * catalogue uses (`commands`, `fileEdits`, …). The lookup comes last so a
     * settings key that happens to share a name keeps its own label.
     */
    private static function permission(string $key): ?string
    {
        $permission = AgentPermission::fromSettingKey($key);

        return $permission instanceof AgentPermission ? __($permission->labelKey()) : null;
    }

    /**
     * A gateway feature is stored three ways: the bare key in a request log, the
     * toggle's settings row, and the record's own key in a provider or model
     * override. All three should read as the feature's published name.
     */
    private static function feature(string $key): ?string
    {
        $bare = GatewayFeature::tryFrom($key);

        if ($bare instanceof GatewayFeature) {
            return __($bare->labelKey());
        }

        if (preg_match('/^feature_([a-z_]+)_enabled$/', $key, $matches) !== 1) {
            return null;
        }

        $toggled = GatewayFeature::tryFrom($matches[1]);

        if ($toggled === null) {
            return null;
        }

        return __('ui.admin.audit.fields.feature_toggle', ['feature' => __($toggled->labelKey())]);
    }

    /**
     * A desktop switch is stored as `desktop_switch_<slug>_enabled`. The slug is
     * not the published camelCase key, so the label is composed from the
     * catalogue rather than from the raw key.
     */
    private static function desktop(string $key): ?string
    {
        $switch = DesktopSwitch::fromSettingKey($key);

        if ($switch === null) {
            return null;
        }

        return __('ui.admin.audit.fields.desktop_switch', ['switch' => __($switch->labelKey())]);
    }

    /**
     * `social_google_secret` is one of twenty-four keys (a provider × on/off,
     * client id and secret), so it is composed rather than listed. The provider
     * name comes from the same catalogue the sign-in buttons read.
     */
    private static function social(string $key): ?string
    {
        if (preg_match('/^social_([a-z]+)_(enabled|client_id|secret)$/', $key, $matches) !== 1) {
            return null;
        }

        $provider = SocialProviders::find($matches[1]);

        if ($provider === null) {
            return null;
        }

        $template = match ($matches[2]) {
            'enabled' => 'social_enabled',
            'client_id' => 'social_client_id',
            default => 'social_secret',
        };

        return __('ui.admin.audit.fields.'.$template, ['provider' => $provider['label']]);
    }
}
