@extends('layouts.admin')

@section('title', __('ui.admin.extensions.title'))
@section('page', __('ui.admin.nav.extensions'))

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.extensions.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.extensions.subtitle') }}</p>
        </div>

        <form
            method="POST"
            action="{{ route('admin.extensions.refresh') }}"
            onsubmit="return confirm(@js(__('ui.admin.extensions.refresh_confirm')))"
        >
            @csrf
            <button type="submit" class="btn btn-outline h-10">
                <x-icon name="refresh" class="size-4" />
                {{ __('ui.admin.extensions.refresh') }}
            </button>
        </form>
    </div>

    <form method="GET" action="{{ route('admin.extensions.index') }}" class="flex flex-wrap items-end gap-2">
        <input type="hidden" name="tab" value="{{ $activeTab }}">

        <div class="min-w-[220px] flex-1">
            <label class="label" for="q">{{ __('ui.admin.extensions.search_label') }}</label>
            <input
                id="q"
                name="q"
                value="{{ $searchQuery }}"
                class="input h-10"
                placeholder="{{ __('ui.admin.extensions.search_hint') }}"
            >
        </div>

        <button type="submit" class="btn btn-primary h-10">{{ __('ui.admin.extensions.search') }}</button>

        @if ($searchQuery !== '')
            <a href="{{ route('admin.extensions.index', ['tab' => $activeTab]) }}" class="btn btn-ghost h-10">
                {{ __('ui.admin.extensions.clear') }}
            </a>
        @endif
    </form>

    <div class="flex flex-wrap items-center gap-1.5">
        @foreach ($tabs as $tab)
            <a
                href="{{ route('admin.extensions.index', array_filter(['tab' => $tab, 'q' => $searchQuery ?: null])) }}"
                class="badge {{ $activeTab === $tab ? 'badge-lime' : 'badge-gray' }}"
                @if ($activeTab === $tab) aria-current="true" @endif
            >
                {{ __('ui.admin.extensions.tabs.'.$tab) }}
                <span class="font-mono">{{ $counts[$tab] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    @if ($installed !== [])
        <div class="card card-pad">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold">{{ __('ui.admin.extensions.installed_title') }}</h2>
                <span class="badge badge-green">{{ __('ui.admin.extensions.installed_count', ['count' => count($installed)]) }}</span>
            </div>

            <ul class="mt-3 grid gap-2 md:grid-cols-2">
                @foreach ($installed as $slug => $installation)
                    <li class="flex items-center justify-between gap-3 rounded-xl bg-ink-950/[0.02] px-3 py-2">
                        <div class="min-w-0">
                            <p class="truncate text-xs text-ink-600">{{ $installation['name'] }}</p>
                            <p class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $slug }}</p>
                        </div>

                        <form method="POST" action="{{ route('admin.extensions.uninstall', $installation['id']) }}">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-sm text-red-600">
                                {{ __('ui.admin.extensions.uninstall') }}
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($items->isEmpty())
        <div class="card p-12 text-center">
            <p class="text-sm text-ink-400">{{ __('ui.admin.extensions.empty') }}</p>
        </div>
    @else
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($items as $item)
                @php($installation = $installed[$item['slug']] ?? null)
                @php($type = in_array($item['type'] ?? '', ['connector', 'skill', 'plugin'], true) ? __('ui.admin.extensions.types.'.$item['type']) : (string) ($item['type'] ?? ''))

                <article class="card card-pad flex flex-col gap-2">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="font-semibold leading-6">{{ $item['name'] }}</h2>
                        <span class="badge badge-gray shrink-0">{{ $type }}</span>
                    </div>

                    <p class="text-[11px] leading-5 text-ink-500">{{ $item['description'] }}</p>

                    @if (! empty($item['tags']))
                        <div class="flex flex-wrap gap-1">
                            @foreach ($item['tags'] as $tag)
                                <span class="badge badge-gray">{{ $tag }}</span>
                            @endforeach
                        </div>
                    @endif

                    <p class="mt-auto font-mono text-[10px] text-ink-400" dir="ltr">{{ $item['slug'] }} · {{ $item['version'] ?? '1.0.0' }}</p>

                    @if ($installation !== null)
                        <div class="flex items-center gap-2">
                            <span class="badge badge-green">{{ __('ui.admin.extensions.installed_badge') }}</span>
                            <form method="POST" action="{{ route('admin.extensions.uninstall', $installation['id']) }}" class="flex-1">
                                @csrf
                                <button type="submit" class="btn btn-outline btn-sm w-full">
                                    {{ __('ui.admin.extensions.uninstall') }}
                                </button>
                            </form>
                        </div>
                    @else
                        <form method="POST" action="{{ route('admin.extensions.install') }}">
                            @csrf
                            <input type="hidden" name="extension_slug" value="{{ $item['slug'] }}">
                            <button type="submit" class="btn btn-primary btn-sm w-full">
                                {{ __('ui.admin.extensions.install') }}
                            </button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
