<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoicePdfService;
use App\Services\OrganizationContext;
use App\Services\PaymentGatewayService;
use App\Services\StripeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly InvoicePdfService $pdf,
        private readonly StripeService $stripe,
        private readonly PaymentGatewayService $gateways,
    ) {}

    public function index(): View
    {
        $organization = $this->context->requireOrganization();

        return view('dashboard.invoices.index', [
            'organization' => $organization,
            'invoices' => $organization->invoices()->latest('issued_at')->latest('created_at')->paginate(15),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        $this->ensureBelongs($invoice);
        $invoice->load(['items', 'payments']);

        return view('dashboard.invoices.show', [
            'invoice' => $invoice,
            'organization' => $invoice->organization,
            'stripeEnabled' => $this->stripe->enabled(),
            'paypalEnabled' => $this->gateways->paypalReady(),
            'bankTransferEnabled' => $this->gateways->bankTransferReady(),
            'bankTransferDetails' => $this->gateways->bankTransferDetails(),
            'bankTransferReference' => $this->gateways->bankTransferReference($invoice),
        ]);
    }

    public function download(Invoice $invoice): Response
    {
        $this->ensureBelongs($invoice);

        return response($this->pdf->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->number.'.pdf"',
        ]);
    }

    public function pay(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->ensureBelongs($invoice);

        if (! $invoice->isOpen()) {
            return redirect()->route('dashboard.invoices.show', $invoice)
                ->withErrors(['invoice' => __('ui.common.not_available')]);
        }

        if (is_string($invoice->stripe_hosted_invoice_url) && $invoice->stripe_hosted_invoice_url !== '') {
            return redirect()->away($invoice->stripe_hosted_invoice_url);
        }

        $payment = $invoice->payments()->where('status', Payment::STATUS_PENDING)->latest('created_at')->first();

        if (! $payment instanceof Payment) {
            $invoice->payments()->create([
                'organization_id' => $invoice->organization_id,
                'user_id' => $request->user()->getKey(),
                'gateway' => Payment::GATEWAY_MANUAL,
                'amount' => $invoice->balanceDue(),
                'currency' => $invoice->currency,
                'status' => Payment::STATUS_PENDING,
                'metadata' => ['source' => 'dashboard'],
            ]);
        }

        return redirect()->route('dashboard.invoices.show', $invoice)
            ->with('status', __('ui.billing.manual_invoice_created'));
    }

    /**
     * Announce a bank transfer. No money moves here: a pending payment row is
     * recorded with the reference the customer must quote, and an operator (or
     * the reconciliation flow) settles the invoice when the funds arrive.
     */
    public function bankTransfer(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->ensureBelongs($invoice);

        if (! $this->gateways->bankTransferReady()) {
            return back()->withErrors(['payment' => __('ui.errors.payment_unavailable')]);
        }

        if (! $invoice->isOpen() || $invoice->balanceDue() <= 0) {
            return redirect()->route('dashboard.invoices.show', $invoice)
                ->withErrors(['invoice' => __('ui.common.not_available')]);
        }

        $existing = $invoice->payments()
            ->where('gateway', Payment::GATEWAY_BANK_TRANSFER)
            ->where('status', Payment::STATUS_PENDING)
            ->exists();

        if (! $existing) {
            $invoice->payments()->create([
                'organization_id' => $invoice->organization_id,
                'user_id' => $request->user()?->getKey(),
                'gateway' => Payment::GATEWAY_BANK_TRANSFER,
                'gateway_reference' => $this->gateways->bankTransferReference($invoice),
                'amount' => $invoice->balanceDue(),
                'currency' => $invoice->currency,
                'status' => Payment::STATUS_PENDING,
                'metadata' => ['source' => 'dashboard'],
            ]);
        }

        return redirect()->route('dashboard.invoices.show', $invoice)
            ->with('status', __('ui.billing.bank_transfer_started'));
    }

    private function ensureBelongs(Invoice $invoice): void
    {
        $organization = $this->context->requireOrganization();
        abort_unless($invoice->organization_id === $organization->getKey(), 404);
    }
}
