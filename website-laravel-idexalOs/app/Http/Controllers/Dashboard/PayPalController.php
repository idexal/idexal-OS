<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoiceService;
use App\Services\OrganizationContext;
use App\Services\PaymentGatewayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * The PayPal order round trip. The order is created against the invoice's
 * outstanding balance, the customer approves it on PayPal, and the capture
 * settles the invoice exactly once — a refresh or a duplicate webhook is a
 * no-op because the pending payment row is the single source of truth.
 */
class PayPalController extends Controller
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly PaymentGatewayService $gateways,
        private readonly InvoiceService $invoices,
    ) {}

    public function start(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->ensureBelongs($invoice);

        if (! $this->gateways->paypalReady()) {
            return back()->withErrors(['payment' => __('ui.errors.payment_unavailable')]);
        }

        if (! $invoice->isOpen() || $invoice->balanceDue() <= 0) {
            return redirect()->route('dashboard.invoices.show', $invoice)
                ->withErrors(['invoice' => __('ui.common.not_available')]);
        }

        try {
            $order = $this->gateways->createPayPalOrder($invoice);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['payment' => __('ui.common.something_wrong')]);
        }

        // An abandoned attempt leaves a stale pending row behind. Clearing it
        // keeps a single outstanding order per invoice so the return URL and
        // the webhook always find the payment they belong to.
        $invoice->payments()
            ->where('gateway', Payment::GATEWAY_PAYPAL)
            ->where('status', Payment::STATUS_PENDING)
            ->delete();

        $invoice->payments()->create([
            'organization_id' => $invoice->organization_id,
            'user_id' => $request->user()?->getKey(),
            'gateway' => Payment::GATEWAY_PAYPAL,
            'gateway_reference' => $order['id'],
            'amount' => $invoice->balanceDue(),
            'currency' => $invoice->currency,
            'status' => Payment::STATUS_PENDING,
            'metadata' => ['source' => 'dashboard', 'approve_url' => $order['approve_url']],
        ]);

        return redirect()->away($order['approve_url']);
    }

    public function return(Request $request): RedirectResponse
    {
        $orderId = (string) $request->query('token', '');

        $payment = Payment::query()
            ->where('gateway', Payment::GATEWAY_PAYPAL)
            ->where('gateway_reference', $orderId)
            ->first();

        if (! $payment instanceof Payment || ! $payment->invoice instanceof Invoice) {
            return redirect()->route('dashboard.billing')
                ->withErrors(['payment' => __('ui.errors.payment_unavailable')]);
        }

        $invoice = $payment->invoice;
        $this->ensureBelongs($invoice);

        if ($payment->status === Payment::STATUS_SUCCEEDED) {
            return redirect()->route('dashboard.invoices.show', $invoice)
                ->with('status', __('ui.billing.checkout_success'));
        }

        try {
            $capture = $this->gateways->capturePayPalOrder($orderId);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('dashboard.invoices.show', $invoice)
                ->withErrors(['payment' => __('ui.errors.payment_capture_failed')]);
        }

        $captured = $this->gateways->capturedAmount($capture);

        if ($captured === null) {
            return redirect()->route('dashboard.invoices.show', $invoice)
                ->withErrors(['payment' => __('ui.errors.payment_capture_failed')]);
        }

        $captureId = $this->gateways->captureId($capture);

        $payment->forceFill([
            'amount' => $captured,
            'gateway_reference' => $captureId ?? $orderId,
            'metadata' => array_merge($payment->metadata ?? [], [
                'order_id' => $orderId,
                'capture_id' => $captureId,
            ]),
        ])->save();

        $this->invoices->confirmPendingPayment($payment);

        return redirect()->route('dashboard.invoices.show', $invoice)
            ->with('status', __('ui.billing.checkout_success'));
    }

    private function ensureBelongs(Invoice $invoice): void
    {
        $organization = $this->context->requireOrganization();
        abort_unless($invoice->organization_id === $organization->getKey(), 404);
    }
}
