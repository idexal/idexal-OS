<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Organization;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminInvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly InvoicePdfService $pdf,
    ) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('q', ''));

        $invoices = Invoice::query()
            ->with('organization:id,name,slug')
            ->when(in_array($status, [
                Invoice::STATUS_DRAFT,
                Invoice::STATUS_OPEN,
                Invoice::STATUS_PAID,
                Invoice::STATUS_VOID,
                Invoice::STATUS_UNCOLLECTIBLE,
                Invoice::STATUS_REFUNDED,
            ], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query
                ->where('number', 'like', '%'.$search.'%')
                ->orWhereHas('organization', fn ($query) => $query->where('name', 'like', '%'.$search.'%')))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.invoices.index', [
            'invoices' => $invoices,
            'status' => $status,
            'search' => $search,
            'stats' => [
                'open' => Invoice::query()->where('status', Invoice::STATUS_OPEN)->count(),
                'paid' => Invoice::query()->where('status', Invoice::STATUS_PAID)->count(),
                'overdue' => Invoice::query()->where('status', Invoice::STATUS_OPEN)->where('due_at', '<', now())->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.invoices.create', [
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name', 'slug', 'currency']),
            'selectedOrganization' => (string) $request->query('organization', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'string', 'exists:organizations,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:190'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'due_at' => ['nullable', 'date', 'after:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $organization = Organization::query()->findOrFail($validated['organization_id']);

        $invoice = $this->invoices->create($organization, array_values($validated['items']), [
            'due_at' => $validated['due_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        AuditLog::record('admin.invoice.created', $organization, $invoice, $invoice->number);

        return redirect()->route('admin.invoices.show', $invoice)->with('status', __('ui.common.saved'));
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['items', 'payments', 'organization.owner']);

        return view('admin.invoices.show', [
            'invoice' => $invoice,
            'organization' => $invoice->organization,
        ]);
    }

    public function download(Invoice $invoice): Response
    {
        return response($this->pdf->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->number.'.pdf"',
        ]);
    }

    public function markPaid(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:190'],
        ]);

        $this->invoices->markPaid($invoice, [
            'amount' => isset($validated['amount']) ? (float) $validated['amount'] : null,
            'user_id' => $request->user()->getKey(),
            'reference' => $validated['reference'] ?? null,
            'gateway' => 'manual',
            'metadata' => ['source' => 'admin'],
        ]);

        AuditLog::record('admin.invoice.marked_paid', $invoice->organization, $invoice, $invoice->number);

        return redirect()->route('admin.invoices.show', $invoice)->with('status', __('ui.common.saved'));
    }

    public function void(Invoice $invoice): RedirectResponse
    {
        if ($invoice->isPaid()) {
            return back()->withErrors(['invoice' => __('ui.common.not_available')]);
        }

        $invoice->forceFill([
            'status' => Invoice::STATUS_VOID,
            'notes' => trim((string) $invoice->notes."\n".__('ui.admin.invoices.void_note')),
        ])->save();

        AuditLog::record('admin.invoice.voided', $invoice->organization, $invoice, $invoice->number);

        return redirect()->route('admin.invoices.show', $invoice)->with('status', __('ui.common.saved'));
    }
}
