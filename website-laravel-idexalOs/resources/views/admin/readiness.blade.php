@extends('layouts.admin')

@section('title', __('ui.admin.readiness.title'))
@section('page', __('ui.admin.nav.readiness'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.readiness.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.readiness.subtitle') }}</p>
        </div>
        <span class="badge badge-gray" dir="ltr">{{ $checkedAt->toDateTimeString() }}</span>
    </div>

    <div class="mt-6 grid gap-3 sm:grid-cols-3">
        @foreach ([
            ['ok', __('ui.admin.readiness.ok')],
            ['warn', __('ui.admin.readiness.warn')],
            ['fail', __('ui.admin.readiness.fail')],
        ] as [$status, $label])
            <div class="rounded-2xl border border-ink-950/10 bg-white p-4">
                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-ink-400">{{ $label }}</p>
                <p class="mt-1 font-mono text-2xl font-medium" data-summary="{{ $status }}">{{ $counts[$status] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-8 grid gap-3">
        @foreach ($checks as $check)
            @php
                $tone = match ($check['status']) {
                    'ok' => 'border-lime-dark/30 bg-white',
                    'warn' => 'border-amber-500/30 bg-amber-50/60',
                    default => 'border-red-500/30 bg-red-50/60',
                };
                $dot = match ($check['status']) {
                    'ok' => 'bg-lime-dark',
                    'warn' => 'bg-amber-500',
                    default => 'bg-red-500',
                };
            @endphp

            <article class="rounded-2xl border p-4 {{ $tone }}" data-check="{{ $check['id'] }}" data-status="{{ $check['status'] }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $dot }}" aria-hidden="true"></span>
                        <div class="min-w-0">
                            <h2 class="text-sm font-semibold text-ink-950">{{ $check['label'] }}</h2>
                            <p class="mt-1 text-sm leading-6 text-ink-500">{{ $check['hint'] }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-lg bg-white/70 px-2 py-1 font-mono text-[11px] text-ink-700" dir="ltr">{{ $check['value'] }}</span>
                        <span class="badge {{ $check['status'] === 'ok' ? 'border-lime-dark/30 text-lime-dark' : ($check['status'] === 'warn' ? 'border-amber-500/40 text-amber-700' : 'border-red-500/40 text-red-700') }}">
                            {{ __('ui.admin.readiness.'.$check['status']) }}
                        </span>
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    <p class="mt-6 max-w-3xl text-xs leading-6 text-ink-400">{{ __('ui.admin.readiness.footer') }}</p>
@endsection
