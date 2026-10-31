<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\SocialProviders;

class SettingsService
{
    /**
     * @var array<string, mixed>
     */
    private array $defaults;

    public function __construct()
    {
        $this->defaults = [
            'site_name' => config('idexal.brand.name'),
            'site_tagline' => config('idexal.brand.tagline'),
            'support_email' => config('idexal.brand.support_email'),
            'registration_enabled' => (bool) config('idexal.registration.enabled'),
            'require_email_verification' => (bool) config('idexal.registration.require_email_verification'),
            'gateway_enabled' => (bool) config('idexal.gateway.enabled'),
            'proxy_enabled' => true,
            'browser_prewarm_enabled' => true,
            // Gateway feature pipeline. Every toggle is off until an operator
            // turns it on in /admin/features, so an unchanged install forwards
            // requests exactly as before.
            'feature_web_search_enabled' => false,
            'feature_web_search_price' => 0.015,
            'feature_vision_enabled' => false,
            'feature_vision_model_id' => '',
            'feature_param_sanitizer_enabled' => false,
            'feature_model_substitute_enabled' => false,
            'feature_default_model_id' => '',
            'feature_pii_sanitizer_enabled' => false,
            'feature_patient_retry_enabled' => false,
            'feature_patient_retry_attempts' => 6,
            'feature_patient_retry_budget' => 60,
            'feature_json_schema_enabled' => false,
            'feature_repetition_guard_enabled' => false,
            'billing_enabled' => true,
            'default_currency' => config('idexal.billing.currency'),
            'tax_rate' => (float) config('idexal.billing.tax_rate'),
            'invoice_due_days' => 14,
            'payment_instructions' => '',
            'desktop_latest_version' => '0.0.0',
            'desktop_min_version' => '0.0.0',
            'free_auto_enabled' => true,
            'free_auto_weekly_usd' => 5.0,
            'free_auto_model_id' => '',
            'maintenance_message' => '',
            'stripe_key' => config('idexal.stripe.key'),
            'stripe_secret' => config('idexal.stripe.secret'),
            'stripe_webhook_secret' => config('idexal.stripe.webhook_secret'),
            'stripe_enabled' => true,
            'paypal_enabled' => false,
            'paypal_mode' => 'sandbox',
            'paypal_client_id' => '',
            'paypal_secret' => '',
            'paypal_webhook_id' => '',
            'bank_transfer_enabled' => false,
            'bank_transfer_bank_name' => '',
            'bank_transfer_account_name' => '',
            'bank_transfer_account_number' => '',
            'bank_transfer_iban' => '',
            'bank_transfer_swift' => '',
            'bank_transfer_routing_number' => '',
            'bank_transfer_instructions' => '',
            'referral_enabled' => true,
            'referral_reward_amount' => 0.0,
            'referral_reward_currency' => config('idexal.billing.currency'),
            'referral_referee_bonus' => 0.0,
            'referral_auto_reward' => true,
            'referral_cookie_days' => 30,
            'support_enabled' => true,
            'support_auto_close_days' => 14,
            'support_ai_enabled' => false,
            'support_ai_auto_reply' => false,
            'support_ai_provider_id' => '',
            'support_ai_model_id' => '',
            'support_ai_instructions' => '',
            'support_ai_timeout' => 60,
        ];

        foreach (SocialProviders::keys() as $provider) {
            $this->defaults['social_'.$provider.'_enabled'] = false;
            $this->defaults['social_'.$provider.'_client_id'] = '';
            $this->defaults['social_'.$provider.'_secret'] = '';
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $fallback = array_key_exists($key, $this->defaults) ? $this->defaults[$key] : $default;

        return Setting::get($key, $fallback);
    }

    public function boolean(string $key, bool $default = false): bool
    {
        return filter_var($this->get($key, $default), FILTER_VALIDATE_BOOL);
    }

    public function integer(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values, string $group = 'general'): void
    {
        $secretKeys = [
            'stripe_key',
            'stripe_secret',
            'stripe_webhook_secret',
            'paypal_secret',
            ...SocialProviders::secretKeys(),
        ];

        foreach ($values as $key => $value) {
            Setting::put(
                $key,
                $value,
                in_array($key, $secretKeys, true),
                $group,
            );
        }
    }

    /**
     * @return array{key: ?string, secret: ?string, webhook_secret: ?string}
     */
    public function stripeCredentials(): array
    {
        return [
            'key' => $this->nullableString($this->get('stripe_key')),
            'secret' => $this->nullableString($this->get('stripe_secret')),
            'webhook_secret' => $this->nullableString($this->get('stripe_webhook_secret')),
        ];
    }

    public function stripeEnabled(): bool
    {
        $credentials = $this->stripeCredentials();

        return $this->boolean('stripe_enabled', true)
            && $credentials['key'] !== null
            && $credentials['secret'] !== null;
    }

    /**
     * PayPal REST credentials. `base` is the API origin for the configured mode,
     * so no part of the code base has to know sandbox from live.
     *
     * @return array{client_id: ?string, secret: ?string, mode: string, webhook_id: ?string, base: string}
     */
    public function paypalCredentials(): array
    {
        $mode = $this->get('paypal_mode') === 'live' ? 'live' : 'sandbox';

        return [
            'client_id' => $this->nullableString($this->get('paypal_client_id')),
            'secret' => $this->nullableString($this->get('paypal_secret')),
            'mode' => $mode,
            'webhook_id' => $this->nullableString($this->get('paypal_webhook_id')),
            'base' => $mode === 'live'
                ? 'https://api-m.paypal.com'
                : 'https://api-m.sandbox.paypal.com',
        ];
    }

    public function paypalEnabled(): bool
    {
        $credentials = $this->paypalCredentials();

        return $this->boolean('paypal_enabled')
            && $credentials['client_id'] !== null
            && $credentials['secret'] !== null;
    }

    /**
     * The bank account customers transfer into. Blank fields are dropped so the
     * invoice page only ever prints details an operator actually filled in.
     *
     * @return array<string, string>
     */
    public function bankTransferDetails(): array
    {
        $fields = [
            'bank_name' => $this->get('bank_transfer_bank_name'),
            'account_name' => $this->get('bank_transfer_account_name'),
            'account_number' => $this->get('bank_transfer_account_number'),
            'iban' => $this->get('bank_transfer_iban'),
            'swift' => $this->get('bank_transfer_swift'),
            'routing_number' => $this->get('bank_transfer_routing_number'),
            'instructions' => $this->get('bank_transfer_instructions'),
        ];

        return array_filter(
            array_map(
                fn (mixed $value): string => trim((string) $value),
                $fields,
            ),
            fn (string $value): bool => $value !== '',
        );
    }

    public function bankTransferEnabled(): bool
    {
        if (! $this->boolean('bank_transfer_enabled')) {
            return false;
        }

        $details = $this->bankTransferDetails();

        return $details !== [] && (
            isset($details['iban'])
            || isset($details['account_number'])
        );
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
