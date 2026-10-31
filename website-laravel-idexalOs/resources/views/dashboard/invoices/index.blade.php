@extends('layouts.dashboard')

@section('title', __('ui.billing.invoices'))
@section('page', __('ui.dashboard.nav.invoices'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.billing.invoices') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.billing.invoices_subtitle') }}</p>
        </div>
        <a href="{{ route('dashboard.billing') }}" class="btn btn-outline btn-sm h-10 px-4">
            <x-icon name="card" class="size-4" />
            {{ __('ui.billing.title') }}
        </a>
    </div>

    <div class="card overflow-hidden">
        @if ($invoices->isEmpty())
            <div class="p-12 text-center">
                <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-paper text-ink-300"><x-icon name="file" class="size-6" /></span>
                <p class="mt-3 text-sm font-semibold">{{ __('ui.billing.no_invoices') }}</p>
                <p class="mt-1 text-xs text-ink-400">{{ __('ui.billing.invoices_subtitle') }}</p>
                <a href="{{ route('pricing') }}" class="btn btn-outline btn-sm mt-5">{{ __('ui.nav.pricing') }}</a>
            </div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.common.invoice') }}</th>
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
                                <td>
                                    <a href="{{ route('dashboard.invoices.show', $invoice) }}" class="font-mono text-xs font-semibold text-ink-950 hover:underline">{{ $invoice->number }}</a>
                                    @if ($invoice->period_start && $invoice->period_end)
                                        <p class="mt-0.5 text-[10px] text-ink-400">{{ $invoice->period_start->translatedFormat('d M') }} — {{ $invoice->period_end->translatedFormat('d M Y') }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-xs">{{ $invoice->issued_at?->translatedFormat('d M Y') ?? $invoice->created_at?->translatedFormat('d M Y') }}</td>
                                <td class="whitespace-nowrap text-xs {{ $invoice->isOverdue() ? 'font-medium text-red-600' : 'text-ink-500' }}">
                                    {{ $invoice->due_at?->translatedFormat('d M Y') ?? '—' }}
                                </td>
                                <td><x-status :status="$invoice->status" /></td>
                                <td class="text-end font-mono text-xs">{{ $invoice->formattedTotal() }}</td>
                                <td class="text-end font-mono text-xs {{ $invoice->balanceDue() > 0 && $invoice->isOpen() ? 'text-red-600' : 'text-ink-400' }}">
                                    {{ $invoice->formattedBalance() }}
                                </td>
                                <td class="text-end">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('dashboard.invoices.show', $invoice) }}" class="btn btn-ghost btn-sm">{{ __('ui.common.view') }}</a>
                                        <a href="{{ route('dashboard.invoices.download', $invoice) }}" class="btn btn-ghost btn-sm" aria-label="{{ __('ui.common.download') }}">
                                            <x-icon name="download" class="size-3.5" />
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $invoices])
        @endif
    </div>
@endsection
