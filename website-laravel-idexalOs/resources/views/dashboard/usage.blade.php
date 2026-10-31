@extends('layouts.dashboard')

@section('title', __('ui.usage.title'))
@section('page', __('ui.dashboard.nav.usage'))

@php
    $labels = collect($series)->map(fn (array $row) => \Illuminate\Support\Carbon::parse($row['date'])->translatedFormat('d M'))->all();
    $requestsSeries = collect($series)->pluck('requests')->all();
    $tokensSeries = collect($series)->pluck('tokens')->all();
    $costSeries = collect($series)->pluck('cost')->all();
@endphp

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.usage.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.usage.subtitle') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="GET" action="{{ route('dashboard.usage') }}" class="flex items-center gap-2">
                <input type="month" name="period" value="{{ request('period', $summary['period']) }}" class="input h-10 w-40 text-xs">
                <button type="submit" class="btn btn-outline btn-sm h-10">{{ __('ui.common.filter') }}</button>
            </form>
            <a href="{{ route('dashboard.usage.export') }}" class="btn btn-primary btn-sm h-10">
                <x-icon name="download" class="size-4" />
                {{ __('ui.usage.export') }}
            </a>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            [__('ui.usage.requests_used'), $summary['requests'], $summary['limits']['requests'], $summary['percentages']['requests'], 'zap'],
            [__('ui.usage.tokens_used'), $summary['total_tokens'], $summary['limits']['tokens'], $summary['percentages']['tokens'], 'layers'],
            [__('ui.common.cost'), money($summary['cost'], $organization->currency), null, null, 'card'],
            [__('ui.usage.credit_balance'), money($summary['credit_balance'], $organization->currency), null, null, 'sparkles'],
        ] as [$label, $value, $limit, $percentage, $icon])
            <div class="card p-5">
                <div class="flex items-start justify-between">
                    <span class="stat-label">{{ $label }}</span>
                    <span class="grid size-8 place-items-center rounded-lg bg-paper text-ink-500"><x-icon :name="$icon" class="size-4" /></span>
                </div>
                <p class="stat-value mt-3 {{ is_string($value) ? 'text-2xl' : '' }}">{{ is_string($value) ? $value : compact_number($value) }}</p>
                @if ($limit !== null)
                    <div class="mt-4 flex justify-between text-[10px] text-ink-400">
                        <span>{{ __('ui.common.of') }} {{ compact_number($limit) }}</span>
                        <span class="font-mono">{{ $percentage }}%</span>
                    </div>
                    <div class="progress mt-2"><span style="width: {{ max(2, (float) $percentage) }}%"></span></div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="border-b border-ink-950/10 p-5">
                <h2 class="font-semibold">{{ __('ui.usage.chart_requests') }}</h2>
                <p class="mt-0.5 text-xs text-ink-400">{{ $from->translatedFormat('d M') }} — {{ $to->translatedFormat('d M Y') }}</p>
            </div>
            <div class="h-[260px] p-4"><canvas id="usage-requests"></canvas></div>
        </div>

        <div class="card overflow-hidden">
            <div class="border-b border-ink-950/10 p-5">
                <h2 class="font-semibold">{{ __('ui.usage.chart_tokens') }}</h2>
                <p class="mt-0.5 text-xs text-ink-400">{{ __('ui.usage.current_period') }}</p>
            </div>
            <div class="h-[260px] p-4"><canvas id="usage-tokens"></canvas></div>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-[1fr_320px]">
        <div class="card overflow-hidden">
            <div class="border-b border-ink-950/10 p-5">
                <h2 class="font-semibold">{{ __('ui.developer.request_logs') }}</h2>
                <p class="mt-0.5 text-xs text-ink-400">{{ __('ui.usage.table_subtitle') }}</p>
            </div>

            @if ($requests->isEmpty())
                <div class="p-10 text-center text-xs text-ink-400">{{ __('ui.usage.no_usage') }}</div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('ui.usage.table_date') }}</th>
                                <th>{{ __('ui.common.model') }}</th>
                                <th>{{ __('ui.developer.endpoint') }}</th>
                                <th>{{ __('ui.developer.status_code') }}</th>
                                <th>{{ __('ui.usage.table_tokens') }}</th>
                                <th>{{ __('ui.usage.table_features') }}</th>
                                <th class="text-end">{{ __('ui.usage.table_cost') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requests as $requestRow)
                                <tr>
                                    <td class="whitespace-nowrap text-xs text-ink-400">{{ $requestRow->created_at?->translatedFormat('d M H:i') }}</td>
                                    <td>
                                        <p class="text-sm font-medium text-ink-950">{{ $requestRow->aiModel?->display_name ?? $requestRow->model }}</p>
                                        <p class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $requestRow->model }}</p>
                                    </td>
                                    <td><code class="font-mono text-[11px]">{{ $requestRow->endpoint }}</code></td>
                                    <td><span class="badge {{ $requestRow->status_code < 400 ? 'badge-green' : 'badge-red' }} font-mono">{{ $requestRow->status_code }}</span></td>
                                    <td class="font-mono text-xs">{{ compact_number($requestRow->total_tokens) }}</td>
                                    <td>
                                        @php($applied = $requestRow->appliedFeatures())
                                        @if ($applied === [])
                                            <span class="text-[10px] text-ink-400">—</span>
                                        @else
                                            <div class="flex max-w-[170px] flex-wrap gap-1">
                                                @foreach ($applied as $feature)
                                                    @php($catalogue = \App\Support\GatewayFeature::tryFrom($feature))
                                                    <span class="badge {{ $feature === 'web_search' ? 'badge-amber' : 'badge-lime' }} font-mono text-[9px]" title="{{ $catalogue ? __($catalogue->labelKey()) : $feature }}">{{ $catalogue ? __($catalogue->badgeKey()) : $feature }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <p class="font-mono text-xs">{{ money($requestRow->cost, $organization->currency) }}</p>
                                        @if ($requestRow->searchFee() > 0)
                                            <p class="whitespace-nowrap font-mono text-[9px] text-amber-600">{{ __('ui.usage.search_fee', ['fee' => money($requestRow->searchFee(), $organization->currency)]) }}</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @include('partials.pagination', ['paginator' => $requests])
            @endif
        </div>

        <div class="space-y-4">
            <div class="card p-5">
                <h2 class="font-semibold">{{ __('ui.usage.plan_limits') }}</h2>
                <div class="mt-4 space-y-4">
                    @foreach ([
                        [__('ui.common.requests'), $summary['requests'], $summary['limits']['requests'], $summary['percentages']['requests']],
                        [__('ui.common.tokens'), $summary['total_tokens'], $summary['limits']['tokens'], $summary['percentages']['tokens']],
                    ] as [$label, $used, $limit, $percentage])
                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-ink-500">{{ $label }}</span>
                                <span class="font-mono text-[11px]">{{ compact_number($used) }} / {{ $limit === null ? '∞' : compact_number($limit) }}</span>
                            </div>
                            <div class="progress mt-2"><span style="width: {{ $limit === null ? 2 : max(2, (float) $percentage) }}%"></span></div>
                            <p class="mt-1 text-[10px] text-ink-400">{{ $limit === null ? __('ui.usage.unlimited') : __('ui.usage.remaining', ['value' => compact_number(max(0, $limit - $used))]) }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($summary['blocked'])
                    <div class="mt-4 rounded-xl bg-red-50 p-3 text-xs text-red-700">{{ __('ui.usage.blocked') }}</div>
                @elseif ($summary['over_quota'])
                    <div class="mt-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-700">{{ __('ui.usage.over_quota') }}</div>
                @endif

                <a href="{{ route('dashboard.billing') }}" class="btn btn-outline btn-sm mt-4 w-full">{{ __('ui.dashboard.manage_plan') }}</a>
            </div>

            @php($appliedFeatures = array_filter($featureUsage))
            <div class="card p-5">
                <h2 class="font-semibold">{{ __('ui.usage.feature_usage') }}</h2>
                <p class="mt-0.5 text-[11px] leading-5 text-ink-400">{{ __('ui.usage.feature_usage_hint') }}</p>
                @if ($appliedFeatures === [])
                    <p class="mt-3 text-[11px] leading-5 text-ink-400">{{ __('ui.usage.feature_usage_empty') }}</p>
                @else
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($appliedFeatures as $feature => $count)
                            @php($catalogue = \App\Support\GatewayFeature::tryFrom($feature))
                            <span class="badge badge-lime font-mono text-[9px]" title="{{ $catalogue ? __($catalogue->labelKey()) : $feature }}">
                                {{ $catalogue ? __($catalogue->badgeKey()) : $feature }} {{ compact_number($count) }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="rounded-3xl bg-ink-950 p-5 text-paper">
                <x-icon name="chart" class="size-5 text-lime" />
                <p class="mt-3 text-sm font-semibold">{{ __('ui.usage.export') }}</p>
                <p class="mt-1 text-[11px] leading-5 text-paper/45">{{ __('ui.usage.subtitle') }}</p>
                <a href="{{ route('dashboard.usage.export') }}" class="btn btn-lime btn-sm mt-4 w-full">
                    <x-icon name="download" class="size-3.5" />
                    CSV
                </a>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const common = {
                    labels: @json($labels),
                    datasets: [{
                        borderColor: '#080a08',
                        backgroundColor: 'rgba(125, 255, 0, 0.18)',
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 0,
                        tension: 0.35,
                    }],
                };

                const requestsCanvas = document.getElementById('usage-requests');
                if (requestsCanvas) {
                    window.idexalChart(requestsCanvas, {
                        type: 'line',
                        data: { ...common, datasets: [{ ...common.datasets[0], label: @json(__('ui.common.requests')), data: @json($requestsSeries) }] },
                    });
                }

                const tokensCanvas = document.getElementById('usage-tokens');
                if (tokensCanvas) {
                    window.idexalChart(tokensCanvas, {
                        type: 'bar',
                        data: {
                            labels: @json($labels),
                            datasets: [{
                                label: @json(__('ui.common.tokens')),
                                data: @json($tokensSeries),
                                backgroundColor: 'rgba(79, 174, 0, 0.55)',
                                borderRadius: 6,
                                maxBarThickness: 18,
                            }],
                        },
                    });
                }
            });
        </script>
    @endpush
@endsection
