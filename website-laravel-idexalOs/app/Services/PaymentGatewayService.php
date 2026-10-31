<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The single place that knows which payment gateways are switched on and how to
 * talk to them. Every gateway is configured by an operator in the admin panel;
 * nothing here reads a hard-coded account, key, or exchange rate.
 *
 * Stripe keeps its own StripeService because it also drives subscriptions.
 * This service covers the invoice payment methods an operator can enable:
 * Stripe (hosted checkout is created by StripeService), PayPal, bank transfer.
 */
class PaymentGatewayService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Gateways a customer may currently choose, in display order.
     *
     * @return list<string>
     */
    public function availableGateways(): array
    {
        return array_keys(array_filter([
            Payment::GATEWAY_STRIPE => $this->stripeReady(),
            Payment::GATEWAY_PAYPAL => $this->paypalReady(),
            Payment::GATEWAY_BANK_TRANSFER => $this->bankTransferReady(),
        ]));
    }

    public function enabled(string $gateway): bool
    {
        return in_array($gateway, $this->availableGateways(), true);
    }

    public function stripeReady(): bool
    {
        return $this->settings->stripeEnabled();
    }

    public function paypalReady(): bool
    {
        return $this->settings->paypalEnabled();
    }

    public function bankTransferReady(): bool
    {
        return $this->settings->bankTransferEnabled();
    }

    /**
     * @return array<string, string>
     */
    public function bankTransferDetails(): array
    {
        return $this->settings->bankTransferDetails();
    }

    /**
     * The reference a customer must quote with a bank transfer. The invoice
     * number is already unique and human-readable, so it is the reference.
     */
    public function bankTransferReference(Invoice $invoice): string
    {
        return 'BT-'.$invoice->number;
    }

    /**
     * Create a PayPal order for the invoice's outstanding balance.
     *
     * @return array{id: string, approve_url: string}
     */
    public function createPayPalOrder(Invoice $invoice): array
    {
        $amount = $invoice->balanceDue();

        if ($amount <= 0) {
            throw new RuntimeException('Invoice has no outstanding balance.');
        }

        $response = $this->paypalRequest()->post($this->paypalBase().'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $invoice->number,
                'custom_id' => $invoice->getKey(),
                'description' => __('ui.billing.paypal_line', ['number' => $invoice->number]),
                'amount' => [
                    'currency_code' => strtoupper((string) $invoice->currency),
                    'value' => number_format($amount, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'brand_name' => (string) config('idexal.brand.name'),
                'user_action' => 'PAY_NOW',
                'return_url' => route('dashboard.billing.paypal.return'),
                'cancel_url' => route('dashboard.invoices.show', $invoice),
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('PayPal rejected the order: '.$response->body());
        }

        $data = $response->json();
        $id = is_string($data['id'] ?? null) ? $data['id'] : '';

        if ($id === '') {
            throw new RuntimeException('PayPal returned no order id.');
        }

        return ['id' => $id, 'approve_url' => $this->approveLink($data)];
    }

    /**
     * Capture an approved order and return PayPal's capture payload.
     *
     * @return array<string, mixed>
     */
    public function capturePayPalOrder(string $orderId): array
    {
        $response = $this->paypalRequest()->post(
            $this->paypalBase().'/v2/checkout/orders/'.rawurlencode($orderId).'/capture',
            [],
        );

        if (! $response->successful()) {
            throw new RuntimeException('PayPal rejected the capture: '.$response->body());
        }

        return (array) $response->json();
    }

    /**
     * The captured amount, in minor-unit-free decimal form, from a capture
     * payload. Returns null when PayPal did not report a completed capture.
     */
    public function capturedAmount(array $capture): ?float
    {
        $unit = $capture['purchase_units'][0] ?? null;
        $payment = is_array($unit) ? ($unit['payments']['captures'][0] ?? null) : null;

        if (! is_array($payment)) {
            return null;
        }

        if (($payment['status'] ?? null) !== 'COMPLETED') {
            return null;
        }

        $value = $payment['amount']['value'] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * The PayPal capture id from a capture payload, if present.
     */
    public function captureId(array $capture): ?string
    {
        $unit = $capture['purchase_units'][0] ?? null;
        $id = is_array($unit) ? ($unit['payments']['captures'][0]['id'] ?? null) : null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Verify a webhook really came from PayPal. PayPal verifies signatures
     * through its own API, so this needs the configured webhook id; without one
     * the webhook is refused rather than trusted blindly.
     */
    public function verifyPayPalWebhook(Request $request): bool
    {
        $credentials = $this->settings->paypalCredentials();

        if ($credentials['webhook_id'] === null
            || $credentials['client_id'] === null
            || $credentials['secret'] === null) {
            return false;
        }

        try {
            $response = $this->paypalRequest()->post($this->paypalBase().'/v1/notifications/verify-webhook-signature', [
                'auth_algo' => (string) $request->header('PAYPAL-AUTH-ALGO', ''),
                'cert_url' => (string) $request->header('PAYPAL-CERT-URL', ''),
                'transmission_id' => (string) $request->header('PAYPAL-TRANSMISSION-ID', ''),
                'transmission_sig' => (string) $request->header('PAYPAL-TRANSMISSION-SIG', ''),
                'transmission_time' => (string) $request->header('PAYPAL-TRANSMISSION-TIME', ''),
                'webhook_id' => $credentials['webhook_id'],
                'webhook_event' => $request->all(),
            ]);
        } catch (\Throwable) {
            return false;
        }

        return $response->successful()
            && ($response->json()['verification_status'] ?? null) === 'SUCCESS';
    }

    /**
     * An OAuth2 access token, cached until shortly before PayPal expires it.
     */
    public function paypalAccessToken(): string
    {
        $credentials = $this->settings->paypalCredentials();

        if ($credentials['client_id'] === null || $credentials['secret'] === null) {
            throw new RuntimeException('PayPal is not configured.');
        }

        $cacheKey = 'paypal.access_token.'.md5($credentials['mode'].$credentials['client_id']);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($credentials): string {
            $response = Http::asForm()
                ->withBasicAuth((string) $credentials['client_id'], (string) $credentials['secret'])
                ->acceptJson()
                ->timeout(30)
                ->post($credentials['base'].'/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('PayPal authentication failed.');
            }

            $token = (string) ($response->json()['access_token'] ?? '');

            if ($token === '') {
                throw new RuntimeException('PayPal returned no access token.');
            }

            return $token;
        });
    }

    private function paypalRequest(): PendingRequest
    {
        return Http::withToken($this->paypalAccessToken())
            ->acceptJson()
            ->timeout(30);
    }

    private function paypalBase(): string
    {
        return $this->settings->paypalCredentials()['base'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function approveLink(array $data): string
    {
        $links = $data['links'] ?? [];

        if (is_array($links)) {
            foreach ($links as $link) {
                if (is_array($link) && ($link['rel'] ?? null) === 'approve' && is_string($link['href'] ?? null)) {
                    return $link['href'];
                }
            }
        }

        throw new RuntimeException('PayPal order has no approval link.');
    }
}
