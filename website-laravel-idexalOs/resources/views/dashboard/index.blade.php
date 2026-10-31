@extends('layouts.dashboard')

@section('title', __('ui.dashboard.title'))
@section('page', __('ui.dashboard.nav.overview'))

@php
    $planName = $plan
        ? (trans()->has('ui.plans.'.$plan->slug.'.name') ? __('ui.plans.'.$plan->slug.'.name') : $plan->name)
        : __('ui.billing.no_plan');
    $labels = collect($series)->map(fn (array $row) => \Illuminate\Support\Carbon::parse($row['date'])->translatedFormat('d M'))->all();
    $requestSeries = collect($series)->pluck('requests')->all();
    $tokenSeries = collect($series)->pluck('tokens')->all();
    $onboardingDone = $keysCount > 0;
@endphp

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-400">{{ now()->translatedFormat('l، d F Y') }}</p>
            <h1 class="mt-1.5 text-2xl font-semibold tracking-tight sm:text-3xl">
                {{ __('ui.dashboard.welcome', ['name' => auth()->user()?->name]) }}
            </h1>
            <p class="mt-1.5 text-sm text-ink-500">{{ __('ui.dashboard.subtitle') }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($membership?->canManageMembers())
                <a href="{{ route('dashboard.team') }}" class="btn btn-outline btn-sm h-10 px-4">
                    <x-icon name="users" class="size-4" />
                    {{ __('ui.dashboard.invite_member') }}
                </a>
            @endif
            <a href="{{ route('dashboard.api-keys.index') }}" class="btn btn-primary btn-sm h-10 px-4">
                <x-icon name="plus" class="size-4" />
                {{ __('ui.dashboard.create_key') }}
            </a>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-5">
            <div class="flex items-start justify-between">
                <span class="stat-label">{{ __('ui.dashboard.stats.requests') }}</span>
                <span class="grid size-8 place-items-center rounded-lg bg-paper text-ink-500"><x-icon name="zap" class="size-4" /></span>
            </div>
            <p class="stat-value mt-3">{{ compact_number($summary['requests']) }}</p>
            <div class="mt-4 flex items-center justify-between text-[10px] text-ink-400">
                <span>{{ __('ui.common.of') }} {{ $summary['limits']['requests'] === null ? __('ui.common.unlimited') : compact_number($summary['limits']['requests']) }}</span>
                <span class="font-mono">{{ $summary['limits']['requests'] === null ? '∞' : $summary['percentages']['requests'].'%' }}</span>
            </div>
            <div class="progress mt-2"><span style="width: {{ $summary['limits']['requests'] === null ? 0 : max(2, $summary['percentages']['requests']) }}%"></span></div>
        </div>

        <div class="card p-5">
            <div class="flex items-start justify-between">
                <span class="stat-label">{{ __('ui.dashboard.stats.tokens') }}</span>
                <span class="grid size-8 place-items-center rounded-lg bg-paper text-ink-500"><x-icon name="layers" class="size-4" /></span>
            </div>
            <p class="stat-value mt-3">{{ compact_number($summary['total_tokens']) }}</p>
            <div class="mt-4 flex items-center justify-between text-[10px] text-ink-400">
                <span>{{ __('ui.common.of') }} {{ $summary['limits']['tokens'] === null ? __('ui.common.unlimited') : compact_number($summary['limits']['tokens']) }}</span>
                <span class="font-mono">{{ $summary['limits']['tokens'] === null ? '∞' : $summary['percentages']['tokens'].'%' }}</span>
            </div>
            <div class="progress mt-2"><span style="width: {{ $summary['limits']['tokens'] === null ? 0 : max(2, $summary['percentages']['tokens']) }}%"></span></div>
        </div>

        <div class="card p-5">
            <div class="flex items-start justify-between">
                <span class="stat-label">{{ __('ui.dashboard.stats.cost') }}</span>
                <span class="grid size-8 place-items-center rounded-lg bg-paper text-ink-500"><x-icon name="card" class="size-4" /></span>
            </div>
            <p class="stat-value mt-3" dir="ltr">{{ money($summary['cost'], $organization->currency) }}</p>
            <p class="mt-4 text-[10px] text-ink-400">{{ __('ui.usage.current_period') }} · {{ $summary['period'] }}</p>
        </div>

        <div class="relative overflow-hidden rounded-3xl bg-ink-950 p-5 text-paper shadow-card">
            <div class="pointer-events-none absolute -end-6 -top-6 size-24 rounded-full bg-lime/15 blur-2xl"></div>
            <div class="relative flex items-start justify-between">
                <span class="text-xs font-medium text-paper/50">{{ __('ui.dashboard.stats.credits') }}</span>
                <span class="grid size-8 place-items-center rounded-lg bg-white/10 text-lime"><x-icon name="sparkles" class="size-4" /></span>
            </div>
            <p class="relative mt-3 font-mono text-3xl font-medium tracking-tight" dir="ltr">{{ money($summary['credit_balance'], $organization->currency) }}</p>
            <div class="relative mt-4 flex items-center justify-between">
                <a href="{{ route('dashboard.billing') }}" class="text-[10px] font-semibold text-lime hover:text-lime-bright">{{ __('ui.dashboard.top_up') }}</a>
                @if ($summary['over_quota'])
                    <span class="badge border-red-400/20 bg-red-400/10 text-red-300">{{ __('ui.usage.over_quota') }}</span>
                @endif
            </div>
        </div>
    </div>

    @if (! $onboardingDone)
        <div class="card overflow-hidden">
            <div class="grid gap-0 md:grid-cols-[1.1fr_1.9fr]">
                <div class="mesh-dark p-6 text-paper sm:p-7">
                    <span class="badge border-lime/20 bg-lime/10 text-lime">{{ __('ui.dashboard.onboarding_title') }}</span>
                    <h2 class="mt-4 text-xl font-semibold">{{ __('ui.dashboard.onboarding_key') }}</h2>
                    <p class="mt-2 text-xs leading-6 text-paper/45">{{ __('ui.dashboard.onboarding_call') }}</p>
                    <a href="{{ route('dashboard.api-keys.index') }}" class="btn btn-lime btn-sm mt-5">{{ __('ui.dashboard.create_key') }}</a>
                </div>
                <div class="p-6 sm:p-7">
                    <ol class="space-y-4">
                        @foreach ([
                            [__('ui.dashboard.onboarding_key'), __('ui.api_keys.subtitle'), 'key'],
                            [__('ui.dashboard.onboarding_call'), __('ui.docs.chat'), 'terminal'],
                            [__('ui.dashboard.onboarding_team'), __('ui.team.subtitle'), 'users'],
                            [__('ui.dashboard.onboarding_billing'), __('ui.billing.subtitle'), 'card'],
                        ] as $index => [$title, $body, $icon])
                            <li class="flex items-start gap-3">
                                <span class="grid size-8 shrink-0 place-items-center rounded-lg {{ $loop->first ? 'bg-lime text-ink-950' : 'bg-paper text-ink-400' }}">
                                    <x-icon :name="$icon" class="size-4" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold">{{ $title }}</p>
                                    <p class="mt-0.5 text-xs text-ink-400">{{ $body }}</p>
                                </div>
                                @if ($loop->first)
                                    <span class="badge badge-amber">{{ __('ui.common.pending') }}</span>
                                @else
                                    <span class="text-[10px] text-ink-300">{{ __('ui.common.next') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[1.65fr_1fr]">
        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-ink-950/10 p-5">
                <div>
                    <h2 class="font-semibold">{{ __('ui.dashboard.usage_chart') }}</h2>
                    <p class="mt-0.5 text-xs text-ink-400">{{ __('ui.usage.current_period') }} · {{ $summary['period'] }}</p>
                </div>
                <div class="flex items-center gap-4 text-[11px] text-ink-500">
                    <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-ink-950"></span>{{ __('ui.dashboard.requests_label') }}</span>
                    <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-lime-dark"></span>{{ __('ui.dashboard.tokens_thousands') }}</span>
                </div>
            </div>
            <div class="h-[290px] p-4 sm:p-5">
                <canvas id="dashboard-usage"></canvas>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="stat-label">{{ __('ui.dashboard.current_plan') }}</p>
                        <p class="mt-1.5 text-lg font-semibold">{{ $planName }}</p>
                    </div>
                    <span class="badge {{ $plan ? 'badge-lime' : 'badge-gray' }}">{{ $subscription?->status ? __('ui.statuses.'.$subscription->status) : __('ui.billing.free') }}</span>
                </div>

                <div class="mt-5 space-y-3 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-ink-400">{{ __('ui.dashboard.seat_usage') }}</span>
                        <span class="font-mono">{{ $membersCount }} / {{ $summary['limits']['seats'] === null ? '∞' : $summary['limits']['seats'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-ink-400">{{ __('ui.common.api_keys') }}</span>
                        <span class="font-mono">{{ $keysCount }} / {{ $summary['limits']['api_keys'] === null ? '∞' : $summary['limits']['api_keys'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-ink-400">{{ __('ui.billing.next_renewal') }}</span>
                        <span class="font-mono text-[11px]">{{ $subscription?->current_period_end?->translatedFormat('d M Y') ?? __('ui.billing.no_renewal') }}</span>
                    </div>
                </div>

                <div class="mt-5 flex gap-2">
                    <a href="{{ route('dashboard.billing') }}" class="btn btn-outline btn-sm flex-1">{{ __('ui.dashboard.manage_plan') }}</a>
                    <a href="{{ route('pricing') }}" class="btn btn-primary btn-sm flex-1">{{ __('ui.common.upgrade') }}</a>
                </div>
            </div>

            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <p class="stat-label">{{ __('ui.dashboard.desktop_title') }}</p>
                    <span class="flex items-center gap-1.5 text-[10px] font-semibold {{ $desktopSessionsCount > 0 ? 'text-emerald-600' : 'text-ink-400' }}">
                        <span class="size-1.5 rounded-full {{ $desktopSessionsCount > 0 ? 'bg-emerald-500' : 'bg-ink-300' }}"></span>
                        {{ $desktopSessionsCount > 0 ? __('ui.dashboard.desktop_connected') : __('ui.dashboard.desktop_not_connected') }}
                    </span>
                </div>

                @if ($latestDesktopSession)
                    <div class="mt-3 flex items-center gap-3">
                        <span class="grid size-9 place-items-center rounded-xl bg-paper text-ink-500"><x-icon name="monitor" class="size-4" /></span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $latestDesktopSession->device_name ?: __('ui.desktop.device') }}</p>
                            <p class="text-[10px] text-ink-400">{{ __('ui.desktop.last_seen') }}: {{ $latestDesktopSession->last_seen_at?->diffForHumans() }}</p>
                        </div>
                    </div>
                @else
                    <p class="mt-2 text-xs leading-6 text-ink-400">{{ __('ui.desktop.how_it_works') }}</p>
                @endif

                <a href="{{ route('dashboard.desktop') }}" class="mt-4 flex items-center justify-between text-xs font-semibold text-ink-950">
                    {{ $desktopSessionsCount > 0 ? __('ui.dashboard.manage_desktop') : __('ui.dashboard.connect_desktop') }}
                    <x-icon name="arrow-left" class="size-3.5 rtl:rotate-180" />
                </a>
            </div>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-[1.65fr_1fr]">
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-ink-950/10 p-5">
                <h2 class="font-semibold">{{ __('ui.dashboard.recent_requests') }}</h2>
                <a href="{{ route('dashboard.usage') }}" class="text-xs font-semibold text-ink-500 hover:text-ink-950">{{ __('ui.dashboard.view_all') }}</a>
            </div>

            @if ($recentRequests->isEmpty())
                <div class="p-10 text-center">
                    <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-paper text-ink-300"><x-icon name="zap" class="size-6" /></span>
                    <p class="mt-3 text-sm font-semibold">{{ __('ui.dashboard.no_requests') }}</p>
                    <a href="{{ route('docs') }}" class="btn btn-outline btn-sm mt-4">{{ __('ui.docs.quickstart') }}</a>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('ui.developer.time') }}</th>
                                <th>{{ __('ui.common.model') }}</th>
                                <th>{{ __('ui.developer.status_code') }}</th>
                                <th>{{ __('ui.common.tokens') }}</th>
                                <th class="text-end">{{ __('ui.common.latency') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentRequests as $requestRow)
                                <tr>
                                    <td class="whitespace-nowrap text-xs text-ink-400">{{ $requestRow->created_at?->translatedFormat('d M H:i') }}</td>
                                    <td>
                                        <span class="text-sm font-medium text-ink-950">{{ $requestRow->aiModel?->display_name ?? $requestRow->model }}</span>
                                        <span class="block font-mono text-[10px] text-ink-400" dir="ltr">{{ $requestRow->endpoint }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $requestRow->status_code < 400 ? 'badge-green' : 'badge-red' }} font-mono">{{ $requestRow->status_code }}</span>
                                    </td>
                                    <td class="font-mono text-xs">{{ compact_number($requestRow->total_tokens) }}</td>
                                    <td class="text-end font-mono text-xs text-ink-500">{{ $requestRow->latency_ms }}ms</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-ink-950/10 p-5">
                <h2 class="font-semibold">{{ __('ui.dashboard.notifications_title') }}</h2>
                <div class="flex items-center gap-2">
                    @if ($notifications->whereNull('read_at')->isNotEmpty())
                        <form method="POST" action="{{ route('dashboard.notifications.read-all') }}">
                            @csrf
                            <button type="submit" class="text-[10px] font-semibold text-ink-500 hover:text-ink-950">{{ __('ui.dashboard.mark_all_read') }}</button>
                        </form>
                    @endif
                    <a href="{{ route('dashboard.notifications') }}" class="text-ink-400 hover:text-ink-950"><x-icon name="arrow-left" class="size-4 rtl:rotate-180" /></a>
                </div>
            </div>

            @forelse ($notifications as $notification)
                <a href="{{ $notification->url ?: route('dashboard.notifications') }}" class="flex items-start gap-3 border-b border-ink-950/[0.06] p-4 transition last:border-0 hover:bg-paper/60">
                    <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg {{ $notification->read_at ? 'bg-paper text-ink-400' : 'bg-lime/20 text-ink-950' }}">
                        <x-icon name="bell" class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold">{{ $notification->title }}</p>
                        <p class="mt-0.5 line-clamp-2 text-[11px] leading-5 text-ink-400">{{ $notification->body }}</p>
                        <p class="mt-1 text-[10px] text-ink-300">{{ $notification->created_at?->diffForHumans() }}</p>
                    </div>
                    @if (! $notification->read_at)
                        <span class="mt-1 size-1.5 shrink-0 rounded-full bg-lime-dark"></span>
                    @endif
                </a>
            @empty
                <div class="p-10 text-center text-xs text-ink-400">{{ __('ui.dashboard.no_notifications') }}</div>
            @endforelse
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-ink-950/10 p-5">
            <h2 class="font-semibold">{{ __('ui.dashboard.recent_invoices') }}</h2>
            <a href="{{ route('dashboard.invoices.index') }}" class="text-xs font-semibold text-ink-500 hover:text-ink-950">{{ __('ui.dashboard.view_all') }}</a>
        </div>

        @if ($invoices->isEmpty())
            <div class="p-10 text-center text-xs text-ink-400">{{ __('ui.dashboard.no_invoices') }}</div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.common.invoice') }}</th>
                            <th>{{ __('ui.common.date') }}</th>
                            <th>{{ __('ui.common.status') }}</th>
                            <th>{{ __('ui.common.total') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td class="font-mono text-xs font-semibold text-ink-950">{{ $invoice->number }}</td>
                                <td class="text-xs">{{ $invoice->issued_at?->translatedFormat('d M Y') ?? $invoice->created_at?->translatedFormat('d M Y') }}</td>
                                <td><x-status :status="$invoice->status" /></td>
                                <td class="font-mono text-xs">{{ $invoice->formattedTotal() }}</td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.invoices.show', $invoice) }}" class="btn btn-ghost btn-sm">{{ __('ui.common.view') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const canvas = document.getElementById('dashboard-usage');

                if (!canvas) {
                    return;
                }

                window.idexalChart(canvas, {
                    type: 'line',
                    data: {
                        labels: @json($labels),
                        datasets: [
                            {
                                label: @json(__('ui.dashboard.requests_label')),
                                data: @json($requestSeries),
                                borderColor: '#080a08',
                                backgroundColor: 'rgba(125, 255, 0, 0.18)',
                                fill: true,
                                borderWidth: 2,
                                pointRadius: 0,
                                pointHoverRadius: 3,
                                tension: 0.35,
                            },
                            {
                                label: @json(__('ui.dashboard.tokens_thousands')),
                                data: @json(collect($tokenSeries)->map(fn ($value) => (int) round($value / 1000))->all()),
                                borderColor: '#4fae00',
                                backgroundColor: 'transparent',
                                borderWidth: 1.5,
                                borderDash: [4, 4],
                                pointRadius: 0,
                                pointHoverRadius: 3,
                                tension: 0.35,
                            },
                        ],
                    },
                    options: {
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: (context) => `${context.dataset.label}: ${context.parsed.y.toLocaleString('en-US')}`,
                                },
                            },
                        },
                    },
                });
            });
        </script>
    @endpush
@endsection
