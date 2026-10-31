@extends('layouts.admin')

@section('title', __('ui.admin.users.title'))
@section('page', __('ui.admin.nav.users'))

@php
    $hasFilters = $search !== '' || $role !== '' || $status !== '' || $verified !== '' || $organization !== '' || $activity !== '';
    $statCards = [
        ['label' => __('ui.admin.users.stat_total'), 'value' => $stats['total'], 'icon' => 'users'],
        ['label' => __('ui.admin.users.stat_active'), 'value' => $stats['active'], 'icon' => 'check-circle'],
        ['label' => __('ui.admin.users.stat_suspended'), 'value' => $stats['suspended'], 'icon' => 'alert'],
        ['label' => __('ui.admin.users.stat_admins'), 'value' => $stats['admins'], 'icon' => 'shield'],
        ['label' => __('ui.admin.users.stat_unverified'), 'value' => $stats['unverified'], 'icon' => 'mail'],
        ['label' => __('ui.admin.users.stat_new'), 'value' => $stats['new_this_month'], 'icon' => 'sparkles'],
    ];
@endphp

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.users.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.users.subtitle') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.users.export', request()->query()) }}" class="btn btn-outline btn-sm h-10 px-4">
                <x-icon name="download" class="size-4" />
                {{ __('ui.admin.users.export') }}
            </a>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm h-10 px-4">
                <x-icon name="plus" class="size-4" />
                {{ __('ui.admin.users.create') }}
            </a>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        @foreach ($statCards as $card)
            <div class="card p-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ $card['label'] }}</p>
                    <x-icon :name="$card['icon']" class="size-4 text-ink-300" />
                </div>
                <p class="mt-2 font-mono text-2xl font-medium text-ink-950">{{ number_format($card['value']) }}</p>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="grid gap-2 rounded-2xl border border-ink-950/10 bg-white p-3 sm:grid-cols-2 xl:grid-cols-[2fr_repeat(5,1fr)_auto]">
        <div class="relative sm:col-span-2 xl:col-span-1">
            <x-icon name="search" class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-400" />
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('ui.admin.users.search_placeholder') }}" class="input h-10 ps-10">
        </div>
        <select name="role" class="select h-10">
            <option value="">{{ __('ui.common.all') }} — {{ __('ui.common.role') }}</option>
            @foreach ($roles as $value => $label)
                <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="status" class="select h-10">
            <option value="">{{ __('ui.common.all') }} — {{ __('ui.common.status') }}</option>
            <option value="active" @selected($status === 'active')>{{ __('ui.statuses.active') }}</option>
            <option value="suspended" @selected($status === 'suspended')>{{ __('ui.statuses.suspended') }}</option>
        </select>
        <select name="verified" class="select h-10">
            <option value="">{{ __('ui.common.all') }} — {{ __('ui.admin.users.verified') }}</option>
            <option value="yes" @selected($verified === 'yes')>{{ __('ui.admin.users.verified_yes') }}</option>
            <option value="no" @selected($verified === 'no')>{{ __('ui.admin.users.verified_no') }}</option>
        </select>
        <select name="organization" class="select h-10">
            <option value="">{{ __('ui.common.all') }} — {{ __('ui.admin.organizations.title') }}</option>
            @foreach ($organizations as $org)
                <option value="{{ $org->getKey() }}" @selected($organization === $org->getKey())>{{ $org->name }}</option>
            @endforeach
        </select>
        <select name="activity" class="select h-10">
            <option value="">{{ __('ui.common.all') }} — {{ __('ui.admin.users.activity') }}</option>
            <option value="keys" @selected($activity === 'keys')>{{ __('ui.admin.users.activity_keys') }}</option>
            <option value="sessions" @selected($activity === 'sessions')>{{ __('ui.admin.users.activity_sessions') }}</option>
            <option value="none" @selected($activity === 'none')>{{ __('ui.admin.users.activity_none') }}</option>
        </select>
        <div class="flex items-center gap-2">
            <select name="sort" class="select h-10" aria-label="{{ __('ui.admin.users.sort') }}">
                <option value="newest" @selected($sort === 'newest')>{{ __('ui.admin.users.sort_newest') }}</option>
                <option value="oldest" @selected($sort === 'oldest')>{{ __('ui.admin.users.sort_oldest') }}</option>
                <option value="name" @selected($sort === 'name')>{{ __('ui.common.name') }}</option>
                <option value="email" @selected($sort === 'email')>{{ __('ui.common.email') }}</option>
                <option value="role" @selected($sort === 'role')>{{ __('ui.common.role') }}</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm h-10 px-4">{{ __('ui.common.filter') }}</button>
            @if ($hasFilters)
                <a href="{{ route('admin.users.index') }}" class="text-xs font-medium text-ink-400 hover:text-ink-950">{{ __('ui.common.reset') }}</a>
            @endif
        </div>
    </form>

    <form id="users-bulk-form" method="POST" action="{{ route('admin.users.bulk') }}" class="card overflow-hidden">
        @csrf

        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-ink-950/5 p-3">
            <div class="flex flex-wrap items-center gap-2">
                <select name="action" class="select h-9 w-56" required>
                    <option value="">{{ __('ui.admin.users.bulk_action') }}</option>
                    <option value="activate">{{ __('ui.admin.users.bulk_activate') }}</option>
                    <option value="suspend">{{ __('ui.admin.users.bulk_suspend') }}</option>
                    <option value="verify">{{ __('ui.admin.users.bulk_verify') }}</option>
                    <option value="reset">{{ __('ui.admin.users.bulk_reset') }}</option>
                    <option value="delete">{{ __('ui.admin.users.bulk_delete') }}</option>
                </select>
                <button
                    type="submit"
                    class="btn btn-outline btn-sm h-9 px-4"
                    data-confirm="{{ __('ui.admin.users.bulk_confirm') }}"
                    x-data
                    @click="if (! confirm($el.dataset.confirm)) { $event.preventDefault(); }"
                >
                    {{ __('ui.admin.users.apply') }}
                </button>
            </div>
            <span class="badge badge-gray">{{ $users->total() }} {{ __('ui.admin.users.title') }}</span>
        </div>

        @if ($users->isEmpty())
            <div class="p-12 text-center text-sm text-ink-400">{{ __('ui.common.no_results') }}</div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-8">
                                <input
                                    type="checkbox"
                                    class="checkbox"
                                    x-data
                                    aria-label="{{ __('ui.admin.users.select_all') }}"
                                    @change="$el.closest('form').querySelectorAll('input[name=&quot;ids[]&quot;]').forEach((el) => { el.checked = $event.target.checked })"
                                >
                            </th>
                            <th>{{ __('ui.common.name') }}</th>
                            <th>{{ __('ui.common.role') }}</th>
                            <th>{{ __('ui.admin.organizations.title') }}</th>
                            <th class="text-center">{{ __('ui.common.api_keys') }}</th>
                            <th class="text-center">{{ __('ui.desktop.sessions') }}</th>
                            <th class="text-center">{{ __('ui.admin.users.verified') }}</th>
                            <th>{{ __('ui.common.status') }}</th>
                            <th>{{ __('ui.common.created_at') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            @php($isSelf = $user->id === auth()->id())
                            <tr>
                                <td>
                                    <input type="checkbox" name="ids[]" value="{{ $user->getKey() }}" class="checkbox" aria-label="{{ $user->email }}">
                                </td>
                                <td>
                                    <a href="{{ route('admin.users.show', $user) }}" class="flex items-center gap-3">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-ink-950 font-mono text-xs font-semibold text-lime">{{ $user->initials() ?: '?' }}</span>
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-semibold text-ink-950">
                                                {{ $user->name }}
                                                @if ($isSelf)
                                                    <span class="text-[10px] font-normal text-ink-400">({{ __('ui.team.you') }})</span>
                                                @endif
                                            </span>
                                            <span class="block truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $user->email }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td><span class="badge {{ $user->role === 'admin' ? 'badge-lime' : 'badge-gray' }}">{{ $roles[$user->role] ?? $user->role }}</span></td>
                                <td>
                                    @if ($user->organizations->isEmpty())
                                        <span class="text-xs text-ink-300">—</span>
                                    @else
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($user->organizations->take(2) as $org)
                                                <a href="{{ route('admin.organizations.show', $org) }}" class="badge badge-gray hover:border-ink-950/25">{{ $org->name }}</a>
                                            @endforeach
                                            @if ($user->organizations->count() > 2)
                                                <span class="badge badge-gray">+{{ $user->organizations->count() - 2 }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center font-mono text-xs">{{ $user->api_keys_count }}</td>
                                <td class="text-center font-mono text-xs">{{ $user->desktop_sessions_count }}</td>
                                <td class="text-center">
                                    @if ($user->email_verified_at)
                                        <span class="badge badge-green">{{ __('ui.admin.users.verified_yes') }}</span>
                                    @else
                                        <span class="badge badge-amber">{{ __('ui.admin.users.verified_no') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $user->status === 'active' ? 'badge-green' : 'badge-red' }}">{{ __('ui.statuses.'.$user->status) }}</span>
                                </td>
                                <td class="whitespace-nowrap text-xs text-ink-400">{{ $user->created_at?->translatedFormat('d M Y') }}</td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('admin.users.show', $user) }}" class="btn-icon" aria-label="{{ __('ui.common.view') }}">
                                            <x-icon name="eye" class="size-4" />
                                        </a>
                                        @unless ($isSelf)
                                            <form method="POST" action="{{ route('admin.users.status', $user) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="{{ $user->status === 'active' ? 'suspended' : 'active' }}">
                                                <button type="submit" class="btn-icon" aria-label="{{ $user->status === 'active' ? __('ui.admin.users.suspend') : __('ui.admin.users.activate') }}">
                                                    <x-icon :name="$user->status === 'active' ? 'alert' : 'check-circle'" class="size-4" />
                                                </button>
                                            </form>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.users.destroy', $user) }}"
                                                x-data
                                                @submit="if (! confirm(@js(__('ui.admin.users.confirm_delete', ['name' => $user->name])))) { $event.preventDefault(); }"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-icon text-red-600" aria-label="{{ __('ui.common.delete') }}">
                                                    <x-icon name="trash" class="size-4" />
                                                </button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $users])
        @endif
    </form>
@endsection
