@extends('layouts.dashboard')

@section('title', __('ui.support.title'))
@section('page', __('ui.dashboard.nav.support'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.support.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.support.subtitle') }}</p>
        </div>
        <a href="{{ route('dashboard.support.create') }}" class="btn btn-primary h-11 gap-2 px-5">
            <x-icon name="plus" class="size-4" />
            {{ __('ui.support.new_ticket') }}
        </a>
    </div>

    @if (! $enabled)
        <div class="alert alert-warning">
            <x-icon name="alert" class="mt-0.5 size-4" />
            <p class="text-sm">{{ __('ui.support.disabled') }}</p>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            [__('ui.support.stats.total'), $stats['total'], 'message'],
            [__('ui.support.stats.awaiting'), $stats['awaiting'], 'clock'],
            [__('ui.support.stats.resolved'), $stats['resolved'], 'check-circle'],
        ] as [$label, $value, $icon])
            <div class="card p-5">
                <div class="flex items-start justify-between">
                    <span class="stat-label">{{ $label }}</span>
                    <span class="grid size-8 place-items-center rounded-lg bg-paper text-ink-500"><x-icon :name="$icon" class="size-4" /></span>
                </div>
                <p class="stat-value mt-3">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="card overflow-hidden">
        <div class="border-b border-ink-950/10 p-5">
            <h2 class="font-semibold">{{ __('ui.support.title') }}</h2>
        </div>

        @if ($tickets->isEmpty())
            <div class="p-12 text-center">
                <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-paper text-ink-300"><x-icon name="message" class="size-6" /></span>
                <p class="mt-3 text-sm text-ink-400">{{ __('ui.support.empty') }}</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.support.reference') }}</th>
                            <th>{{ __('ui.support.subject') }}</th>
                            <th>{{ __('ui.support.status') }}</th>
                            <th>{{ __('ui.support.updated_at') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tickets as $ticket)
                            <tr>
                                <td class="whitespace-nowrap font-mono text-xs text-ink-500" dir="ltr">{{ $ticket->reference }}</td>
                                <td class="max-w-[360px]">
                                    <a href="{{ route('dashboard.support.show', $ticket) }}" class="block truncate text-sm font-medium hover:underline">{{ $ticket->subject }}</a>
                                    <div class="mt-1 flex items-center gap-2">
                                        <x-ticket-priority :priority="$ticket->priority" />
                                        <span class="text-[10px] text-ink-400">{{ __('ui.support.categories.'.$ticket->category) }}</span>
                                    </div>
                                </td>
                                <td><x-ticket-status :status="$ticket->status" /></td>
                                <td class="whitespace-nowrap text-xs text-ink-400">{{ ($ticket->last_reply_at ?? $ticket->created_at)?->translatedFormat('d M Y') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.support.show', $ticket) }}" class="btn btn-ghost btn-sm">{{ __('ui.common.view') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
