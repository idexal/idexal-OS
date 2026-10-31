@extends('layouts.admin')

@section('title', __('ui.admin.gateway.title'))
@section('page', __('ui.admin.nav.gateway'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.gateway.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.gateway.subtitle') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.providers.index') }}" class="btn btn-outline btn-sm h-10 px-4">
                <x-icon name="server" class="size-4" />
                {{ __('ui.admin.nav.providers') }}
            </a>
            <a href="{{ route('docs') }}" class="btn btn-primary btn-sm h-10 px-4">
                <x-icon name="book" class="size-4" />
                {{ __('ui.dashboard.nav.docs') }}
            </a>
        </div>
    </div>

    @if ($errorCount > 0)
        <div class="alert alert-warning">
            <x-icon name="alert" class="mt-0.5 size-4" />
            <p>{{ __('ui.admin.gateway.errors_alert', ['count' => $errorCount]) }}</p>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="relative overflow-hidden rounded-3xl bg-ink-950 p-5 text-paper">
            <div class="pointer-events-none absolute -end-6 -top-6 size-24 rounded-full bg-lime/20 blur-2xl"></div>
            <div class="relative flex items-start justify-between">
                <span class="text-xs font-medium text-paper/50">{{ __('ui.admin.gateway.month_requests') }}</span>
                <span class="grid size-8 place-items-center rounded-lg bg-white/10 text-lime"><x-icon name="zap" class="size-4" /></span>
            </div>
            <p class="relative mt-3 font-mono text-2xl font-medium" dir="ltr">{{ compact_number($stats['monthRequests']) }}</p>
            <p class="relative mt-1 text-[10px] text-paper/40">{{ compact_number($stats['todayRequests']) }} {{ __('ui.admin.gateway.today') }}</p>
        </div>
        <div class="card p-5">
            <p class="stat-label">{{ __('ui.admin.gateway.month_tokens') }}</p>
            <p class="stat-value mt-2" dir="ltr">{{ compact_number($stats['monthTokens']) }}</p>
            <p class="mt-1 text-[10px] text-ink-400">{{ now()->translatedFormat('F Y') }}</p>
        </div>
        <div class="card p-5">
            <p class="stat-label">{{ __('ui.admin.gateway.month_cost') }}</p>
            <p class="stat-value mt-2" dir="ltr">{{ money($stats['monthCost']) }}</p>
            <p class="mt-1 text-[10px] text-ink-400">{{ __('ui.admin.gateway.cost_hint') }}</p>
        </div>
        <div class="card p-5">
            <p class="stat-label">{{ __('ui.admin.gateway.error_rate') }}</p>
            <p class="stat-value mt-2" dir="ltr">{{ number_format($stats['errorRate'], 2) }}%</p>
            <p class="mt-1 text-[10px] text-ink-400">{{ __('ui.admin.gateway.error_rate_hint') }}</p>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-ink-950/10 p-5">
                <div>
                    <h2 class="font-semibold">{{ __('ui.admin.gateway.traffic') }}</h2>
                    <p class="mt-0.5 text-xs text-ink-400">{{ __('ui.admin.gateway.last_30_days') }}</p>
                </div>
                <div class="flex items-center gap-3 text-[10px] text-ink-400">
                    <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-lime-dark"></span>{{ __('ui.common.requests') }}</span>
                    <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-ink-950"></span>{{ __('ui.common.tokens') }}</span>
                </div>
            </div>
            <div class="h-[280px] p-4 sm:p-5"><canvas id="gateway-traffic"></canvas></div>
        </div>

        <div class="card overflow-hidden">
            <div class="border-b border-ink-950/10 p-5">
                <h2 class="font-semibold">{{ __('ui.admin.gateway.top_models') }}</h2>
                <p class="mt-0.5 text-xs text-ink-400">{{ now()->translatedFormat('F Y') }}</p>
            </div>
            @forelse ($topModels as $model)
                <div class="border-b border-ink-950/[0.06] p-4 last:border-0">
                    <div class="flex items-center justify-between gap-3">
                        <p class="truncate font-mono text-[11px] font-medium" dir="ltr">{{ $model->model }}</p>
                        <span class="shrink-0 font-mono text-[11px] font-semibold">{{ compact_number($model->requests) }}</span>
                    </div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-paper">
                        <div class="h-full rounded-full bg-lime-dark" style="width: {{ $topModels->max('requests') > 0 ? max(4, round(($model->requests / $topModels->max('requests')) * 100)) : 0 }}%"></div>
                    </div>
                    <div class="mt-1 flex justify-between gap-3 text-[10px] text-ink-400">
                        <span>{{ compact_number($model->tokens) }} {{ __('ui.common.tokens') }}</span>
                        <span class="font-mono">{{ money((float) $model->cost) }}</span>
                    </div>
                </div>
            @empty
                <p class="p-8 text-center text-xs text-ink-400">{{ __('ui.common.no_data') }}</p>
            @endforelse
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-[1fr_1.6fr]">
        <div class="card overflow-hidden">
            <div class="border-b border-ink-950/10 p-5">
                <h2 class="font-semibold">{{ __('ui.admin.gateway.top_organizations') }}</h2>
            </div>
            @forelse ($topOrganizations as $row)
                <div class="flex items-center gap-3 border-b border-ink-950/[0.06] p-4 last:border-0">
                    <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-ink-950 font-mono text-[10px] font-semibold text-lime">{{ $loop->iteration }}</span>
                    <div class="min-w-0 flex-1">
                        <a href="{{ $row->organization ? route('admin.organizations.show', $row->organization) : '#' }}" class="block truncate text-xs font-semibold hover:underline">{{ $row->organization?->name ?? '—' }}</a>
                        <p class="text-[10px] text-ink-400">{{ compact_number($row->requests) }} {{ __('ui.common.requests') }} · {{ compact_number($row->tokens) }} {{ __('ui.common.tokens') }}</p>
                    </div>
                    <span class="font-mono text-[11px]">{{ money((float) $row->cost) }}</span>
                </div>
            @empty
                <p class="p-8 text-center text-xs text-ink-400">{{ __('ui.common.no_data') }}</p>
            @endforelse
        </div>

        <div class="card p-5">
            <h2 class="font-semibold">{{ __('ui.admin.gateway.endpoints') }}</h2>
            <p class="mt-1 text-xs leading-6 text-ink-400">{{ __('ui.admin.gateway.endpoints_hint') }}</p>
            <div class="mt-4 space-y-2">
                @foreach ([
                    ['POST', '/v1/chat/completions', 'OpenAI compatible'],
                    ['POST', '/v1/messages', 'Anthropic compatible'],
                    ['GET', '/v1/models', 'Model list'],
                    ['GET', '/v1/usage', 'Usage summary'],
                ] as [$method, $path, $label])
                    <div class="flex items-center gap-3 rounded-xl border border-ink-950/10 p-3">
                        <span class="badge {{ $method === 'POST' ? 'badge-lime' : 'badge-gray' }} font-mono">{{ $method }}</span>
                        <code class="min-w-0 flex-1 truncate font-mono text-[11px]" dir="ltr">{{ $path }}</code>
                        <span class="hidden text-[10px] text-ink-400 sm:block">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('docs') }}" class="mt-4 inline-flex items-center gap-1.5 text-xs font-semibold text-ink-950">
                {{ __('ui.docs.api') }}
                <x-icon name="arrow-left" class="size-3.5 rtl:rotate-180" />
            </a>
        </div>
    </div>

    <div class="card p-5">
        <div>
            <h2 class="font-semibold">{{ __('ui.admin.gateway.feature_usage') }}</h2>
            <p class="mt-0.5 text-xs text-ink-400">{{ now()->translatedFormat('F Y') }} · {{ __('ui.admin.gateway.feature_usage_hint') }}</p>
        </div>
        @php($peak = collect($featureUsage)->max('count') ?: 0)
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($featureUsage as $row)
                <a href="{{ route('admin.gateway', ['feature' => $row['key']]) }}" class="rounded-xl border border-ink-950/10 p-3 transition hover:border-ink-950/30">
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate text-xs font-medium">{{ $row['label'] }}</span>
                        <span class="shrink-0 font-mono text-xs font-semibold">{{ compact_number($row['count']) }}</span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-paper">
                        <div class="h-full rounded-full bg-lime-dark" style="width: {{ $peak > 0 ? max(3, round(($row['count'] / $peak) * 100)) : 0 }}%"></div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    <div class="card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-semibold">{{ __('ui.admin.gateway.usage_requests.title') }}</h2>
                <p class="mt-0.5 max-w-3xl text-xs leading-5 text-ink-400">{{ __('ui.admin.gateway.usage_requests.hint') }}</p>
            </div>
            <span class="badge badge-gray">{{ count($usageRequests) }}</span>
        </div>

        @if ($usageRequests === [])
            <p class="mt-4 text-xs text-ink-400">{{ __('ui.admin.gateway.usage_requests.empty') }}</p>
        @else
            <div class="mt-4 space-y-3">
                @foreach ($usageRequests as $ask)
                    <div class="rounded-xl border border-ink-950/10 p-3">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium">{{ $ask['member'] }}</p>
                                <p class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $ask['memberEmail'] }}</p>
                                <p class="mt-1 text-[11px] text-ink-500">{{ $ask['organization'] }} · {{ $ask['period'] }}</p>
                            </div>
                            <div class="text-end">
                                @if ($ask['currentAllowanceUsd'])
                                    <p class="font-mono text-xs text-ink-600" dir="ltr">
                                        {{ '$'.number_format((float) $ask['currentAllowanceUsd']['used'], 2) }}
                                        /
                                        {{ '$'.number_format((float) $ask['currentAllowanceUsd']['allowance'], 2) }}
                                    </p>
                                @endif
                                <p class="text-[10px] text-ink-400">{{ $ask['askedAt'] }}</p>
                            </div>
                        </div>

                        <p class="mt-2 rounded-lg bg-paper px-3 py-2 text-xs leading-5 text-ink-600">{{ $ask['reason'] }}</p>

                        <div class="mt-3 flex flex-wrap items-end gap-2">
                            <form method="POST" action="{{ route('admin.gateway.usage-requests.approve', $ask['id']) }}" class="flex items-end gap-2">
                                @csrf
                                <div>
                                    <label class="label" for="ext-{{ $ask['id'] }}">{{ __('ui.admin.gateway.usage_requests.extension') }}</label>
                                    <input id="ext-{{ $ask['id'] }}" type="number" name="extension_usd" min="0.01" max="1000000" step="0.01"
                                           value="{{ $ask['currentAllowanceUsd'] ? number_format((float) $ask['currentAllowanceUsd']['allowance'], 2, '.', '') : '25.00' }}"
                                           class="input h-9 w-28 font-mono text-xs" dir="ltr" required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm h-9 px-3">{{ __('ui.admin.gateway.usage_requests.approve') }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.gateway.usage-requests.deny', $ask['id']) }}" x-data @submit="if (! confirm(@js(__('ui.admin.gateway.usage_requests.confirm_deny')))) { $event.preventDefault(); }">
                                @csrf
                                <button type="submit" class="btn btn-outline btn-sm h-9 px-3 text-red-600">{{ __('ui.admin.gateway.usage_requests.deny') }}</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-semibold">{{ __('ui.admin.gateway.delivery_title') }}</h2>
                <p class="mt-0.5 max-w-3xl text-xs leading-5 text-ink-400">{{ __('ui.admin.gateway.delivery_hint') }}</p>
            </div>
            <form method="GET" action="{{ route('admin.gateway') }}" class="flex items-center gap-2">
                <select name="delivery" class="select h-10 w-56 text-xs" onchange="this.form.submit()">
                    @foreach ($deliveryOrganizations as $option)
                        <option value="{{ $option->getKey() }}" @selected($deliveryOrganization?->getKey() === $option->getKey())>{{ $option->name }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if ($deliveryHealth['no_free_plan'])
            <p class="mt-4 flex items-start gap-2 rounded-xl border border-amber-500/30 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-700">
                <x-icon name="alert" class="mt-0.5 size-3.5 shrink-0" />
                <span>{{ __('ui.admin.gateway.delivery_no_free_plan') }}</span>
            </p>
        @endif

        @if ($deliveryHealth['active_providers'] > 0 && $deliveryHealth['providers_without_credential'] === $deliveryHealth['active_providers'])
            <p class="mt-4 flex items-start gap-2 rounded-xl border border-red-500/30 bg-red-50 px-3 py-2 text-xs leading-5 text-red-700">
                <x-icon name="alert" class="mt-0.5 size-3.5 shrink-0" />
                <span>{{ __('ui.admin.gateway.delivery_no_credentials') }}</span>
            </p>
        @endif

        @if ($deliveryOrganization instanceof \App\Models\Organization)
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-ink-950/10 p-3">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __('ui.admin.gateway.delivery_plan') }}</p>
                    <p class="mt-1 text-sm font-semibold">{{ $deliveryPlan?->name ?? __('ui.admin.gateway.delivery_no_plan') }}</p>
                    <p class="mt-0.5 text-[11px] text-ink-400">{{ $deliverySubscription?->status ?? __('ui.admin.gateway.delivery_no_subscription') }}</p>
                </div>
                <div class="rounded-xl border border-ink-950/10 p-3">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __('ui.admin.gateway.delivery_delivered') }}</p>
                    <p class="mt-1 font-mono text-lg font-semibold">{{ $deliveryDelivered }}</p>
                    <p class="mt-0.5 text-[11px] text-ink-400">{{ __('ui.admin.gateway.delivery_of_total', ['total' => count($deliveryRows)]) }}</p>
                </div>
                <div class="rounded-xl border border-ink-950/10 p-3">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __('ui.admin.gateway.delivery_providers') }}</p>
                    <p class="mt-1 font-mono text-lg font-semibold">{{ $deliveryHealth['desktop_drivers'] }} / {{ $deliveryHealth['active_providers'] }}</p>
                    <p class="mt-0.5 text-[11px] text-ink-400">{{ __('ui.admin.gateway.delivery_providers_hint') }}</p>
                </div>
                <div class="rounded-xl border border-ink-950/10 p-3">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __('ui.admin.gateway.delivery_blockers') }}</p>
                    @if ($deliveryReasons === [])
                        <p class="mt-1 text-sm font-semibold text-lime-dark">{{ __('ui.admin.gateway.delivery_none') }}</p>
                    @else
                        <p class="mt-1 font-mono text-lg font-semibold">{{ array_sum($deliveryReasons) }}</p>
                        <p class="mt-0.5 text-[11px] text-ink-400">{{ __('ui.admin.gateway.delivery_blockers_hint') }}</p>
                    @endif
                </div>
            </div>

            @if ($deliveryReasons !== [])
                <div class="mt-4 space-y-2">
                    @foreach ($deliveryReasons as $reason => $count)
                        <div class="flex items-start gap-3 rounded-xl border border-ink-950/10 p-3">
                            <span class="badge badge-amber shrink-0">{{ $count }}</span>
                            <div class="min-w-0">
                                <p class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $reason }}</p>
                                <p class="mt-0.5 text-xs leading-5 text-ink-600">{{ $deliveryHealth['reason_labels'][$reason] ?? $reason }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <details class="mt-4 rounded-xl border border-ink-950/10">
                <summary class="cursor-pointer p-3 text-xs font-medium">{{ __('ui.admin.gateway.delivery_all_models') }}</summary>
                <div class="table-wrap border-t border-ink-950/5">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('ui.common.model') }}</th>
                                <th>{{ __('ui.admin.nav.providers') }}</th>
                                <th>{{ __('ui.admin.gateway.delivery_driver') }}</th>
                                <th>{{ __('ui.common.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($deliveryRows as $row)
                                <tr>
                                    <td>
                                        <p class="text-xs font-medium">{{ $row['display_name'] }}</p>
                                        <p class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $row['model_id'] }}</p>
                                    </td>
                                    <td class="font-mono text-[11px] text-ink-500" dir="ltr">{{ $row['provider'] }}</td>
                                    <td class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $row['driver'] }}</td>
                                    <td>
                                        @if ($row['in_catalog'])
                                            <span class="badge badge-green">{{ __('ui.admin.gateway.delivery_shown') }}</span>
                                        @else
                                            <span class="badge badge-amber">{{ $row['reason'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @else
            <p class="mt-4 text-xs text-ink-400">{{ __('ui.admin.gateway.delivery_empty') }}</p>
        @endif
    </div>

    <form method="GET" action="{{ route('admin.gateway') }}" class="flex flex-wrap items-center gap-2 rounded-2xl border border-ink-950/10 bg-white p-3">
        <input type="number" name="status" value="{{ $filterStatus ?: '' }}" min="100" max="599" placeholder="200" class="input h-10 w-24 font-mono text-xs" dir="ltr">
        <input type="text" name="model" value="{{ $filterModel }}" placeholder="{{ __('ui.common.model') }}" class="input h-10 w-52 font-mono text-xs" dir="ltr">
        <input type="text" name="organization" value="{{ $filterOrganization }}" placeholder="Organization ID" class="input h-10 w-64 font-mono text-xs" dir="ltr">
        <select name="feature" class="select h-10 w-44 text-xs">
            <option value="">{{ __('ui.admin.gateway.all_features') }}</option>
            @foreach ($features as $key => $label)
                <option value="{{ $key }}" @selected($filterFeature === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary btn-sm h-10 px-4">{{ __('ui.common.filter') }}</button>
        @if ($filterStatus || $filterModel !== '' || $filterOrganization !== '' || $filterFeature !== '')
            <a href="{{ route('admin.gateway') }}" class="text-xs font-medium text-ink-400 hover:text-ink-950">{{ __('ui.common.reset') }}</a>
        @endif
    </form>

    <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-ink-950/10 p-5">
            <div>
                <h2 class="font-semibold">{{ __('ui.admin.gateway.logs') }}</h2>
                <p class="mt-0.5 text-xs text-ink-400">{{ __('ui.admin.gateway.logs_subtitle') }}</p>
            </div>
            <span class="badge badge-gray">{{ $requests->total() }}</span>
        </div>

        @if ($requests->isEmpty())
            <div class="p-12 text-center text-sm text-ink-400">{{ __('ui.common.no_results') }}</div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.developer.time') }}</th>
                            <th>{{ __('ui.admin.organizations.title') }}</th>
                            <th>{{ __('ui.common.user') }}</th>
                            <th>{{ __('ui.common.model') }}</th>
                            <th>{{ __('ui.admin.gateway.features') }}</th>
                            <th>{{ __('ui.developer.endpoint') }}</th>
                            <th>{{ __('ui.developer.status_code') }}</th>
                            <th>{{ __('ui.common.tokens') }}</th>
                            <th class="text-end">{{ __('ui.common.cost') }}</th>
                            <th class="text-end">{{ __('ui.common.latency') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $row)
                            <tr>
                                <td class="whitespace-nowrap text-[11px] text-ink-400">{{ $row->created_at?->translatedFormat('d M H:i:s') }}</td>
                                <td>
                                    @if ($row->organization)
                                        <a href="{{ route('admin.organizations.show', $row->organization) }}" class="block max-w-[160px] truncate text-xs font-medium hover:underline">{{ $row->organization->name }}</a>
                                    @else
                                        <span class="text-xs text-ink-400">—</span>
                                    @endif
                                </td>
                                <td>
                                    <p class="text-xs">{{ $row->user?->name ?? '—' }}</p>
                                    <p class="max-w-[160px] truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $row->user?->email }}</p>
                                </td>
                                <td>
                                    <p class="text-xs">{{ $row->aiModel?->display_name ?? $row->model }}</p>
                                    <p class="max-w-[160px] truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $row->model }}</p>
                                </td>
                                <td>
                                    @php($appliedFeatures = $row->appliedFeatures())
                                    @if ($appliedFeatures === [])
                                        <span class="text-[10px] text-ink-400">—</span>
                                    @else
                                        <div class="flex max-w-[170px] flex-wrap gap-1">
                                            @foreach ($appliedFeatures as $feature)
                                                <span class="badge badge-lime font-mono text-[9px]">{{ __(\App\Support\GatewayFeature::from($feature)->badgeKey()) }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td><code class="font-mono text-[10px] text-ink-500">{{ $row->endpoint }}</code></td>
                                <td><span class="badge {{ $row->status_code < 400 ? 'badge-green' : 'badge-red' }} font-mono">{{ $row->status_code }}</span></td>
                                <td class="font-mono text-[11px]">{{ compact_number($row->total_tokens) }}</td>
                                <td class="text-end">
                                    <p class="font-mono text-[11px]">{{ money((float) $row->cost) }}</p>
                                    @if ($row->searchFee() > 0)
                                        <p class="whitespace-nowrap font-mono text-[9px] text-amber-600">{{ __('ui.admin.gateway.search_fee', ['fee' => money($row->searchFee())]) }}</p>
                                    @endif
                                </td>
                                <td class="text-end font-mono text-[11px] text-ink-400">{{ $row->latency_ms }}ms</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $requests])
        @endif
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const canvas = document.getElementById('gateway-traffic');
                if (! canvas) {
                    return;
                }

                window.idexalChart(canvas, {
                    type: 'line',
                    data: {
                        labels: @json($series['labels']),
                        datasets: [
                            {
                                label: @json(__('ui.common.requests')),
                                data: @json($series['requests']),
                                borderColor: '#7dff00',
                                backgroundColor: 'rgba(125, 255, 0, 0.14)',
                                fill: true,
                                borderWidth: 2,
                                pointRadius: 0,
                                tension: 0.35,
                                yAxisID: 'y',
                            },
                            {
                                label: @json(__('ui.common.tokens')),
                                data: @json($series['tokens']),
                                borderColor: '#080a08',
                                backgroundColor: 'transparent',
                                borderWidth: 1.5,
                                borderDash: [4, 4],
                                pointRadius: 0,
                                tension: 0.35,
                                yAxisID: 'y1',
                            },
                        ],
                    },
                    options: {
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            y: { beginAtZero: true, position: 'right' },
                            y1: { beginAtZero: true, position: 'left', grid: { drawOnChartArea: false } },
                        },
                    },
                });
            });
        </script>
    @endpush
@endsection
