@extends('layouts.admin')

@section('title', __('ui.admin.payments.title'))
@section('page', __('ui.admin.nav.payments'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.payments.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.payments.subtitle') }}</p>
        </div>
        <span class="badge badge-gray">{{ $payments->total() }}</span>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="card p-5">
            <p class="stat-label">{{ __('ui.admin.payments.succeeded') }}</p>
            <p class="stat-value mt-2" dir="ltr">{{ money($stats['succeeded']) }}</p>
        </div>
        <div class="card p-5">
            <p class="stat-label">{{ __('ui.admin.payments.pending') }}</p>
            <p class="stat-value mt-2" dir="ltr">{{ money($stats['pending']) }}</p>
        </div>
        <div class="card p-5">
            <p class="stat-label">{{ __('ui.admin.payments.refunded') }}</p>
            <p class="stat-value mt-2" dir="ltr">{{ money($stats['refunded']) }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.payments.index') }}" class="flex flex-wrap items-center gap-2 rounded-2xl border border-ink-950/10 bg-white p-3">
        <div class="relative min-w-[220px] flex-1">
            <x-icon name="search" class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-400" />
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('ui.admin.payments.reference') }}" class="input h-10 ps-10 font-mono text-xs">
        </div>
        <select name="gateway" class="select h-10 w-36">
            <option value="">{{ __('ui.common.all') }} — {{ __('ui.billing.gateway') }}</option>
            <option value="stripe" @selected($gateway === 'stripe')>Stripe</option>
            <option value="paypal" @selected($gateway === 'paypal')>PayPal</option>
            <option value="bank_transfer" @selected($gateway === 'bank_transfer')>{{ __('ui.billing.bank_transfer') }}</option>
            <option value="manual" @selected($gateway === 'manual')>{{ __('ui.billing.manual') }}</option>
            <option value="wallet" @selected($gateway === 'wallet')>{{ __('ui.billing.wallet') }}</option>
        </select>
        <select name="status" class="select h-10 w-36">
            <option value="">{{ __('ui.common.all') }} — {{ __('ui.common.status') }}</option>
            @foreach (['succeeded', 'pending', 'failed', 'refunded'] as $option)
                <option value="{{ $option }}" @selected($status === $option)>{{ __('ui.statuses.'.$option) }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary btn-sm h-10 px-4">{{ __('ui.common.filter') }}</button>
        @if ($search !== '' || $status !== '' || $gateway !== '')
            <a href="{{ route('admin.payments.index') }}" class="text-xs font-medium text-ink-400 hover:text-ink-950">{{ __('ui.common.reset') }}</a>
        @endif
    </form>

    <div class="card overflow-hidden">
        @if ($payments->isEmpty())
            <div class="p-12 text-center text-sm text-ink-400">{{ __('ui.common.no_results') }}</div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.admin.payments.date') }}</th>
                            <th>{{ __('ui.common.invoice') }}</th>
                            <th>{{ __('ui.admin.organizations.title') }}</th>
                            <th>{{ __('ui.billing.gateway') }}</th>
                            <th>{{ __('ui.admin.payments.reference') }}</th>
                            <th class="text-end">{{ __('ui.common.amount') }}</th>
                            <th>{{ __('ui.common.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td class="whitespace-nowrap text-xs text-ink-400">{{ $payment->paid_at?->translatedFormat('d M Y H:i') ?? $payment->created_at?->translatedFormat('d M Y H:i') }}</td>
                                <td>
                                    @if ($payment->invoice)
                                        <a href="{{ route('admin.invoices.show', $payment->invoice) }}" class="font-mono text-xs font-semibold hover:underline">{{ $payment->invoice->number }}</a>
                                    @else
                                        <span class="text-xs text-ink-400">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($payment->organization)
                                        <a href="{{ route('admin.organizations.show', $payment->organization) }}" class="block truncate text-xs font-medium hover:underline">{{ $payment->organization->name }}</a>
                                        <p class="text-[10px] text-ink-400">{{ $payment->user?->name }}</p>
                                    @else
                                        <span class="text-xs text-ink-400">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-gray">{{ $payment->gatewayLabel() }}</span>
                                </td>
                                <td class="max-w-[180px] truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $payment->gateway_reference ?: '—' }}</td>
                                <td class="text-end font-mono text-xs font-semibold">{{ money((float) $payment->amount, $payment->currency) }}</td>
                                <td><x-status :status="$payment->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $payments])
        @endif
    </div>
@endsection
