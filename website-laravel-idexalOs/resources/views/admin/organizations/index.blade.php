@extends('layouts.admin')

@section('title', __('ui.admin.organizations.title'))
@section('page', __('ui.admin.nav.organizations'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.organizations.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.organizations.subtitle') }}</p>
        </div>
        <span class="badge badge-gray">{{ $organizations->total() }}</span>
    </div>

    <form method="GET" action="{{ route('admin.organizations.index') }}" class="flex flex-wrap items-center gap-2 rounded-2xl border border-ink-950/10 bg-white p-3">
        <div class="relative min-w-[220px] flex-1">
            <x-icon name="search" class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-400" />
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('ui.common.search') }}" class="input h-10 ps-10">
        </div>
        <select name="type" class="select h-10 w-40">
            <option value="">{{ __('ui.common.all') }} — {{ __('ui.common.type') }}</option>
            <option value="personal" @selected($type === 'personal')>{{ __('ui.settings.personal') }}</option>
            <option value="team" @selected($type === 'team')>{{ __('ui.settings.team') }}</option>
        </select>
        <select name="status" class="select h-10 w-36">
            <option value="">{{ __('ui.common.all') }} — {{ __('ui.common.status') }}</option>
            <option value="active" @selected($status === 'active')>{{ __('ui.statuses.active') }}</option>
            <option value="suspended" @selected($status === 'suspended')>{{ __('ui.statuses.suspended') }}</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm h-10 px-4">{{ __('ui.common.filter') }}</button>
        @if ($search !== '' || $type !== '' || $status !== '')
            <a href="{{ route('admin.organizations.index') }}" class="text-xs font-medium text-ink-400 hover:text-ink-950">{{ __('ui.common.reset') }}</a>
        @endif
    </form>

    <div class="card overflow-hidden">
        @if ($organizations->isEmpty())
            <div class="p-12 text-center text-sm text-ink-400">{{ __('ui.common.no_results') }}</div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.admin.organizations.name') }}</th>
                            <th>{{ __('ui.admin.organizations.owner') }}</th>
                            <th>{{ __('ui.common.type') }}</th>
                            <th>{{ __('ui.team.members') }}</th>
                            <th>{{ __('ui.common.api_keys') }}</th>
                            <th>{{ __('ui.billing.invoices') }}</th>
                            <th>{{ __('ui.common.status') }}</th>
                            <th>{{ __('ui.common.created_at') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($organizations as $organization)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-ink-950 font-mono text-[10px] font-semibold text-lime">{{ $organization->initials() }}</span>
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.organizations.show', $organization) }}" class="block truncate text-sm font-semibold text-ink-950 hover:underline">{{ $organization->name }}</a>
                                            <p class="truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $organization->slug }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <p class="text-xs">{{ $organization->owner?->name ?? '—' }}</p>
                                    <p class="truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $organization->owner?->email }}</p>
                                </td>
                                <td><span class="badge badge-gray">{{ $organization->isPersonal() ? __('ui.settings.personal') : __('ui.settings.team') }}</span></td>
                                <td class="font-mono text-xs">{{ $organization->memberships_count }}</td>
                                <td class="font-mono text-xs">{{ $organization->api_keys_count }}</td>
                                <td class="font-mono text-xs">{{ $organization->invoices_count }}</td>
                                <td><span class="badge {{ $organization->status === 'active' ? 'badge-green' : 'badge-red' }}">{{ __('ui.statuses.'.$organization->status) }}</span></td>
                                <td class="whitespace-nowrap text-xs text-ink-400">{{ $organization->created_at?->translatedFormat('d M Y') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.organizations.show', $organization) }}" class="btn btn-ghost btn-sm">{{ __('ui.common.view') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $organizations])
        @endif
    </div>
@endsection
