@extends('layouts.admin')

@section('title', __('ui.admin.proxy_providers.title'))
@section('page', __('ui.admin.nav.proxy_providers'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.proxy_providers.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.proxy_providers.subtitle') }}</p>
        </div>
        <a href="{{ route('admin.proxy-providers.create') }}" class="btn btn-primary btn-sm h-10 px-4">
            <x-icon name="plus" class="size-4" />
            {{ __('ui.admin.proxy_providers.create') }}
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($providers->isEmpty())
        <div class="card p-12 text-center">
            <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-paper text-ink-300"><x-icon name="globe" class="size-6" /></span>
            <p class="mt-3 text-sm font-semibold">{{ __('ui.admin.proxy_providers.empty') }}</p>
            <p class="mt-1 text-xs text-ink-400">{{ __('ui.admin.proxy_providers.subtitle') }}</p>
        </div>
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.common.name') }}</th>
                            <th>{{ __('ui.admin.proxy_providers.driver') }}</th>
                            <th>{{ __('ui.admin.proxy_providers.protocol') }}</th>
                            <th>{{ __('ui.admin.proxy_providers.plans') }}</th>
                            <th>{{ __('ui.common.priority') }}</th>
                            <th>{{ __('ui.common.status') }}</th>
                            <th class="text-end">{{ __('ui.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($providers as $provider)
                            <tr>
                                <td>
                                    <div class="font-medium">{{ $provider->name }}</div>
                                    <div class="mt-0.5 font-mono text-[10px] text-ink-400" dir="ltr">{{ $provider->slug }}</div>
                                </td>
                                <td><span class="badge badge-gray">{{ $provider->driver }}</span></td>
                                <td><span class="font-mono text-xs uppercase">{{ $provider->protocol }}</span></td>
                                <td>
                                    <div class="flex max-w-56 flex-wrap gap-1">
                                        @forelse ($provider->plans as $plan)
                                            <span class="badge badge-gray">{{ $plan->name }}</span>
                                        @empty
                                            <span class="text-xs text-ink-400">{{ __('ui.admin.proxy_providers.all_plans') }}</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="font-mono text-xs">{{ $provider->priority }}</td>
                                <td>
                                    <span class="badge {{ $provider->is_active ? 'badge-green' : 'badge-gray' }}">
                                        {{ $provider->is_active ? __('ui.common.active') : __('ui.common.inactive') }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.proxy-providers.edit', $provider) }}" class="btn btn-outline btn-sm">{{ __('ui.common.edit') }}</a>
                                        <form method="POST" action="{{ route('admin.proxy-providers.destroy', $provider) }}" onsubmit="return confirm('{{ __('ui.admin.proxy_providers.delete_confirm') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm text-red-600">{{ __('ui.common.delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
