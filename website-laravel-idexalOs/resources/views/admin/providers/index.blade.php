@extends('layouts.admin')

@section('title', __('ui.admin.providers.title'))
@section('page', __('ui.admin.nav.providers'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.providers.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.providers.subtitle') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.providers.health-check-all') }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm h-10 px-4">
                    <x-icon name="wifi" class="size-4" />
                    {{ __('ui.admin.providers.health_check_all') }}
                </button>
            </form>
            <form method="POST" action="{{ route('admin.provider-key-management.rotate-all') }}" x-data @submit="if (! confirm(@js(__('ui.admin.providers.rotate_confirm')))) { $event.preventDefault(); }">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm h-10 px-4">
                    <x-icon name="refresh" class="size-4" />
                    {{ __('ui.admin.providers.rotate_all') }}
                </button>
            </form>
            <a href="{{ route('admin.providers.create') }}" class="btn btn-primary btn-sm h-10 px-4">
                <x-icon name="plus" class="size-4" />
                {{ __('ui.admin.providers.create') }}
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            <x-icon name="check-circle" class="mt-0.5 size-4" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @error('provider')
        <div class="alert alert-error">{{ $message }}</div>
    @enderror

    @php($byokOff = ! $byokEnabled)
    <div class="card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="font-semibold">{{ __('ui.admin.providers.byok_title') }}</h2>
                <p class="mt-1 max-w-3xl text-xs leading-5 text-ink-400">{{ __('ui.admin.providers.byok_hint') }}</p>
            </div>
            <span class="badge {{ $byokOff ? 'badge-amber' : 'badge-green' }}">
                {{ $byokOff ? __('ui.admin.providers.byok_off_badge') : __('ui.admin.providers.byok_on_badge') }}
            </span>
        </div>
        <form method="POST" action="{{ route('admin.providers.byok') }}" class="mt-4 flex flex-wrap items-center gap-3">
            @csrf
            <input type="hidden" name="byok_enabled" value="{{ $byokEnabled ? '0' : '1' }}">
            <button type="submit" class="btn btn-outline h-10 px-4">
                {{ $byokEnabled ? __('ui.admin.providers.byok_disable') : __('ui.admin.providers.byok_enable') }}
            </button>
            <p class="text-[11px] text-ink-400">{{ __('ui.admin.providers.byok_note') }}</p>
        </form>
    </div>

    @php($assignmentOpen = ! $explicitAssignmentRequired)
    <div class="card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="font-semibold">{{ __('ui.admin.providers.assignment_title') }}</h2>
                <p class="mt-1 max-w-3xl text-xs leading-5 text-ink-400">{{ __('ui.admin.providers.assignment_hint') }}</p>
            </div>
            <span class="badge {{ $assignmentOpen ? 'badge-gray' : 'badge-green' }}">
                {{ $assignmentOpen ? __('ui.admin.providers.assignment_open_badge') : __('ui.admin.providers.assignment_strict_badge') }}
            </span>
        </div>
        <form method="POST" action="{{ route('admin.providers.assignment') }}" class="mt-4 flex flex-wrap items-center gap-3" x-data
            @submit="if (! {{ $explicitAssignmentRequired ? 'true' : 'false' }} && ! confirm(@js(__('ui.admin.providers.assignment_confirm')))) { $event.preventDefault(); }">
            @csrf
            <input type="hidden" name="explicit_assignment_required" value="{{ $explicitAssignmentRequired ? '0' : '1' }}">
            <button type="submit" class="btn btn-outline h-10 px-4">
                {{ $explicitAssignmentRequired ? __('ui.admin.providers.assignment_relax') : __('ui.admin.providers.assignment_require') }}
            </button>
            <p class="text-[11px] text-ink-400">{{ __('ui.admin.providers.assignment_note') }}</p>
        </form>
    </div>

    @if ($providers->isEmpty())
        <div class="card p-12 text-center">
            <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-paper text-ink-300"><x-icon name="server" class="size-6" /></span>
            <p class="mt-3 text-sm font-semibold">{{ __('ui.admin.providers.empty') }}</p>
            <p class="mt-1 text-xs text-ink-400">{{ __('ui.admin.providers.subtitle') }}</p>
            <a href="{{ route('admin.providers.create') }}" class="btn btn-primary btn-sm mt-5">{{ __('ui.admin.providers.create') }}</a>
        </div>
    @else
        <div class="grid gap-4 xl:grid-cols-2">
            @foreach ($providers as $provider)
                <div class="card overflow-hidden">
                    <div class="flex items-start gap-4 p-5">
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl {{ $provider->is_active ? 'bg-ink-950 text-lime' : 'bg-paper text-ink-400' }}">
                            <x-icon :name="$provider->driver === 'anthropic' ? 'sparkles' : 'server'" class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-sm font-semibold text-ink-950">{{ $provider->name }}</h2>
                                <span class="badge {{ $provider->is_active ? 'badge-green' : 'badge-gray' }}">{{ $provider->is_active ? __('ui.common.active') : __('ui.common.inactive') }}</span>
                                <span class="badge badge-gray">{{ $provider->driver }}</span>
                            </div>
                            <p class="mt-1 truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $provider->base_url }}</p>

                            {{-- Which plans this provider belongs to, read the same way
                                 the proxy manager lists its providers. A provider with no
                                 plan rows serves every plan, so that is said in words
                                 rather than left blank. --}}
                            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __('ui.admin.providers.plans') }}</span>
                                @forelse ($provider->plans as $plan)
                                    <a href="{{ route('admin.plans.edit', $plan) }}" class="badge badge-gray hover:border-ink-950/25">{{ $plan->name }}</a>
                                @empty
                                    <span class="badge bg-lime/15 text-ink-950">{{ __('ui.admin.providers.all_plans') }}</span>
                                @endforelse
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-3 text-[10px] text-ink-400">
                                @php($healthLog = $health->get((string) $provider->getKey()))
                                @php($coolingCount = $cooling[(string) $provider->getKey()] ?? 0)
                                <span class="badge {{ $healthLog?->status === 'healthy' ? 'badge-green' : ($healthLog?->status === 'degraded' ? 'badge-amber' : ($healthLog?->status === 'unhealthy' ? 'badge-red' : 'badge-gray')) }}">
                                    {{ $healthLog ? __('ui.admin.providers.health_'.$healthLog->status) : __('ui.admin.providers.health_unknown') }}
                                </span>
                                @if ($coolingCount > 0)
                                    <span class="badge badge-amber" title="{{ __('ui.admin.providers.cooling_hint') }}">
                                        {{ __('ui.admin.providers.cooling') }} · <span class="font-mono" dir="ltr">{{ $coolingCount }}</span>
                                    </span>
                                @endif
                                <span>{{ $provider->models_count }} {{ __('ui.admin.providers.models') }}</span>
                                <span>·</span>
                                <span>{{ $provider->api_keys_count }} {{ __('ui.admin.providers.keys_total') }}</span>
                                <span>·</span>
                                <span>{{ __('ui.admin.providers.priority') }} {{ $provider->priority }}</span>
                                <span>·</span>
                                <span class="font-mono" dir="ltr">{{ $provider->maskedKey() }}</span>
                                @if ($healthLog?->check_time)
                                    <span>·</span>
                                    <span>{{ __('ui.admin.providers.health_checked') }} {{ $healthLog->check_time->diffForHumans() }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 border-t border-ink-950/10 bg-paper/50 px-5 py-3">
                        <a href="{{ route('admin.providers.edit', $provider) }}" class="btn btn-outline btn-sm">{{ __('ui.common.edit') }}</a>
                        <form method="POST" action="{{ route('admin.providers.toggle', $provider) }}">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-sm">
                                <x-icon :name="$provider->is_active ? 'pause' : 'play'" class="size-3.5" />
                                {{ $provider->is_active ? __('ui.admin.providers.disable') : __('ui.admin.providers.enable') }}
                            </button>
                        </form>
                        <a href="{{ route('admin.provider-key-management.edit-keys', $provider) }}" class="btn btn-ghost btn-sm">
                            <x-icon name="key" class="size-3.5" />
                            {{ __('ui.admin.providers.manage_keys') }}
                            @if ($provider->api_keys_count > 0)
                                <span class="badge badge-gray">{{ $provider->api_keys_count }}</span>
                            @endif
                        </a>
                        <a href="{{ route('admin.providers.models.fetch', $provider) }}" class="btn btn-ghost btn-sm">
                            <x-icon name="download" class="size-3.5" />
                            {{ __('ui.admin.providers.fetch_models') }}
                        </a>
                        <form method="POST" action="{{ route('admin.providers.test', $provider) }}">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-sm">
                                <x-icon name="wifi" class="size-3.5" />
                                {{ __('ui.admin.providers.test') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.providers.health-check', $provider) }}">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-sm">
                                <x-icon name="shield" class="size-3.5" />
                                {{ __('ui.admin.providers.health_check') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.providers.destroy', $provider) }}" class="ms-auto" x-data @submit="if (! confirm(@js(__('ui.admin.providers.confirm_delete')))) { $event.preventDefault(); }">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-3.5" /></button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
