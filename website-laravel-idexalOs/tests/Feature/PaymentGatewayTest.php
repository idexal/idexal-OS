<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_enable_paypal_and_bank_transfer_gateways(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/payment-gateways')
            ->assertOk();

        $this->actingAs($admin)
            ->patch('/admin/payment-gateways', [
                'paypal_enabled' => '1',
                'paypal_mode' => 'sandbox',
                'paypal_client_id' => 'paypal-client',
                'paypal_secret' => 'paypal-secret',
                'paypal_webhook_id' => 'wh-123',
                'bank_transfer_enabled' => '1',
                'bank_transfer_bank_name' => 'Example Bank',
                'bank_transfer_iban' => 'AE070331234567890123456',
            ])
            ->assertRedirect('/admin/payment-gateways');

        $this->assertSame('paypal-client', Setting::get('paypal_client_id'));

        $secret = Setting::query()->whereKey('paypal_secret')->firstOrFail();
        $this->assertTrue($secret->encrypted);
        $this->assertNotSame('paypal-secret', $secret->value);

        $this->assertTrue((bool) Setting::get('bank_transfer_enabled'));

        $gateways = app(PaymentGatewayService::class)->availableGateways();

        $this->assertContains(Payment::GATEWAY_PAYPAL, $gateways);
        $this->assertContains(Payment::GATEWAY_BANK_TRANSFER, $gateways);
    }

    public function test_a_gateway_cannot_be_enabled_without_its_credentials(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch('/admin/payment-gateways', [
                'paypal_enabled' => '1',
                'paypal_mode' => 'sandbox',
                'paypal_client_id' => '',
            ])
            ->assertSessionHasErrors('paypal_client_id');

        $this->assertFalse((bool) Setting::get('paypal_enabled'));
        $this->assertNotContains(Payment::GATEWAY_PAYPAL, app(PaymentGatewayService::class)->availableGateways());
    }

    public function test_a_bank_transfer_records_the_invoice_reference_and_settles_on_confirmation(): void
    {
        [$user, $organization] = $this->workspace();
        $invoice = $this->invoice($organization, 40);

        Setting::put('bank_transfer_enabled', true, false, 'billing');
        Setting::put('bank_transfer_iban', 'AE070331234567890123456', false, 'billing');

        $this->actingAs($user)
            ->post("/dashboard/invoices/{$invoice->getKey()}/bank-transfer")
            ->assertRedirect("/dashboard/invoices/{$invoice->getKey()}");

        $payment = $invoice->payments()->firstOrFail();

        $this->assertSame(Payment::GATEWAY_BANK_TRANSFER, $payment->gateway);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame('BT-'.$invoice->number, $payment->gateway_reference);

        app(InvoiceService::class)->confirmPendingPayment($payment);

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertSame(Payment::STATUS_SUCCEEDED, $payment->fresh()->status);
    }

    public function test_starting_a_paypal_payment_creates_an_order_and_redirects(): void
    {
        [$user, $organization] = $this->workspace();
        $invoice = $this->invoice($organization, 40);

        $this->enablePayPal();

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token']),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'ORDER-9',
                'links' => [
                    ['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-9'],
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->post("/dashboard/invoices/{$invoice->getKey()}/paypal")
            ->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=ORDER-9');

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->getKey(),
            'gateway' => Payment::GATEWAY_PAYPAL,
            'gateway_reference' => 'ORDER-9',
            'status' => Payment::STATUS_PENDING,
        ]);
    }

    public function test_returning_from_paypal_captures_and_settles_the_invoice(): void
    {
        [$user, $organization] = $this->workspace();
        $invoice = $this->invoice($organization, 40);

        $this->enablePayPal();

        $payment = $invoice->payments()->create([
            'organization_id' => $invoice->organization_id,
            'user_id' => $user->getKey(),
            'gateway' => Payment::GATEWAY_PAYPAL,
            'gateway_reference' => 'ORDER-9',
            'amount' => 40,
            'currency' => 'USD',
            'status' => Payment::STATUS_PENDING,
        ]);

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token']),
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-9/capture' => Http::response([
                'id' => 'ORDER-9',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'payments' => ['captures' => [[
                        'id' => 'CAP-9',
                        'status' => 'COMPLETED',
                        'amount' => ['value' => '40.00', 'currency_code' => 'USD'],
                    ]]],
                ]],
            ]),
        ]);

        $this->actingAs($user)
            ->get('/dashboard/billing/paypal/return?token=ORDER-9')
            ->assertRedirect("/dashboard/invoices/{$invoice->getKey()}");

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertSame(Payment::STATUS_SUCCEEDED, $payment->fresh()->status);
    }

    public function test_a_paypal_capture_webhook_settles_an_invoice_exactly_once(): void
    {
        [$user, $organization] = $this->workspace();
        $invoice = $this->invoice($organization, 40);

        $this->enablePayPal();

        $invoice->payments()->create([
            'organization_id' => $invoice->organization_id,
            'user_id' => $user->getKey(),
            'gateway' => Payment::GATEWAY_PAYPAL,
            'gateway_reference' => 'ORDER-1',
            'amount' => 40,
            'currency' => 'USD',
            'status' => Payment::STATUS_PENDING,
        ]);

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token']),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        ]);

        $payload = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAP-1',
                'custom_id' => $invoice->getKey(),
                'amount' => ['value' => '40.00', 'currency_code' => 'USD'],
                'supplementary_data' => ['related_ids' => ['order_id' => 'ORDER-1']],
            ],
        ];

        $this->postJson('/webhooks/paypal', $payload)->assertOk()->assertJson(['status' => 'processed']);

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertSame(40.0, (float) $invoice->amount_paid);
        $this->assertSame(Payment::STATUS_SUCCEEDED, $invoice->payments()->firstOrFail()->status);

        $this->postJson('/webhooks/paypal', $payload)->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertSame(40.0, (float) $invoice->fresh()->amount_paid);
    }

    public function test_a_paypal_webhook_is_refused_when_paypal_is_not_configured(): void
    {
        $this->postJson('/webhooks/paypal', ['event_type' => 'PAYMENT.CAPTURE.COMPLETED'])
            ->assertStatus(400)
            ->assertJson(['status' => 'invalid']);
    }

    public function test_an_unsigned_paypal_webhook_is_refused(): void
    {
        $this->enablePayPal();

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token']),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'FAILURE']),
        ]);

        $this->postJson('/webhooks/paypal', ['event_type' => 'PAYMENT.CAPTURE.COMPLETED'])
            ->assertStatus(400)
            ->assertJson(['status' => 'invalid']);
    }

    private function enablePayPal(): void
    {
        Setting::put('paypal_enabled', true, false, 'billing');
        Setting::put('paypal_mode', 'sandbox', false, 'billing');
        Setting::put('paypal_client_id', 'paypal-client', false, 'billing');
        Setting::put('paypal_secret', 'paypal-secret', true, 'billing');
        Setting::put('paypal_webhook_id', 'wh-123', false, 'billing');
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function workspace(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['owner_id' => $user->getKey()]);

        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        return [$user, $organization];
    }

    private function invoice(Organization $organization, float $total): Invoice
    {
        return Invoice::factory()->create([
            'organization_id' => $organization->getKey(),
            'subtotal' => $total,
            'total' => $total,
        ]);
    }
}
