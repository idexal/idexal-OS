<?php

namespace Tests\Unit;

use App\Support\SettingsChanges;
use PHPUnit\Framework\TestCase;

/**
 * The settings diff: what the admin audit log records when an operator saves a
 * settings page, and what it must never record.
 */
class SettingsChangesTest extends TestCase
{
    public function test_only_the_keys_that_moved_are_reported(): void
    {
        $changes = SettingsChanges::between(
            ['site_name' => 'Old', 'tax_rate' => 5, 'default_currency' => 'USD'],
            ['site_name' => 'New', 'tax_rate' => 5, 'default_currency' => 'USD'],
        );

        $this->assertSame(['site_name' => ['from' => 'Old', 'to' => 'New']], $changes);
    }

    public function test_a_form_string_matching_a_stored_number_is_not_a_change(): void
    {
        $changes = SettingsChanges::between(
            ['tax_rate' => 5, 'invoice_due_days' => 14, 'gateway_enabled' => true],
            ['tax_rate' => '5', 'invoice_due_days' => '14', 'gateway_enabled' => true],
        );

        $this->assertSame([], $changes);
    }

    public function test_writing_an_empty_value_over_an_unset_key_is_not_a_change(): void
    {
        $changes = SettingsChanges::between(
            ['site_tagline' => null, 'registration_enabled' => null, 'tax_rate' => null],
            ['site_tagline' => '', 'registration_enabled' => false, 'tax_rate' => '0'],
        );

        $this->assertSame([], $changes);
    }

    public function test_a_secret_is_recorded_as_set_or_cleared_but_never_in_the_clear(): void
    {
        $changes = SettingsChanges::between(
            ['stripe_secret' => 'sk_live_old', 'paypal_secret' => null],
            ['stripe_secret' => 'sk_live_new', 'paypal_secret' => 'pp_secret'],
            ['stripe_secret', 'paypal_secret'],
        );

        $this->assertSame(SettingsChanges::SECRET_MARKER, $changes['stripe_secret']['from']);
        $this->assertSame(SettingsChanges::SECRET_MARKER, $changes['stripe_secret']['to']);
        $this->assertNull($changes['paypal_secret']['from']);
        $this->assertSame(SettingsChanges::SECRET_MARKER, $changes['paypal_secret']['to']);

        $this->assertStringNotContainsString('sk_live_old', (string) json_encode($changes));
        $this->assertStringNotContainsString('pp_secret', (string) json_encode($changes));
    }

    public function test_clearing_a_secret_keeps_the_stored_value_out_of_the_audit(): void
    {
        $changes = SettingsChanges::between(
            ['stripe_secret' => 'sk_live_old'],
            ['stripe_secret' => null],
            ['stripe_secret'],
        );

        $this->assertSame(['from' => SettingsChanges::SECRET_MARKER, 'to' => null], $changes['stripe_secret']);
    }

    public function test_fresh_install_first_save_does_not_report_every_default(): void
    {
        $submitted = [
            'site_name' => 'idexal Os',
            'registration_enabled' => false,
            'tax_rate' => '0',
            'payment_instructions' => '',
        ];

        $this->assertSame(
            ['site_name' => ['from' => null, 'to' => 'idexal Os']],
            SettingsChanges::between([], $submitted),
            'Only the value that actually became something new belongs in the entry.',
        );
    }
}
