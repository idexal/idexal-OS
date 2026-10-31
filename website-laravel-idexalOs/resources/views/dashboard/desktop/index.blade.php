@extends('layouts.dashboard')

@section('title', __('ui.desktop.title'))
@section('page', __('ui.dashboard.nav.desktop'))

@php
    $activeSessions = $sessions->whereNull('revoked_at');
@endphp

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.desktop.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.desktop.subtitle') }}</p>
        </div>
        <a href="{{ route('desktop.authorize') }}" class="btn btn-primary btn-sm h-10 px-4">
            <x-icon name="monitor" class="size-4" />
            {{ __('ui.desktop.connect') }}
        </a>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="card p-5">
            <p class="stat-label">{{ __('ui.desktop.active_sessions') }}</p>
            <p class="stat-value mt-2">{{ $activeSessions->count() }}</p>
            <p class="mt-2 text-[10px] text-ink-400">{{ __('ui.desktop.subtitle') }}</p>
        </div>
        <div class="card p-5">
            <p class="stat-label">{{ __('ui.desktop.total_sessions') }}</p>
            <p class="stat-value mt-2">{{ $sessions->count() }}</p>
            <p class="mt-2 text-[10px] text-ink-400">{{ __('ui.desktop.sessions_hint') }}</p>
        </div>
        <div class="relative overflow-hidden rounded-3xl bg-ink-950 p-5 text-paper">
            <div class="pointer-events-none absolute -end-6 -top-6 size-24 rounded-full bg-lime/15 blur-2xl"></div>
            <p class="relative text-xs font-medium text-paper/50">{{ __('ui.desktop.app_title') }}</p>
            <p class="relative mt-2 text-sm font-semibold">{{ __('ui.desktop.app_body') }}</p>
            <a href="{{ config('idexal.brand.github') }}/releases" target="_blank" rel="noopener" class="relative mt-4 inline-flex items-center gap-1.5 text-[11px] font-semibold text-lime">
                {{ __('ui.desktop.download') }}
                <x-icon name="external" class="size-3" />
            </a>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="border-b border-ink-950/10 p-5">
            <h2 class="font-semibold">{{ __('ui.desktop.sessions') }}</h2>
            <p class="mt-0.5 text-xs text-ink-400">{{ __('ui.desktop.sessions_hint') }}</p>
        </div>

        @if ($sessions->isEmpty())
            <div class="p-12 text-center">
                <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-paper text-ink-300"><x-icon name="monitor" class="size-6" /></span>
                <p class="mt-3 text-sm font-semibold">{{ __('ui.desktop.no_sessions') }}</p>
                <p class="mt-1 text-xs text-ink-400">{{ __('ui.desktop.connect_hint') }}</p>
                <a href="{{ route('desktop.authorize') }}" class="btn btn-primary btn-sm mt-5">{{ __('ui.desktop.connect') }}</a>
            </div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.desktop.device') }}</th>
                            <th>{{ __('ui.desktop.user') }}</th>
                            <th>{{ __('ui.desktop.platform') }}</th>
                            <th>{{ __('ui.desktop.version') }}</th>
                            <th>{{ __('ui.desktop.last_seen') }}</th>
                            <th>{{ __('ui.common.status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sessions as $session)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-paper text-ink-500"><x-icon name="monitor" class="size-4" /></span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-ink-950">{{ $session->device_name ?: __('ui.desktop.device') }}</p>
                                            <p class="truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $session->apiKey?->maskedPrefix() ?? $session->ip }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <p class="text-xs font-medium">{{ $session->user?->name }}</p>
                                    <p class="truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $session->user?->email }}</p>
                                </td>
                                <td class="text-xs capitalize text-ink-500">{{ $session->platform ?: '—' }}</td>
                                <td class="font-mono text-xs">{{ $session->app_version ?: '—' }}</td>
                                <td class="whitespace-nowrap text-xs text-ink-400">{{ $session->last_seen_at?->diffForHumans() ?? '—' }}</td>
                                <td>
                                    @if ($session->revoked_at)
                                        <span class="badge badge-gray">{{ __('ui.statuses.revoked') }}</span>
                                    @else
                                        <span class="badge badge-green">{{ __('ui.common.active') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($canManage || $session->user_id === auth()->id())
                                        @if ($session->revoked_at === null)
                                            <form
                                                method="POST"
                                                action="{{ route('dashboard.desktop.destroy', $session) }}"
                                                x-data
                                                @submit="if (! confirm(@js(__('ui.desktop.confirm_revoke')))) { $event.preventDefault(); }"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-ghost btn-sm text-red-600 hover:bg-red-50">{{ __('ui.desktop.revoke') }}</button>
                                            </form>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <div class="card card-pad">
            <div class="flex items-start gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-lime/20 text-ink-950"><x-icon name="link" class="size-5" /></span>
                <div>
                    <h2 class="font-semibold">{{ __('ui.desktop.how_it_works') }}</h2>
                    <p class="mt-1 text-xs leading-6 text-ink-400">{{ __('ui.desktop.authorize_subtitle') }}</p>
                </div>
            </div>
            <ol class="mt-5 space-y-4">
                @foreach (['step1', 'step2', 'step3'] as $index => $step)
                    <li class="flex gap-3">
                        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-ink-950 font-mono text-[10px] text-lime">{{ $index + 1 }}</span>
                        <p class="pt-1 text-xs leading-6 text-ink-600">{{ __('ui.desktop.'.$step) }}</p>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="rounded-3xl border border-ink-950/10 bg-white p-6">
            <div class="flex items-start gap-3">
                <x-icon name="shield" class="mt-0.5 size-5 text-lime-dark" />
                <div>
                    <h2 class="font-semibold">{{ __('ui.desktop.security_title') }}</h2>
                    <p class="mt-1 text-xs leading-6 text-ink-400">{{ __('ui.desktop.security_body') }}</p>
                </div>
            </div>
            <ul class="mt-4 space-y-2.5 text-xs text-ink-500">
                @foreach (['permission_profile', 'permission_gateway', 'permission_revoke'] as $permission)
                    <li class="flex items-start gap-2">
                        <x-icon name="check-circle" class="mt-0.5 size-3.5 text-lime-dark" />
                        {{ __('ui.desktop.'.$permission) }}
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('docs') }}#desktop" class="mt-5 inline-flex items-center gap-1.5 text-xs font-semibold text-ink-950">
                {{ __('ui.docs.desktop') }}
                <x-icon name="arrow-left" class="size-3.5 rtl:rotate-180" />
            </a>
        </div>
    </div>
@endsection
