<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\SettingsChanges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin settings screens all save a whole page at once. The audit entry has
 * to say which field moved, and a secret must never reach the log in the clear.
 */
class AdminSettingsAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_platform_setting_change_is_recorded_with_the_field_that_moved(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch('/admin/settings', $this->platformPayload(['site_name' => 'idexal Os']))
            ->assertRedirect('/admin/settings');

        $entry = AuditLog::query()->where('action', 'admin.settings.updated')->sole();
        $settings = $entry->meta['settings'];

        $this->assertSame(['from' => null, 'to' => 'idexal Os'], $settings['site_name']);
        $this->assertSame(['from' => null, 'to' => 'support@example.test'], $settings['support_email']);
        $this->assertArrayNotHasKey('tax_rate', $settings, 'A zero over an unset key is the same state, not an edit.');
        $this->assertArrayNotHasKey('payment_instructions', $settings, 'An empty string over unset is not an edit.');
    }

    public function test_saving_the_platform_page_without_an_edit_writes_no_entry(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch('/admin/settings', $this->platformPayload())->assertRedirect();

        $this->assertSame(1, AuditLog::query()->where('action', 'admin.settings.updated')->count());

        $this->actingAs($admin)->patch('/admin/settings', $this->platformPayload())->assertRedirect();

        $this->assertSame(1, AuditLog::query()->where('action', 'admin.settings.updated')->count());
    }

    public function test_a_stripe_secret_rotation_is_visible_without_the_secret(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch('/admin/settings', $this->platformPayload([
            'stripe_secret' => 'sk_live_first',
        ]))->assertRedirect();

        $this->actingAs($admin)->patch('/admin/settings', $this->platformPayload([
            'stripe_secret' => 'sk_live_second',
        ]))->assertRedirect();

        $latest = AuditLog::query()->where('action', 'admin.settings.updated')->orderByDesc('id')->firstOrFail();

        $this->assertSame(
            ['from' => SettingsChanges::SECRET_MARKER, 'to' => SettingsChanges::SECRET_MARKER],
            $latest->meta['settings']['stripe_secret'],
        );

        foreach (AuditLog::all() as $log) {
            $this->assertStringNotContainsString('sk_live_first', (string) json_encode($log->meta));
            $this->assertStringNotContainsString('sk_live_second', (string) json_encode($log->meta));
        }
    }

    public function test_enabling_a_payment_gateway_is_recorded_with_its_details(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch('/admin/payment-gateways', [
            'bank_transfer_enabled' => '1',
            'bank_transfer_bank_name' => 'Example Bank',
            'bank_transfer_iban' => 'AE070331234567890123456',
            'paypal_secret' => 'pp_secret_value',
        ])->assertRedirect('/admin/payment-gateways');

        $entry = AuditLog::query()->where('action', 'admin.payment_gateways.updated')->sole();
        $settings = $entry->meta['settings'];

        $this->assertSame(['from' => null, 'to' => true], $settings['bank_transfer_enabled']);
        $this->assertSame(['from' => null, 'to' => 'Example Bank'], $settings['bank_transfer_bank_name']);
        $this->assertSame(
            ['from' => null, 'to' => SettingsChanges::SECRET_MARKER],
            $settings['paypal_secret'],
        );
    }

    public function test_a_referral_reward_change_is_recorded(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch('/admin/referrals', [
            'referral_enabled' => '1',
            'referral_reward_amount' => '5',
            'referral_reward_currency' => 'usd',
            'referral_cookie_days' => '30',
        ])->assertRedirect('/admin/referrals');

        $this->actingAs($admin)->patch('/admin/referrals', [
            'referral_enabled' => '1',
            'referral_reward_amount' => '12.5',
            'referral_reward_currency' => 'usd',
            'referral_cookie_days' => '30',
        ])->assertRedirect('/admin/referrals');

        $latest = AuditLog::query()->where('action', 'admin.referrals.updated')->orderByDesc('id')->firstOrFail();
        $settings = $latest->meta['settings'];

        $this->assertSame(['referral_reward_amount'], array_keys($settings));
        $this->assertEquals(5, $settings['referral_reward_amount']['from']);
        $this->assertEquals(12.5, $settings['referral_reward_amount']['to']);
    }

    public function test_the_audit_page_renders_the_recorded_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch('/admin/settings', $this->platformPayload(['site_name' => 'Idexal Workspace']))
            ->assertRedirect();

        $this->actingAs($admin)->get('/admin/audit?action=admin.settings.updated')
            ->assertOk()
            ->assertSee('admin.settings.updated')
            ->assertSee(__('ui.admin.audit.from'))
            ->assertSee(__('ui.admin.audit.to'))
            ->assertSee('site_name', false)
            ->assertSee('Idexal Workspace');
    }

    public function test_the_audit_page_names_payment_fields_instead_of_printing_keys(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch('/admin/payment-gateways', [
            'bank_transfer_enabled' => '1',
            'bank_transfer_bank_name' => 'Example Bank',
            'bank_transfer_iban' => 'AE070331234567890123456',
        ])->assertRedirect('/admin/payment-gateways');

        $this->actingAs($admin)->get('/admin/audit?action=admin.payment_gateways.updated')
            ->assertOk()
            ->assertSee(__('ui.admin.audit.fields.bank_transfer_enabled'))
            ->assertSee(__('ui.admin.audit.fields.bank_transfer_bank_name'))
            ->assertSee('bank_transfer_enabled', false);
    }

    public function test_free_form_metadata_still_reads_as_json(): void
    {
        $admin = User::factory()->admin()->create();

        AuditLog::record('admin.user.updated', null, $admin, $admin->email, [
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->get('/admin/audit?action=admin.user.updated')
            ->assertOk()
            ->assertSee('&quot;role&quot;: &quot;admin&quot;', false);
    }

    /**
     * The minimum the platform settings form requires, so a test only has to name
     * the field it is actually about.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function platformPayload(array $overrides = []): array
    {
        return [
            'site_name' => 'idexal Os',
            'site_tagline' => '',
            'support_email' => 'support@example.test',
            'default_currency' => 'USD',
            'tax_rate' => '0',
            'invoice_due_days' => '14',
            'desktop_latest_version' => '2.2.6',
            'desktop_min_version' => '2.0.0',
            ...$overrides,
        ];
    }
}
