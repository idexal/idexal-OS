@extends('layouts.admin')

@section('title', __('ui.admin.plans.title'))
@section('page', __('ui.admin.nav.plans'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.plans.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.plans.subtitle') }}</p>
        </div>
        <a href="{{ route('admin.plans.create') }}" class="btn btn-primary btn-sm h-10 px-4">
            <x-icon name="plus" class="size-4" />
            {{ __('ui.admin.plans.create') }}
        </a>
    </div>

    <div class="card overflow-hidden">
        @if ($plans->isEmpty())
            <div class="p-12 text-center text-sm text-ink-400">{{ __('ui.admin.plans.empty') }}</div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.common.plan') }}</th>
                            <th class="text-end">{{ __('ui.common.monthly') }}</th>
                            <th class="text-end">{{ __('ui.common.yearly') }}</th>
                            <th>{{ __('ui.pricing.limits') }}</th>
                            <th>{{ __('ui.pricing.features') }}</th>
                            <th>{{ __('ui.admin.plans.subscriptions') }}</th>
                            <th>{{ __('ui.common.status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($plans as $plan)
                            <tr>
                                <td>
                                    <p class="text-sm font-semibold text-ink-950">
                                        {{ $plan->name }}
                                        @if ($plan->is_public)
                                            <span class="badge badge-lime ms-1">{{ __('ui.common.public') }}</span>
                                        @endif
                                    </p>
                                    <p class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $plan->slug }} · {{ $plan->sort_order }}</p>
                                </td>
                                <td class="text-end font-mono text-xs">{{ $plan->formattedPrice('monthly') }}</td>
                                <td class="text-end font-mono text-xs">{{ $plan->formattedPrice('yearly') }}</td>
                                <td class="text-[10px] leading-5 text-ink-500">
                                    <span class="block font-mono">{{ compact_number($plan->limit(\App\Models\Plan::LIMIT_REQUESTS)) ?: '∞' }} {{ __('ui.common.requests') }}</span>
                                    <span class="block font-mono">{{ compact_number($plan->limit(\App\Models\Plan::LIMIT_TOKENS)) ?: '∞' }} {{ __('ui.common.tokens') }}</span>
                                    <span class="block font-mono">{{ $plan->limit(\App\Models\Plan::LIMIT_SEATS) ?? '∞' }} {{ __('ui.common.seats') }}</span>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach (['api_access', 'team_members', 'webhooks', 'priority_support', 'sso'] as $feature)
                                            @if ($plan->hasFeature($feature))
                                                <span class="badge badge-gray">{{ __('ui.pricing.feature_'.$feature) }}</span>
                                            @endif
                                        @endforeach
                                    </div>
                                </td>
                                <td class="font-mono text-xs">{{ $plan->subscriptions_count }}</td>
                                <td><span class="badge {{ $plan->is_active ? 'badge-green' : 'badge-gray' }}">{{ $plan->is_active ? __('ui.common.active') : __('ui.common.inactive') }}</span></td>
                                <td class="text-end">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-ghost btn-sm">{{ __('ui.common.edit') }}</a>
                                        <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" x-data @submit="if (! confirm(@js(__('ui.admin.plans.confirm_delete')))) { $event.preventDefault(); }">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-3.5" /></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
