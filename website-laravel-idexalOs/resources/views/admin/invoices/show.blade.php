@extends('layouts.admin')

@section('title', $invoice->number)
@section('page', __('ui.admin.nav.invoices'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a href="{{ route('admin.invoices.index') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-400 transition hover:text-ink-950">
                <x-icon name="arrow-right" class="size-3.5 rtl:rotate-180" />
                {{ __('ui.admin.invoices.title') }}
            </a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="font-mono text-2xl font-semibold tracking-tight sm:text-3xl">{{ $invoice->number }}</h1>
                <x-status :status="$invoice->status" />
                @if ($invoice->isOverdue())
                    <span class="badge badge-red">{{ __('ui.billing.overdue') }}</span>
                @endif
            </div>
            <p class="mt-1.5 text-sm text-ink-500">
                <a href="{{ route('admin.organizations.show', $organization) }}" class="font-medium text-ink-950 hover:underline">{{ $organization->name }}</a>
                · {{ $invoice->issued_at?->translatedFormat('d F Y') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.invoices.download', $invoice) }}" class="btn btn-outline btn-sm h-10 px-4">
                <x-icon name="download" class="size-4" />
                PDF
            </a>
            @if (! $invoice->isPaid() && ! in_array($invoice->status, [\App\Models\Invoice::STATUS_VOID, \App\Models\Invoice::STATUS_REFUNDED], true))
                <form method="POST" action="{{ route('admin.invoices.void', $invoice) }}" x-data @submit="if (! confirm(@js(__('ui.admin.invoices.confirm_void')))) { $event.preventDefault(); }">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm h-10 px-4 text-red-600">{{ __('ui.admin.invoices.void') }}</button>
                </form>
            @endif
        </div>
    </div>

    @error('invoice')
        <div class="alert alert-error">{{ $message }}</div>
    @enderror

    <div class="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-6 border-b border-ink-950/10 p-6 sm:p-8">
                <div>
                    <img src="{{ asset('brand/logo-light.png') }}" alt="{{ config('idexal.brand.name') }}" class="h-8 w-auto">
                    <p class="mt-3 text-[11px] leading-5 text-ink-400">
                        {{ config('idexal.brand.legal_name') }}<br>
                        <span dir="ltr">{{ config('idexal.brand.domain') }}</span>
                    </p>
                </div>
                <div class="text-end">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-ink-400">{{ __('ui.common.invoice') }}</p>
                    <p class="mt-1 font-mono text-sm font-semibold" dir="ltr">{{ $invoice->number }}</p>
                    <p class="mt-3 text-[10px] uppercase tracking-[0.14em] text-ink-400">{{ __('ui.billing.issued') }}</p>
                    <p class="mt-0.5 text-xs">{{ $invoice->issued_at?->translatedFormat('d M Y') ?? '—' }}</p>
                    <p class="mt-2 text-[10px] uppercase tracking-[0.14em] text-ink-400">{{ __('ui.billing.due') }}</p>
                    <p class="mt-0.5 text-xs {{ $invoice->isOverdue() ? 'font-medium text-red-600' : '' }}">{{ $invoice->due_at?->translatedFormat('d M Y') ?? '—' }}</p>
                </div>
            </div>

            <div class="grid gap-6 border-b border-ink-950/10 p-6 sm:grid-cols-2 sm:p-8">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-ink-400">{{ __('ui.billing.billed_to') }}</p>
                    <p class="mt-2 text-sm font-semibold">{{ $organization->name }}</p>
                    <p class="mt-1 text-[11px] leading-5 text-ink-500">
                        @if ($organization->address_line_1)
                            {{ $organization->address_line_1 }}<br>
                        @endif
                        @if ($organization->city || $organization->country)
                            {{ collect([$organization->city, $organization->country])->filter()->implode(', ') }}<br>
                        @endif
                        @if ($organization->billing_email)
                            <span dir="ltr">{{ $organization->billing_email }}</span>
                        @endif
                    </p>
                </div>
                <div class="sm:text-end">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-ink-400">{{ __('ui.billing.balance_due') }}</p>
                    <p class="mt-2 font-mono text-2xl font-medium" dir="ltr">{{ $invoice->formattedBalance() }}</p>
                    <p class="mt-1 text-[11px] text-ink-400">{{ __('ui.common.of') }} {{ $invoice->formattedTotal() }}</p>
                </div>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.common.description') }}</th>
                            <th class="text-end">{{ __('ui.billing.quantity') }}</th>
                            <th class="text-end">{{ __('ui.billing.unit_price') }}</th>
                            <th class="text-end">{{ __('ui.common.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->items as $item)
                            <tr>
                                <td class="text-sm">{{ $item->description }}</td>
                                <td class="text-end font-mono text-xs">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ','), '0'), '.') }}</td>
                                <td class="text-end font-mono text-xs">{{ money((float) $item->unit_price, $invoice->currency) }}</td>
                                <td class="text-end font-mono text-xs">{{ money((float) $item->amount, $invoice->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end border-t border-ink-950/10 p-6 sm:p-8">
                <div class="w-full max-w-xs space-y-2 text-xs">
                    <div class="flex justify-between gap-4">
                        <span class="text-ink-400">{{ __('ui.common.subtotal') }}</span>
                        <span class="font-mono">{{ money((float) $invoice->subtotal, $invoice->currency) }}</span>
                    </div>
                    @if ((float) $invoice->discount > 0)
                        <div class="flex justify-between gap-4">
                            <span class="text-ink-400">{{ __('ui.common.discount') }}</span>
                            <span class="font-mono text-emerald-600">− {{ money((float) $invoice->discount, $invoice->currency) }}</span>
                        </div>
                    @endif
                    @if ((float) $invoice->tax > 0)
                        <div class="flex justify-between gap-4">
                            <span class="text-ink-400">{{ __('ui.common.tax') }}</span>
                            <span class="font-mono">{{ money((float) $invoice->tax, $invoice->currency) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between gap-4 border-t border-ink-950/10 pt-2 text-sm font-semibold">
                        <span>{{ __('ui.common.total') }}</span>
                        <span class="font-mono">{{ $invoice->formattedTotal() }}</span>
                    </div>
                    @if ((float) $invoice->amount_paid > 0)
                        <div class="flex justify-between gap-4">
                            <span class="text-ink-400">{{ __('ui.common.paid') }}</span>
                            <span class="font-mono text-emerald-600">− {{ money((float) $invoice->amount_paid, $invoice->currency) }}</span>
                        </div>
                    @endif
                </div>
            </div>

            @if ($invoice->notes)
                <div class="border-t border-ink-950/10 bg-paper/60 p-6 sm:p-8">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-ink-400">{{ __('ui.billing.notes') }}</p>
                    <p class="mt-2 whitespace-pre-line text-xs leading-6 text-ink-600">{{ $invoice->notes }}</p>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            @if (! $invoice->isPaid() && $invoice->status === \App\Models\Invoice::STATUS_OPEN)
                <div class="card card-pad">
                    <h2 class="font-semibold">{{ __('ui.admin.invoices.mark_paid') }}</h2>
                    <p class="mt-1 text-xs leading-5 text-ink-400">{{ __('ui.admin.invoices.mark_paid_hint') }}</p>
                    <form method="POST" action="{{ route('admin.invoices.paid', $invoice) }}" class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <label class="label" for="amount">{{ __('ui.common.amount') }}</label>
                            <input id="amount" type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount', (float) $invoice->balanceDue()) }}" class="input h-10 font-mono @error('amount') input-error @enderror" dir="ltr">
                            @error('amount')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="label" for="reference">{{ __('ui.admin.invoices.reference') }}</label>
                            <input id="reference" type="text" name="reference" value="{{ old('reference') }}" maxlength="190" class="input h-10 font-mono text-xs" dir="ltr" placeholder="BANK-0001">
                        </div>
                        <button type="submit" class="btn btn-primary h-10 w-full">
                            <x-icon name="check-circle" class="size-4" />
                            {{ __('ui.admin.invoices.mark_paid') }}
                        </button>
                    </form>
                </div>
            @endif

            <div class="card p-5">
                <h2 class="font-semibold">{{ __('ui.billing.summary') }}</h2>
                <div class="mt-4 space-y-3 text-xs">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.common.status') }}</span>
                        <x-status :status="$invoice->status" />
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.common.total') }}</span>
                        <span class="font-mono">{{ $invoice->formattedTotal() }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.common.paid') }}</span>
                        <span class="font-mono">{{ money((float) $invoice->amount_paid, $invoice->currency) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3 border-t border-ink-950/10 pt-3">
                        <span class="text-ink-400">{{ __('ui.billing.balance_due') }}</span>
                        <span class="font-mono font-semibold">{{ $invoice->formattedBalance() }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.billing.paid_at') }}</span>
                        <span class="font-mono">{{ $invoice->paid_at?->translatedFormat('d M Y') ?? '—' }}</span>
                    </div>
                </div>
                @if ($invoice->stripe_hosted_invoice_url)
                    <a href="{{ $invoice->stripe_hosted_invoice_url }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm mt-4 w-full">
                        <x-icon name="external" class="size-3.5" />
                        Stripe
                    </a>
                @endif
            </div>

            <div class="card overflow-hidden">
                <div class="border-b border-ink-950/10 p-5">
                    <h2 class="font-semibold">{{ __('ui.billing.payments') }}</h2>
                </div>
                @forelse ($invoice->payments as $payment)
                    <div class="border-b border-ink-950/[0.06] p-4 last:border-0">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-mono text-xs font-semibold">{{ money((float) $payment->amount, $payment->currency) }}</span>
                            <x-status :status="$payment->status" />
                        </div>
                        <div class="mt-1 flex items-center justify-between gap-3 text-[10px] text-ink-400">
                            <span>{{ ucfirst($payment->gateway) }}</span>
                            <span>{{ $payment->paid_at?->translatedFormat('d M Y') ?? $payment->created_at?->translatedFormat('d M Y') }}</span>
                        </div>
                        @if ($payment->gateway_reference)
                            <p class="mt-1 truncate font-mono text-[10px] text-ink-300" dir="ltr">{{ $payment->gateway_reference }}</p>
                        @endif
                    </div>
                @empty
                    <p class="p-6 text-center text-xs text-ink-400">{{ __('ui.billing.no_payments') }}</p>
                @endforelse
            </div>

            <div class="rounded-3xl border border-ink-950/10 bg-white p-5">
                <div class="flex items-start gap-3">
                    <x-icon name="user" class="mt-0.5 size-5 text-ink-300" />
                    <div class="min-w-0">
                        <p class="text-xs font-semibold">{{ __('ui.admin.organizations.owner') }}</p>
                        <p class="mt-1 truncate text-[11px] text-ink-500">{{ $organization->owner?->name ?? '—' }}</p>
                        <p class="truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $organization->owner?->email }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
