@extends('layouts.admin')

@section('title', __('ui.admin.dashboard.title'))
@section('page', __('ui.admin.nav.dashboard'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-400">{{ now()->translatedFormat('l، d F Y') }}</p>
            <h1 class="mt-1.5 text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.dashboard.title') }}</h1>
            <p class="mt-1.5 text-sm text-ink-500">{{ __('ui.admin.dashboard.subtitle') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.providers.create') }}" class="btn btn-outline btn-sm h-10 px-4">
                <x-icon name="server" class="size-4" />
                {{ __('ui.admin.providers.create') }}
            </a>
            <a href="{{ route('admin.invoices.create') }}" class="btn btn-primary btn-sm h-10 px-4">
                <x-icon name="plus" class="size-4" />
                {{ __('ui.admin.invoices.create') }}
            </a>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="relative overflow-hidden rounded-3xl bg-ink-950 p-5 text-paper">
            <div class="pointer-events-none absolute -end-6 -top-6 size-24 rounded-full bg-lime/20 blur-2xl"></div>
            <div class="relative flex items-start justify-between">
                <span class="text-xs font-medium text-paper/50">{{ __('ui.admin.dashboard.mrr') }}</span>
                <span class="grid size-8 place-items-center rounded-lg bg-white/10 text-lime"><x-icon name="chart" class="size-4" /></span>
            </div>
            <p class="relative mt-3 font-mono text-3xl font-medium tracking-tight" dir="ltr">{{ money($stats['mrr']) }}</p>
            <p class="relative mt-2 text-[10px] text-paper/40">{{ $stats['activeSubscriptions'] }} {{ __('ui.admin.dashboard.active_subscriptions') }}</p>
        </div>

        <div class="card p-5">
            <div class="flex items-start justify-between">
                <span class="stat-label">{{ __('ui.admin.dashboard.month_revenue') }}</span>
                <span class="grid size-8 place-items-center rounded-lg bg-paper text-ink-500"><x-icon name="card" class="size-4" /></span>
            </div>
            <p class="stat-value mt-3" dir="ltr">{{ money($stats['monthRevenue']) }}</p>
            <p class="mt-2 text-[10px] text-ink-400">{{ now()->translatedFormat('F Y') }}</p>
        </div>

        <div class="card p-5">
            <div class="flex items-start justify-between">
                <span class="stat-label">{{ __('ui.admin.dashboard.organizations') }}</span>
                <span class="grid size-8 place-items-center rounded-lg bg-paper text-ink-500"><x-icon name="building" class="size-4" /></span>
            </div>
            <p class="stat-value mt-3">{{ compact_number($stats['organizations']) }}</p>
            <a href="{{ route('admin.organizations.index') }}" class="mt-2 inline-flex items-center gap-1 text-[10px] font-semibold text-ink-400 hover:text-ink-950">
                {{ __('ui.common.view') }}
                <x-icon name="arrow-left" class="size-3 rtl:rotate-180" />
            </a>
        </div>

        <div class="card p-5">
            <div class="flex items-start justify-between">
                <span class="stat-label">{{ __('ui.admin.dashboard.users') }}</span>
                <span class="grid size-8 place-items-center rounded-lg bg-paper text-ink-500"><x-icon name="users" class="size-4" /></span>
            </div>
            <p class="stat-value mt-3">{{ compact_number($stats['users']) }}</p>
            <p class="mt-2 text-[10px] text-ink-400">{{ $stats['desktopSessions'] }} {{ __('ui.admin.dashboard.desktop_sessions') }}</p>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([
            [__('ui.admin.dashboard.month_requests'), compact_number($stats['monthRequests']), 'zap'],
            [__('ui.admin.dashboard.month_tokens'), compact_number($stats['monthTokens']), 'layers'],
            [__('ui.admin.dashboard.gateway_cost'), money($stats['monthGatewayCost']), 'server'],
            [__('ui.admin.dashboard.open_invoices'), (string) $stats['openInvoices'], 'invoice'],
            [__('ui.admin.dashboard.desktop_sessions'), (string) $stats['desktopSessions'], 'monitor'],
        ] as [$label, $value, $icon])
            <div class="flex items-center gap-3 rounded-2xl border border-ink-950/10 bg-white px-4 py-3">
                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-paper text-ink-500"><x-icon :name="$icon" class="size-4" /></span>
                <div class="min-w-0">
                    <p class="truncate text-[10px] text-ink-400">{{ $label }}</p>
                    <p class="mt-0.5 font-mono text-sm font-semibold" dir="ltr">{{ $value }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="card overflow-hidden xl:col-span-2">
            <div class="flex items-center justify-between border-b border-ink-950/10 p-5">
                <div>
                    <h2 class="font-semibold">{{ __('ui.admin.dashboard.revenue_chart') }}</h2>
                    <p class="mt-0.5 text-xs text-ink-400">{{ __('ui.admin.dashboard.last_6_months') }}</p>
                </div>
                <span class="badge badge-lime">{{ __('ui.common.revenue') }}</span>
            </div>
            <div class="h-[280px] p-4 sm:p-5"><canvas id="admin-revenue"></canvas></div>
        </div>

        <div class="card overflow-hidden">
            <div class="border-b border-ink-950/10 p-5">
                <h2 class="font-semibold">{{ __('ui.admin.dashboard.signups_chart') }}</h2>
                <p class="mt-0.5 text-xs text-ink-400">{{ __('ui.admin.dashboard.last_30_days') }}</p>
            </div>
            <div class="h-[280px] p-4"><canvas id="admin-signups"></canvas></div>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="card overflow-hidden xl:col-span-2">
            <div class="flex items-center justify-between border-b border-ink-950/10 p-5">
                <h2 class="font-semibold">{{ __('ui.admin.dashboard.gateway_chart') }}</h2>
                <a href="{{ route('admin.gateway') }}" class="text-xs font-semibold text-ink-500 hover:text-ink-950">{{ __('ui.common.view') }}</a>
            </div>
            <div class="h-[260px] p-4 sm:p-5"><canvas id="admin-requests"></canvas></div>
        </div>

        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-ink-950/10 p-5">
                <h2 class="font-semibold">{{ __('ui.admin.dashboard.top_organizations') }}</h2>
                <span class="text-[10px] text-ink-400">{{ now()->translatedFormat('F') }}</span>
            </div>
            @forelse ($topOrganizations as $row)
                <div class="flex items-center gap-3 border-b border-ink-950/[0.06] p-4 last:border-0">
                    <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-ink-950 font-mono text-[10px] font-semibold text-lime">
                        {{ $loop->iteration }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <a href="{{ $row->organization ? route('admin.organizations.show', $row->organization) : '#' }}" class="block truncate text-xs font-semibold text-ink-950 hover:underline">{{ $row->organization?->name ?? '—' }}</a>
                        <p class="mt-0.5 text-[10px] text-ink-400">{{ compact_number($row->requests) }} {{ __('ui.common.requests') }} · {{ compact_number($row->tokens) }} {{ __('ui.common.tokens') }}</p>
                    </div>
                    <span class="font-mono text-[11px] font-medium">{{ money((float) $row->cost) }}</span>
                </div>
            @empty
                <p class="p-8 text-center text-xs text-ink-400">{{ __('ui.common.no_data') }}</p>
            @endforelse
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-ink-950/10 p-5">
                <h2 class="font-semibold">{{ __('ui.admin.dashboard.recent_users') }}</h2>
                <a href="{{ route('admin.users.index') }}" class="text-xs font-semibold text-ink-500 hover:text-ink-950">{{ __('ui.common.view') }}</a>
            </div>
            @forelse ($recentUsers as $recentUser)
                <div class="flex items-center gap-3 border-b border-ink-950/[0.06] p-4 last:border-0">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-paper font-mono text-[10px] font-semibold text-ink-600">{{ mb_substr((string) $recentUser->name, 0, 1) }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold">{{ $recentUser->name }}</p>
                        <p class="truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $recentUser->email }}</p>
                    </div>
                    <span class="badge badge-gray">{{ __('ui.roles.'.$recentUser->role) }}</span>
                    <span class="text-[10px] text-ink-300">{{ $recentUser->created_at?->diffForHumans() }}</span>
                </div>
            @empty
                <p class="p-8 text-center text-xs text-ink-400">{{ __('ui.common.no_data') }}</p>
            @endforelse
        </div>

        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-ink-950/10 p-5">
                <h2 class="font-semibold">{{ __('ui.admin.dashboard.recent_invoices') }}</h2>
                <a href="{{ route('admin.invoices.index') }}" class="text-xs font-semibold text-ink-500 hover:text-ink-950">{{ __('ui.common.view') }}</a>
            </div>
            @forelse ($recentInvoices as $recentInvoice)
                <div class="flex items-center gap-3 border-b border-ink-950/[0.06] p-4 last:border-0">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('admin.invoices.show', $recentInvoice) }}" class="font-mono text-xs font-semibold text-ink-950 hover:underline">{{ $recentInvoice->number }}</a>
                        <p class="mt-0.5 truncate text-[10px] text-ink-400">{{ $recentInvoice->organization?->name }}</p>
                    </div>
                    <span class="font-mono text-xs">{{ $recentInvoice->formattedTotal() }}</span>
                    <x-status :status="$recentInvoice->status" />
                </div>
            @empty
                <p class="p-8 text-center text-xs text-ink-400">{{ __('ui.common.no_data') }}</p>
            @endforelse
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-ink-950/10 p-5">
            <h2 class="font-semibold">{{ __('ui.admin.dashboard.recent_requests') }}</h2>
            <a href="{{ route('admin.gateway') }}" class="text-xs font-semibold text-ink-500 hover:text-ink-950">{{ __('ui.common.view') }}</a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('ui.developer.time') }}</th>
                        <th>{{ __('ui.admin.organizations.title') }}</th>
                        <th>{{ __('ui.common.model') }}</th>
                        <th>{{ __('ui.developer.status_code') }}</th>
                        <th>{{ __('ui.common.tokens') }}</th>
                        <th class="text-end">{{ __('ui.common.cost') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentRequests as $recentRequest)
                        <tr>
                            <td class="whitespace-nowrap text-[11px] text-ink-400">{{ $recentRequest->created_at?->translatedFormat('d M H:i') }}</td>
                            <td class="text-xs">{{ $recentRequest->organization?->name ?? '—' }}</td>
                            <td>
                                <p class="text-xs font-medium">{{ $recentRequest->aiModel?->display_name ?? $recentRequest->model }}</p>
                                <p class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $recentRequest->endpoint }}</p>
                            </td>
                            <td><span class="badge {{ $recentRequest->status_code < 400 ? 'badge-green' : 'badge-red' }} font-mono">{{ $recentRequest->status_code }}</span></td>
                            <td class="font-mono text-xs">{{ compact_number($recentRequest->total_tokens) }}</td>
                            <td class="text-end font-mono text-xs">{{ money((float) $recentRequest->cost) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-xs text-ink-400">{{ __('ui.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const revenue = document.getElementById('admin-revenue');
                if (revenue) {
                    window.idexalChart(revenue, {
                        type: 'bar',
                        data: {
                            labels: @json($revenueSeries['labels']),
                            datasets: [{
                                label: @json(__('ui.common.revenue')),
                                data: @json($revenueSeries['values']),
                                backgroundColor: 'rgba(125, 255, 0, 0.65)',
                                hoverBackgroundColor: '#7dff00',
                                borderRadius: 6,
                                maxBarThickness: 34,
                            }],
                        },
                        options: { scales: { y: { beginAtZero: true } } },
                    });
                }

                const signups = document.getElementById('admin-signups');
                if (signups) {
                    window.idexalChart(signups, {
                        type: 'line',
                        data: {
                            labels: @json($signupSeries['labels']),
                            datasets: [{
                                label: @json(__('ui.admin.dashboard.users')),
                                data: @json($signupSeries['values']),
                                borderColor: '#080a08',
                                backgroundColor: 'rgba(125, 255, 0, 0.18)',
                                fill: true,
                                borderWidth: 2,
                                pointRadius: 0,
                                tension: 0.35,
                            }],
                        },
                    });
                }

                const requests = document.getElementById('admin-requests');
                if (requests) {
                    window.idexalChart(requests, {
                        type: 'line',
                        data: {
                            labels: @json($requestSeries['labels']),
                            datasets: [{
                                label: @json(__('ui.common.requests')),
                                data: @json($requestSeries['values']),
                                borderColor: '#4fae00',
                                backgroundColor: 'rgba(79, 174, 0, 0.14)',
                                fill: true,
                                borderWidth: 2,
                                pointRadius: 0,
                                tension: 0.35,
                            }],
                        },
                    });
                }
            });
        </script>
    @endpush
@endsection
