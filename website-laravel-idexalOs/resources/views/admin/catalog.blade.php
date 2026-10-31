@extends('layouts.admin')

@section('title', __('ui.admin.catalog.title'))
@section('page', __('ui.admin.nav.catalog'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.catalog.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.catalog.subtitle') }}</p>
        </div>
        <a href="{{ route('admin.combos.index') }}" class="btn btn-outline btn-sm h-10 px-4">
            <x-icon name="layers" class="size-4" />
            {{ __('ui.admin.nav.combos') }}
        </a>
    </div>

    <div class="card p-5">
        <form method="GET" action="{{ route('admin.catalog.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-56 flex-1">
                <label class="label" for="organization">{{ __('ui.admin.catalog.workspace') }}</label>
                <select id="organization" name="organization" class="input h-10">
                    @foreach ($organizations as $option)
                        <option value="{{ $option->getKey() }}" @selected($organization?->is($option))>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm h-10 px-5">{{ __('ui.admin.catalog.apply') }}</button>
        </form>
    </div>

    @if ($organization === null)
        <div class="alert alert-warning">{{ __('ui.admin.catalog.no_workspace') }}</div>
    @else
        @if ($visibleCount === 0 && $rows->isNotEmpty())
            <div class="alert alert-warning">
                <p class="font-medium">{{ __('ui.admin.catalog.empty_visible_title') }}</p>
                <p class="mt-1 text-sm leading-6">{{ __('ui.admin.catalog.empty_visible_hint') }}</p>
                <ul class="mt-2 list-inside list-disc text-sm">
                    @foreach ($grouped as $reason => $group)
                        @if ($reason !== 'visible')
                            @php([$label] = __('ui.admin.catalog.reasons.'.$reason))
                            <li>{{ $label }} · <span class="font-mono" dir="ltr">{{ $group->count() }}</span></li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="card p-5">
                <p class="text-xs font-medium text-ink-400">{{ __('ui.admin.catalog.visible') }}</p>
                <p class="mt-2 font-mono text-2xl font-medium" dir="ltr">{{ $visibleCount }}</p>
            </div>
            <div class="card p-5">
                <p class="text-xs font-medium text-ink-400">{{ __('ui.admin.catalog.hidden') }}</p>
                <p class="mt-2 font-mono text-2xl font-medium" dir="ltr">{{ $rows->count() - $visibleCount }}</p>
            </div>
            <div class="card p-5">
                <p class="text-xs font-medium text-ink-400">{{ __('ui.admin.catalog.plan') }}</p>
                @if ($plan !== null)
                    <p class="mt-2"><span class="badge badge-lime">{{ $plan->name }}</span></p>
                @else
                    <p class="mt-2"><span class="badge badge-gray">{{ __('ui.admin.catalog.plan_none') }}</span></p>
                    <p class="mt-1 text-xs leading-5 text-ink-400">{{ __('ui.admin.catalog.plan_none_hint') }}</p>
                @endif
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.admin.catalog.columns.model') }}</th>
                            <th>{{ __('ui.admin.catalog.columns.provider') }}</th>
                            <th>{{ __('ui.admin.catalog.columns.state') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>
                                    <div class="text-sm">{{ $row['display_name'] }}</div>
                                    <div class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $row['model_id'] }}</div>
                                </td>
                                <td>
                                    <div class="text-sm">{{ $row['provider'] }}</div>
                                    <div class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $row['driver'] }} · {{ $row['priority'] }}</div>
                                </td>
                                <td>
                                    @if ($row['in_catalog'])
                                        <span class="badge badge-green">{{ __('ui.admin.catalog.visible') }}</span>
                                    @else
                                        @php([$label, $fix] = __('ui.admin.catalog.reasons.'.$row['reason']))
                                        <span class="badge badge-gray">{{ $label }}</span>
                                        <p class="mt-1 max-w-md text-xs leading-5 text-ink-500">{{ $fix }}</p>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
