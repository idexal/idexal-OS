<?php

namespace Tests\Feature;

use App\Support\AuditFieldLabel;
use App\Support\DesktopSwitch;
use App\Support\GatewayFeature;
use App\Support\SocialProviders;
use Tests\TestCase;

/**
 * The audit page's before/after table used to print stored keys as they are.
 * Every key a screen actually writes should read as the field that screen shows,
 * and an unknown key must fall back to itself rather than to an empty cell.
 */
class AuditFieldLabelTest extends TestCase
{
    public function test_an_unknown_key_has_no_label_so_the_caller_shows_the_key(): void
    {
        $this->assertNull(AuditFieldLabel::for('some_future_setting'));
        $this->assertNull(AuditFieldLabel::for('feature_unknown_thing_enabled'));
    }

    public function test_a_platform_setting_reuses_the_label_its_form_shows(): void
    {
        $this->assertSame(__('ui.admin.settings.site_name'), AuditFieldLabel::for('site_name'));
        $this->assertSame(__('ui.admin.settings.currency'), AuditFieldLabel::for('default_currency'));
    }

    public function test_a_gateway_field_is_named_after_its_gateway(): void
    {
        app()->setLocale('en');

        $this->assertSame('PayPal client ID', AuditFieldLabel::for('paypal_client_id'));
        $this->assertSame('Bank transfer enabled', AuditFieldLabel::for('bank_transfer_enabled'));
        $this->assertSame('Stripe enabled', AuditFieldLabel::for('stripe_enabled'));
    }

    public function test_a_feature_key_uses_the_feature_name_from_the_features_page(): void
    {
        $this->assertSame(__('ui.admin.features.pii_sanitizer_name'), AuditFieldLabel::for('pii_sanitizer'));
    }

    public function test_a_feature_toggle_names_the_feature_it_switches(): void
    {
        app()->setLocale('en');

        $this->assertSame('Web Search enabled', AuditFieldLabel::for('feature_web_search_enabled'));
        $this->assertSame('Repetition Guard enabled', AuditFieldLabel::for('feature_repetition_guard_enabled'));
    }

    public function test_a_social_key_names_its_provider(): void
    {
        app()->setLocale('en');

        $this->assertSame('Google client secret', AuditFieldLabel::for('social_google_secret'));
        $this->assertSame('LinkedIn sign-in enabled', AuditFieldLabel::for('social_linkedin_enabled'));
    }

    public function test_a_social_key_for_a_provider_that_no_longer_exists_is_left_raw(): void
    {
        $this->assertNull(AuditFieldLabel::for('social_myspace_enabled'));
    }

    /**
     * The list is the union of what the five settings screens write. A screen that
     * starts saving a new key without a label fails here, not in production.
     */
    public function test_every_key_the_settings_screens_write_has_a_label(): void
    {
        foreach ($this->writtenKeys() as $key) {
            $label = AuditFieldLabel::for($key);

            $this->assertNotNull($label, "the audit table has no name for [{$key}]");
            $this->assertNotSame($key, $label, "[{$key}] is still shown as a machine key");
        }
    }

    /**
     * An operator reading the Arabic panel must not meet English prose. Names of
     * companies and banking acronyms are the same in both languages.
     */
    public function test_arabic_labels_are_written_in_arabic(): void
    {
        app()->setLocale('ar');

        $sameInBothLanguages = ['bank_transfer_iban', 'bank_transfer_swift'];

        foreach (array_diff($this->writtenKeys(), $sameInBothLanguages) as $key) {
            $this->assertMatchesRegularExpression(
                '/[\x{0600}-\x{06FF}]/u',
                (string) AuditFieldLabel::for($key),
                "[{$key}] has no Arabic label",
            );
        }
    }

    /**
     * Every settings key a screen writes today.
     *
     * @return list<string>
     */
    private function writtenKeys(): array
    {
        $keys = [
            // Platform settings.
            'site_name',
            'site_tagline',
            'support_email',
            'registration_enabled',
            'require_email_verification',
            'gateway_enabled',
            'proxy_enabled',
            'browser_prewarm_enabled',
            'billing_enabled',
            'default_currency',
            'tax_rate',
            'invoice_due_days',
            'payment_instructions',
            'desktop_latest_version',
            'desktop_min_version',
            'maintenance_message',
            'stripe_key',
            'stripe_secret',
            'stripe_webhook_secret',

            // Payment gateways.
            'stripe_enabled',
            'paypal_enabled',
            'paypal_mode',
            'paypal_client_id',
            'paypal_secret',
            'paypal_webhook_id',
            'bank_transfer_enabled',
            'bank_transfer_bank_name',
            'bank_transfer_account_name',
            'bank_transfer_account_number',
            'bank_transfer_iban',
            'bank_transfer_swift',
            'bank_transfer_routing_number',
            'bank_transfer_instructions',

            // Referral programme.
            'referral_enabled',
            'referral_reward_amount',
            'referral_reward_currency',
            'referral_referee_bonus',
            'referral_auto_reward',
            'referral_cookie_days',

            // Support desk.
            'support_enabled',
            'support_auto_close_days',
            'support_ai_enabled',
            'support_ai_provider_id',
            'support_ai_model_id',
            'support_ai_instructions',
            'support_ai_auto_reply',

            // Features page values.
            'feature_web_search_price',
            'feature_vision_model_id',
            'feature_default_model_id',
            'feature_patient_retry_attempts',
            'feature_patient_retry_budget',
        ];

        foreach (GatewayFeature::cases() as $feature) {
            $keys[] = $feature->settingKey();
        }

        foreach (DesktopSwitch::cases() as $switch) {
            $keys[] = $switch->settingKey();
        }

        foreach (SocialProviders::keys() as $provider) {
            $keys[] = 'social_'.$provider.'_enabled';
            $keys[] = 'social_'.$provider.'_client_id';
            $keys[] = 'social_'.$provider.'_secret';
        }

        return $keys;
    }
}
