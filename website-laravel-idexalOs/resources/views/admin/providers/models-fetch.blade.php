@extends('layouts.admin')

@section('title', __('ui.admin.providers.fetch_models'))
@section('page', __('ui.admin.nav.providers'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a href="{{ route('admin.providers.edit', $provider) }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-400 transition hover:text-ink-950">
                <x-icon name="arrow-right" class="size-3.5 rtl:rotate-180" />
                {{ __('ui.admin.providers.edit') }}
            </a>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.providers.fetch_models') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">
                {{ __('ui.admin.providers.fetch_models_subtitle') }}
                <span class="font-mono text-xs text-ink-950" dir="ltr">{{ $provider->slug }}</span>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.providers.models.fetch', $provider) }}" class="btn btn-outline btn-sm h-10 px-4">
                <x-icon name="refresh" class="size-4" />
                {{ __('ui.admin.providers.fetch_again') }}
            </a>
            <a href="{{ route('admin.providers.edit', $provider) }}" class="btn btn-primary btn-sm h-10 px-4">
                <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
                {{ __('ui.admin.providers.edit') }}
            </a>
        </div>
    </div>

    @if ($error)
        <div class="alert alert-error">
            <x-icon name="alert" class="mt-0.5 size-4" />
            <div>
                <p class="text-sm font-medium">{{ __('ui.admin.providers.fetch_failed') }}</p>
                <p class="mt-1 text-xs">{{ $error }}</p>
            </div>
        </div>
    @elseif (empty($available))
        <div class="alert alert-warning">
            <x-icon name="alert" class="mt-0.5 size-4" />
            <span>{{ __('ui.admin.providers.fetch_empty') }}</span>
        </div>
    @else
        <form method="POST" action="{{ route('admin.providers.models.import', $provider) }}" class="card overflow-hidden">
            @csrf

            <div class="flex flex-col gap-3 border-b border-ink-950/10 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-semibold">{{ __('ui.admin.providers.fetch_available') }}</h2>
                    <p class="mt-0.5 text-xs text-ink-400">
                        <span id="model-count" class="font-mono font-semibold text-ink-950">0</span>
                        {{ __('ui.admin.providers.fetch_selected') }}
                        · {{ count($available) }} {{ __('ui.admin.providers.fetch_total') }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <input id="model-search" type="search" placeholder="{{ __('ui.admin.providers.fetch_search') }}" class="input h-10 w-56" dir="auto">
                    <button type="button" class="btn btn-outline btn-sm h-10 px-3" onclick="toggleAllModels(true)">{{ __('ui.admin.providers.select_all') }}</button>
                    <button type="button" class="btn btn-ghost btn-sm h-10 px-3" onclick="toggleAllModels(false)">{{ __('ui.admin.providers.select_none') }}</button>
                </div>
            </div>

            <div class="max-h-[28rem] divide-y divide-ink-950/[0.06] overflow-y-auto">
                @foreach ($available as $modelId)
                    @php($alreadyAdded = in_array($modelId, $existing, true))
                    <label data-model-row="{{ $modelId }}" class="flex cursor-pointer items-center gap-3 px-5 py-3 {{ $alreadyAdded ? 'opacity-60' : '' }}">
                        <input type="checkbox" name="models[]" value="{{ $modelId }}" class="checkbox" @disabled($alreadyAdded) onchange="updateModelCount()">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-mono text-xs" dir="ltr">{{ $modelId }}</span>
                        </span>
                        @if ($alreadyAdded)
                            <span class="badge badge-gray">{{ __('ui.admin.providers.fetch_added') }}</span>
                        @endif
                    </label>
                @endforeach
            </div>

            <div class="border-t border-ink-950/10 bg-paper/40 p-5">
                <h3 class="text-sm font-semibold">{{ __('ui.admin.providers.import_defaults') }}</h3>
                <p class="mt-0.5 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.providers.import_defaults_hint') }}</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="label" for="import-input-price">{{ __('ui.admin.providers.input_price') }}</label>
                        <input id="import-input-price" type="number" name="input_price" value="{{ old('input_price', 0) }}" min="0" step="0.000001" class="input h-10 font-mono" dir="ltr">
                    </div>
                    <div>
                        <label class="label" for="import-output-price">{{ __('ui.admin.providers.output_price') }}</label>
                        <input id="import-output-price" type="number" name="output_price" value="{{ old('output_price', 0) }}" min="0" step="0.000001" class="input h-10 font-mono" dir="ltr">
                    </div>
                    <div>
                        <label class="label" for="import-context">{{ __('ui.admin.providers.context_window') }}</label>
                        <input id="import-context" type="number" name="context_window" value="{{ old('context_window') }}" min="1" class="input h-10 font-mono" dir="ltr">
                    </div>
                    <div class="flex items-end">
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ink-950/10 p-3 text-sm">
                            <input type="checkbox" name="is_active" value="1" class="checkbox" checked>
                            {{ __('ui.common.active') }}
                        </label>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-[11px] leading-5 text-ink-400">{{ __('ui.admin.providers.import_edit_later') }}</p>
                    <button type="submit" class="btn btn-primary h-10 px-8">
                        <x-icon name="download" class="size-4" />
                        {{ __('ui.admin.providers.import_selected') }}
                    </button>
                </div>
            </div>
        </form>
    @endif

    <script>
        function toggleAllModels(checked) {
            document.querySelectorAll('input[name="models[]"]:not(:disabled)').forEach(function (el) {
                el.checked = checked;
            });
            updateModelCount();
        }

        function updateModelCount() {
            var count = document.querySelectorAll('input[name="models[]"]:checked').length;
            var target = document.getElementById('model-count');
            if (target) {
                target.textContent = count;
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            var search = document.getElementById('model-search');

            if (search) {
                search.addEventListener('input', function () {
                    var query = search.value.toLowerCase();

                    document.querySelectorAll('[data-model-row]').forEach(function (row) {
                        row.hidden = query !== '' && ! row.dataset.modelRow.toLowerCase().includes(query);
                    });
                });
            }

            updateModelCount();
        });
    </script>
@endsection
