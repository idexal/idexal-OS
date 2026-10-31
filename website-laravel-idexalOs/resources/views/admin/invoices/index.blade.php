@extends('layouts.admin')

@section('title', __('ui.admin.invoices.title'))
@section('page', __('ui.admin.nav.invoices'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.invoices.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.invoices.subtitle') }}</p>
        </div>
        <a href="{{ route('admin.invoices.create') }}" class="btn btn-primary btn-sm h-10 px-4">
            <x-icon name="plus" class="size-4" />
            {{ __('ui.admin.invoices.create') }}
        </a>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            [__('ui.admin.invoices.open'), $stats['open'], 'invoice', 'bg-lime/20'],
            [__('ui.admin.invoices.overdue'), $stats['overdue'], 'clock', 'bg-red-50 text-red-600'],
            [__('ui.admin.invoices.paid'), $stats['paid'], 'check-circle', 'bg-emerald-50 text-emerald-600'],
        ] as [$label, $value, $icon, $classes])
            <div class="card flex items-center gap-4 p-5">
                <span class="grid size-10 place-items-center rounded-xl {{ $classes }}"><x-icon :name="$icon" class="size-5" /></span>
                <div>
                    <p class="stat-label">{{ $label }}</p>
                    <p class="mt-1 font-mono text-xl font-semibold">{{ compact_number($value) }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.invoices.index') }}" class="flex flex-wrap items-center gap-2 rounded-2xl border border-ink-950/10 bg-white p-3">
        <div class="relative min-w-[220px] flex-1">
            <x-icon name="search" class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-400" />
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('ui.common.search') }}" class="input h-10 ps-10">
        </div>
        <select name="status" class="select h-10 w-40">
            <option value="">{{ __('ui.common.all') }} — {{ __('ui.common.status') }}</option>
            @foreach (['open', 'paid', 'draft', 'void', 'uncollectible', 'refunded'] as $option)
                <option value="{{ $option }}" @selected($status === $option)>{{ __('ui.billing.invoice_statuses.'.$option) }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary btn-sm h-10 px-4">{{ __('ui.common.filter') }}</button>
        @if ($search !== '' || $status !== '')
            <a href="{{ route('admin.invoices.index') }}" class="text-xs font-medium text-ink-400 hover:text-ink-950">{{ __('ui.common.reset') }}</a>
        @endif
    </form>

    <div class="card overflow-hidden">
        @if ($invoices->isEmpty())
            <div class="p-12 text-center text-sm text-ink-400">{{ __('ui.common.no_results') }}</div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.common.invoice') }}</th>
                            <th>{{ __('ui.admin.organizations.title') }}</th>
                            <th>{{ __('ui.billing.issued') }}</th>
                            <th>{{ __('ui.billing.due') }}</th>
                            <th>{{ __('ui.common.status') }}</th>
                            <th class="text-end">{{ __('ui.common.total') }}</th>
                            <th class="text-end">{{ __('ui.billing.balance_due') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td><a href="{{ route('admin.invoices.show', $invoice) }}" class="font-mono text-xs font-semibold text-ink-950 hover:underline">{{ $invoice->number }}</a></td>
                                <td>
                                    <p class="text-xs font-medium">{{ $invoice->organization?->name }}</p>
                                    <p class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $invoice->organization?->slug }}</p>
                                </td>
                                <td class="whitespace-nowrap text-xs">{{ $invoice->issued_at?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs {{ $invoice->isOverdue() ? 'font-medium text-red-600' : 'text-ink-500' }}">{{ $invoice->due_at?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td><x-status :status="$invoice->status" /></td>
                                <td class="text-end font-mono text-xs">{{ $invoice->formattedTotal() }}</td>
                                <td class="text-end font-mono text-xs">{{ $invoice->formattedBalance() }}</td>
                                <td class="text-end"><a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-ghost btn-sm">{{ __('ui.common.view') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $invoices])
        @endif
    </div>
@endsection
