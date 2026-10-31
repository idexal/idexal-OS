<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A rejected form is the moment a non-technical operator reads the most words.
 * With no Arabic validation table those words came from the framework in
 * English, inside an otherwise Arabic panel.
 */
class ValidationMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_rejected_settings_field_is_named_in_arabic(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch('/admin/settings', [
            'support_email' => 'support@example.test',
            'default_currency' => 'USD',
            'tax_rate' => '0',
            'invoice_due_days' => '14',
            'desktop_latest_version' => '2.2.6',
            'desktop_min_version' => '2.0.0',
        ])->assertSessionHasErrors('site_name');

        $message = session('errors')->first('site_name');

        $this->assertSame(__('validation.required', ['attribute' => __('validation.attributes.site_name')]), $message);
        $this->assertStringNotContainsString('site_name', $message);
        $this->assertMatchesRegularExpression('/[\x{0600}-\x{06FF}]/u', $message);
    }

    public function test_a_too_long_gateway_field_reports_the_limit_in_arabic(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch('/admin/payment-gateways', [
            'bank_transfer_bank_name' => str_repeat('a', 191),
        ])->assertSessionHasErrors('bank_transfer_bank_name');

        $message = session('errors')->first('bank_transfer_bank_name');

        $this->assertStringContainsString(__('validation.attributes.bank_transfer_bank_name'), $message);
        $this->assertStringContainsString('190', $message);
    }

    public function test_a_failed_sign_in_says_so_in_arabic(): void
    {
        User::factory()->create(['email' => 'operator@example.test']);

        $this->withSession(['locale' => 'ar'])->post('/login', [
            'email' => 'operator@example.test',
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('email');

        $message = session('errors')->first('email');

        $this->assertSame(__('ui.auth.invalid_credentials'), $message);
        $this->assertMatchesRegularExpression('/[\x{0600}-\x{06FF}]/u', $message);
    }

    public function test_a_reset_for_an_unknown_address_is_reported_in_arabic(): void
    {
        $this->withSession(['locale' => 'ar'])->post('/reset-password', [
            'token' => 'a-token-nobody-issued',
            'email' => 'nobody@example.test',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('email');

        $message = session('errors')->first('email');

        $this->assertSame(__('passwords.user'), $message);
        $this->assertMatchesRegularExpression('/[\x{0600}-\x{06FF}]/u', $message);
    }

    public function test_a_reset_with_a_bad_token_is_reported_in_arabic(): void
    {
        User::factory()->create(['email' => 'operator@example.test']);

        $this->withSession(['locale' => 'ar'])->post('/reset-password', [
            'token' => 'a-token-nobody-issued',
            'email' => 'operator@example.test',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('email');

        $message = session('errors')->first('email');

        $this->assertSame(__('passwords.token'), $message);
        $this->assertMatchesRegularExpression('/[\x{0600}-\x{06FF}]/u', $message);
    }

    /**
     * The Arabic table mirrors the framework's own. A Laravel upgrade that adds
     * a rule fails here rather than silently answering in English.
     */
    public function test_the_arabic_table_covers_every_rule_the_framework_defines(): void
    {
        $english = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $arabic = require base_path('lang/ar/validation.php');

        $this->assertSame([], array_keys(array_diff_key($english, $arabic)), 'Rules missing from the Arabic table.');

        foreach ($english as $rule => $message) {
            if ($rule === 'custom') {
                continue;
            }

            if (is_array($message)) {
                $this->assertSame([], array_keys(array_diff_key($message, $arabic[$rule])), "The [{$rule}] rule is missing Arabic variants.");

                continue;
            }

            $this->assertNotSame($message, $arabic[$rule], "The [{$rule}] rule is still in English.");
        }
    }
}
