<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoiceService;
use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * PayPal's server-to-server confirmation. The signature is verified through
 * PayPal's own API before anything is trusted; a capture that already settled
 * its invoice is reported as a duplicate so PayPal stops retrying.
 */
class PayPalWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayService $gateways,
        private readonly InvoiceService $invoices,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->gateways->verifyPayPalWebhook($request)) {
            return response()->json(['status' => 'invalid'], 400);
        }

        $event = $request->all();

        if (($event['event_type'] ?? null) !== 'PAYMENT.CAPTURE.COMPLETED') {
            return response()->json(['status' => 'ignored']);
        }

        $resource = is_array($event['resource'] ?? null) ? $event['resource'] : [];
        $orderId = (string) data_get($resource, 'supplementary_data.related_ids.order_id', '');
        $invoiceId = (string) ($resource['custom_id'] ?? '');
        $captureId = (string) ($resource['id'] ?? '');
        $amount = $resource['amount']['value'] ?? null;

        $payment = $this->locatePayment($orderId, $captureId, $invoiceId);

        if ($payment instanceof Payment && $payment->status === Payment::STATUS_SUCCEEDED) {
            return response()->json(['status' => 'duplicate']);
        }

        if (! $payment instanceof Payment) {
            $payment = $this->createFromInvoice($invoiceId, $captureId, $orderId, $amount);

            if (! $payment instanceof Payment) {
                return response()->json(['status' => 'unmatched']);
            }
        }

        if (is_numeric($amount)) {
            $payment->forceFill(['amount' => (float) $amount])->save();
        }

        try {
            $this->invoices->confirmPendingPayment($payment);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['status' => 'error'], 500);
        }

        return response()->json(['status' => 'processed']);
    }

    private function locatePayment(string $orderId, string $captureId, string $invoiceId): ?Payment
    {
        if ($orderId !== '' || $captureId !== '') {
            $payment = Payment::query()
                ->where('gateway', Payment::GATEWAY_PAYPAL)
                ->where(function ($query) use ($orderId, $captureId): void {
                    $query->where('gateway_reference', $orderId)
                        ->orWhere('gateway_reference', $captureId);
                })
                ->latest('created_at')
                ->first();

            if ($payment instanceof Payment) {
                return $payment;
            }
        }

        if ($invoiceId !== '') {
            return Payment::query()
                ->where('gateway', Payment::GATEWAY_PAYPAL)
                ->where('invoice_id', $invoiceId)
                ->latest('created_at')
                ->first();
        }

        return null;
    }

    private function createFromInvoice(string $invoiceId, string $captureId, string $orderId, mixed $amount): ?Payment
    {
        if ($invoiceId === '' || ! is_numeric($amount)) {
            return null;
        }

        $invoice = Invoice::query()->find($invoiceId);

        if (! $invoice instanceof Invoice) {
            return null;
        }

        return $invoice->payments()->create([
            'organization_id' => $invoice->organization_id,
            'gateway' => Payment::GATEWAY_PAYPAL,
            'gateway_reference' => $captureId !== '' ? $captureId : $orderId,
            'amount' => (float) $amount,
            'currency' => $invoice->currency,
            'status' => Payment::STATUS_PENDING,
            'metadata' => [
                'source' => 'webhook',
                'order_id' => $orderId,
                'capture_id' => $captureId,
            ],
        ]);
    }
}
