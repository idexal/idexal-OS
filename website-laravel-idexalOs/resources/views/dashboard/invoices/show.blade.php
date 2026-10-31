@extends('layouts.dashboard')

@section('title', $invoice->number)
@section('page', __('ui.dashboard.nav.invoices'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a href="{{ route('dashboard.invoices.index') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-400 transition hover:text-ink-950">
                <x-icon name="arrow-right" class="size-3.5 rtl:rotate-180" />
                {{ __('ui.billing.invoices') }}
            </a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="font-mono text-2xl font-semibold tracking-tight sm:text-3xl">{{ $invoice->number }}</h1>
                <x-status :status="$invoice->status" />
                @if ($invoice->isOverdue())
                    <span class="badge badge-red">{{ __('ui.billing.overdue') }}</span>
                @endif
            </div>
            <p class="mt-1.5 text-sm text-ink-500">{{ $invoice->issued_at?->translatedFormat('d F Y') }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('dashboard.invoices.download', $invoice) }}" class="btn btn-outline btn-sm h-10 px-4">
                <x-icon name="download" class="size-4" />
                {{ __('ui.common.download') }} PDF
            </a>
            @if ($invoice->isOpen() && $invoice->balanceDue() > 0 && $stripeEnabled)
                <form method="POST" action="{{ route('dashboard.invoices.pay', $invoice) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm h-10 px-4">
                        <x-icon name="card" class="size-4" />
                        {{ __('ui.billing.pay_now') }}
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if ($invoice->stripe_hosted_invoice_url)
        <div class="alert alert-info">
            <x-icon name="external" class="mt-0.5 size-4" />
            <a href="{{ $invoice->stripe_hosted_invoice_url }}" target="_blank" rel="noopener" class="text-sm font-medium underline underline-offset-4">{{ __('ui.billing.open_stripe_invoice') }}</a>
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-6 border-b border-ink-950/10 p-6 sm:p-8">
                <div>
                    <img src="{{ asset('brand/logo-light.png') }}" alt="{{ config('idexal.brand.name') }}" class="h-8 w-auto">
                    <p class="mt-3 text-[11px] leading-5 text-ink-400">
                        {{ config('idexal.brand.legal_name') }}<br>
                        <span dir="ltr">{{ config('idexal.brand.domain') }}</span><br>
                        <span dir="ltr">{{ config('idexal.brand.support_email') }}</span>
                    </p>
                </div>
                <div class="text-end">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-ink-400">{{ __('ui.common.invoice') }}</p>
                    <p class="mt-1 font-mono text-sm font-semibold" dir="ltr">{{ $invoice->number }}</p>
                    <p class="mt-3 text-[10px] uppercase tracking-[0.14em] text-ink-400">{{ __('ui.billing.issued') }}</p>
                    <p class="mt-0.5 text-xs">{{ $invoice->issued_at?->translatedFormat('d M Y') ?? '—' }}</p>
                    <p class="mt-2 text-[10px] uppercase tracking-[0.14em] text-ink-400">{{ __('ui.billing.due') }}</p>
                    <p class="mt-0.5 text-xs">{{ $invoice->due_at?->translatedFormat('d M Y') ?? '—' }}</p>
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
                        @if ($organization->address_line_2)
                            {{ $organization->address_line_2 }}<br>
                        @endif
                        @if ($organization->city || $organization->country)
                            {{ collect([$organization->city, $organization->country])->filter()->implode(', ') }}<br>
                        @endif
                        @if ($organization->billing_email)
                            <span dir="ltr">{{ $organization->billing_email }}</span>
                        @endif
                    </p>
                    @if ($organization->tax_id)
                        <p class="mt-2 text-[11px] text-ink-400">VAT / Tax ID: <span class="font-mono" dir="ltr">{{ $organization->tax_id }}</span></p>
                    @endif
                </div>
                <div class="sm:text-end">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-ink-400">{{ __('ui.billing.summary') }}</p>
                    <p class="mt-2 font-mono text-2xl font-medium" dir="ltr">{{ $invoice->formattedTotal() }}</p>
                    <p class="mt-1 text-[11px] text-ink-400">{{ $invoice->currency }}</p>
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
                                <td>
                                    <p class="text-sm font-medium text-ink-950">{{ $item->description }}</p>
                                    @if (isset($item->metadata['period']))
                                        <p class="mt-0.5 text-[10px] text-ink-400">{{ $item->metadata['period'] }}</p>
                                    @endif
                                </td>
                                <td class="text-end font-mono text-xs">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ','), '0'), '.') }}</td>
                                <td class="text-end font-mono text-xs">{{ money((float) $item->unit_price, $invoice->currency) }}</td>
                                <td class="text-end font-mono text-xs font-medium">{{ money((float) $item->amount, $invoice->currency) }}</td>
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
                        <div class="flex justify-between gap-4 border-t border-ink-950/10 pt-2 font-semibold">
                            <span>{{ __('ui.billing.balance_due') }}</span>
                            <span class="font-mono">{{ $invoice->formattedBalance() }}</span>
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
            <div class="card p-5">
                <h2 class="font-semibold">{{ __('ui.billing.payment_summary') }}</h2>
                <div class="mt-4 space-y-3 text-xs">
                    <div class="flex justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.common.status') }}</span>
                        <x-status :status="$invoice->status" />
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.billing.balance_due') }}</span>
                        <span class="font-mono font-semibold" dir="ltr">{{ $invoice->formattedBalance() }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.billing.paid_at') }}</span>
                        <span class="font-mono">{{ $invoice->paid_at?->translatedFormat('d M Y') ?? '—' }}</span>
                    </div>
                </div>

                @if ($invoice->isOpen() && $invoice->balanceDue() > 0)
                    <div class="mt-5 space-y-2.5">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-ink-400">{{ __('ui.billing.payment_options') }}</p>

                        @if ($stripeEnabled)
                            <form method="POST" action="{{ route('dashboard.invoices.pay', $invoice) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary h-10 w-full">
                                    <x-icon name="card" class="size-4" />
                                    {{ __('ui.billing.pay_card') }}
                                </button>
                            </form>
                        @endif

                        @if ($paypalEnabled)
                            <form method="POST" action="{{ route('dashboard.invoices.paypal', $invoice) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline h-10 w-full">
                                    <x-icon name="external" class="size-4" />
                                    {{ __('ui.billing.pay_paypal') }}
                                </button>
                            </form>
                        @endif

                        @if ($bankTransferEnabled)
                            <form method="POST" action="{{ route('dashboard.invoices.bank-transfer', $invoice) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline h-10 w-full">
                                    <x-icon name="building" class="size-4" />
                                    {{ __('ui.billing.pay_bank_transfer') }}
                                </button>
                            </form>
                        @endif

                        @if (! $stripeEnabled && ! $paypalEnabled && ! $bankTransferEnabled)
                            <form method="POST" action="{{ route('dashboard.invoices.pay', $invoice) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary h-10 w-full">{{ __('ui.billing.pay_now') }}</button>
                            </form>
                        @endif
                    </div>

                    @if ($bankTransferEnabled && $bankTransferDetails !== [])
                        <div class="mt-4 rounded-2xl bg-paper p-4">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-ink-400">{{ __('ui.billing.bank_transfer_details') }}</p>
                            <dl class="mt-3 space-y-2 text-[11px]">
                                @foreach ($bankTransferDetails as $key => $value)
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-ink-400">{{ __('ui.billing.bank_fields.'.$key) }}</dt>
                                        <dd class="text-end font-mono" dir="ltr">{{ $value }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                            <p class="mt-3 text-[11px] leading-5 text-ink-400">{{ __('ui.billing.bank_transfer_reference_hint') }}</p>
                            <p class="mt-1 font-mono text-xs font-semibold" dir="ltr">{{ $bankTransferReference }}</p>
                        </div>
                    @endif
                @endif
            </div>

            <div class="card overflow-hidden">
                <div class="border-b border-ink-950/10 p-5">
                    <h2 class="font-semibold">{{ __('ui.billing.payments') }}</h2>
                </div>

                @if ($invoice->payments->isEmpty())
                    <p class="p-6 text-center text-xs text-ink-400">{{ __('ui.billing.no_payments') }}</p>
                @else
                    <div class="divide-y divide-ink-950/[0.06]">
                        @foreach ($invoice->payments as $payment)
                            <div class="p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-mono text-xs font-semibold">{{ money((float) $payment->amount, $payment->currency) }}</span>
                                    <x-status :status="$payment->status" />
                                </div>
                                <div class="mt-1 flex items-center justify-between gap-3 text-[10px] text-ink-400">
                                    <span>{{ __('ui.billing.gateway') }}: {{ $payment->gatewayLabel() }}</span>
                                    <span>{{ $payment->paid_at?->translatedFormat('d M Y') ?? $payment->created_at?->translatedFormat('d M Y') }}</span>
                                </div>
                                @if ($payment->gateway_reference)
                                    <p class="mt-1 truncate font-mono text-[10px] text-ink-300" dir="ltr">{{ $payment->gateway_reference }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="rounded-3xl border border-ink-950/10 bg-white p-5">
                <div class="flex items-start gap-3">
                    <x-icon name="shield" class="mt-0.5 size-5 text-lime-dark" />
                    <div>
                        <p class="text-xs font-semibold">{{ __('ui.billing.payment_instructions') }}</p>
                        <p class="mt-1 text-[11px] leading-5 text-ink-400">{{ __('ui.billing.manual_note') }}</p>
                        <a href="{{ route('contact') }}" class="mt-3 inline-flex items-center gap-1.5 text-[11px] font-semibold text-ink-950">
                            {{ __('ui.nav.contact') }}
                            <x-icon name="arrow-left" class="size-3 rtl:rotate-180" />
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
