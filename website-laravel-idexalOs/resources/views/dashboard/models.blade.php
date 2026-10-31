@extends('layouts.dashboard')

@section('title', __('ui.dashboard.models.title'))
@section('page', __('ui.dashboard.nav.models'))

@section('content')
    @php($canManageProviders = auth()->user()?->isAdmin() ?? false)

    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.dashboard.models.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.dashboard.models.subtitle') }}</p>
        </div>
        @if ($canManageProviders)
            <a href="{{ route('admin.providers.index') }}" class="btn btn-outline btn-sm h-10 px-4">
                <x-icon name="server" class="size-4" />
                {{ __('ui.dashboard.models.manage_providers') }}
            </a>
        @else
            <a href="{{ route('dashboard.support.create') }}" class="btn btn-outline btn-sm h-10 px-4">
                <x-icon name="message" class="size-4" />
                {{ __('ui.dashboard.models.contact_support') }}
            </a>
        @endif
    </div>

    <div class="card overflow-hidden">
        @if ($models === [])
            <div class="p-10 text-center">
                <span class="mx-auto grid size-11 place-items-center rounded-2xl bg-paper text-ink-400"><x-icon name="cpu" class="size-5" /></span>
                <h2 class="mt-4 font-semibold">{{ __('ui.dashboard.models.empty_title') }}</h2>
                <p class="mx-auto mt-1.5 max-w-md text-xs leading-6 text-ink-400">
                    {{ $canManageProviders ? __('ui.dashboard.models.empty_admin') : __('ui.dashboard.models.empty_member') }}
                </p>
                <div class="mt-5 flex flex-wrap justify-center gap-2">
                    @if ($canManageProviders)
                        <a href="{{ route('admin.providers.index') }}" class="btn btn-primary btn-sm h-10 px-4">{{ __('ui.dashboard.models.manage_providers') }}</a>
                        <a href="{{ route('admin.catalog.index') }}" class="btn btn-outline btn-sm h-10 px-4">{{ __('ui.dashboard.models.diagnose_catalog') }}</a>
                    @else
                        <a href="{{ route('dashboard.support.create') }}" class="btn btn-primary btn-sm h-10 px-4">{{ __('ui.dashboard.models.contact_support') }}</a>
                    @endif
                </div>
            </div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.dashboard.models.table_model') }}</th>
                            <th>{{ __('ui.dashboard.models.table_provider') }}</th>
                            <th>{{ __('ui.dashboard.models.table_driver') }}</th>
                            <th class="text-end">{{ __('ui.dashboard.models.table_context') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($models as $model)
                            <tr>
                                <td>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-medium text-ink-950">{{ $model['name'] }}</p>
                                        @if ($model['is_combo'])
                                            <span class="badge badge-lime text-[9px]">{{ __('ui.dashboard.models.combo_badge', ['count' => $model['members']]) }}</span>
                                        @endif
                                    </div>
                                    <p class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $model['model_id'] }}</p>
                                </td>
                                <td>
                                    <p class="text-sm">{{ $model['provider'] }}</p>
                                    <p class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $model['provider_slug'] }}</p>
                                </td>
                                <td class="text-xs text-ink-500">{{ $model['driver'] }}</td>
                                <td class="text-end font-mono text-xs">{{ $model['context'] === null ? '—' : compact_number($model['context']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
