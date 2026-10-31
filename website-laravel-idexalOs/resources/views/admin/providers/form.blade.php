@extends('layouts.admin')

@section('title', $provider->exists ? __('ui.admin.providers.edit') : __('ui.admin.providers.create'))
@section('page', __('ui.admin.nav.providers'))

@php
    $isEdit = $provider->exists;
    $action = $isEdit ? route('admin.providers.update', $provider) : route('admin.providers.store');
    $headersJson = old('headers_json', $provider->headers ? json_encode($provider->headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '');
    $selectedPlans = old('plans', $isEdit ? $provider->plans()->pluck('plans.id')->all() : []);
    $featureLabels = \App\Support\GatewayFeature::labels();
    $featureOverrides = old('feature_overrides', collect($featureKeys)->mapWithKeys(function ($feature) use ($provider) {
        $override = $provider->featureOverride($feature);

        return [$feature => $override === null ? 'inherit' : ($override ? 'on' : 'off')];
    })->all());
@endphp

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a href="{{ route('admin.providers.index') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-400 transition hover:text-ink-950">
                <x-icon name="arrow-right" class="size-3.5 rtl:rotate-180" />
                {{ __('ui.admin.providers.title') }}
            </a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ $isEdit ? $provider->name : __('ui.admin.providers.create') }}</h1>
                @if ($isEdit)
                    <span class="badge {{ $provider->is_active ? 'badge-green' : 'badge-gray' }}">{{ $provider->is_active ? __('ui.common.active') : __('ui.common.inactive') }}</span>
                @endif
            </div>
            <p class="mt-1.5 text-sm text-ink-500">{{ __('ui.admin.providers.subtitle') }}</p>
        </div>

        @if ($isEdit)
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.provider-key-management.edit-keys', $provider) }}" class="btn btn-outline btn-sm h-10 px-4">
                    <x-icon name="key" class="size-4" />
                    {{ __('ui.admin.providers.manage_keys') }}
                    @if ($keyCount > 0)
                        <span class="badge badge-gray">{{ $keyCount }}</span>
                    @endif
                </a>
                <form method="POST" action="{{ route('admin.providers.test', $provider) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm h-10 px-4">
                        <x-icon name="wifi" class="size-4" />
                        {{ __('ui.admin.providers.test') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.providers.destroy', $provider) }}" x-data @submit="if (! confirm(@js(__('ui.admin.providers.confirm_delete')))) { $event.preventDefault(); }">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-ghost btn-sm h-10 px-4 text-red-600">{{ __('ui.common.delete') }}</button>
                </form>
            </div>
        @endif
    </div>

    @error('provider')
        <div class="alert alert-error">{{ $message }}</div>
    @enderror

    <div class="grid gap-4 xl:grid-cols-[1.35fr_1fr]">
        <form method="POST" action="{{ $action }}" class="card card-pad space-y-5">
            @csrf
            @if ($isEdit)
                @method('PATCH')
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="name">{{ __('ui.common.name') }}</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $provider->name) }}" required maxlength="120" class="input h-10 @error('name') input-error @enderror">
                    @error('name')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="label" for="slug">{{ __('ui.admin.providers.slug') }}</label>
                    <input id="slug" type="text" name="slug" value="{{ old('slug', $provider->slug) }}" required maxlength="80" class="input h-10 font-mono @error('slug') input-error @enderror" dir="ltr">
                    @error('slug')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="label" for="driver">{{ __('ui.admin.providers.driver') }}</label>
                    <select id="driver" name="driver" class="select h-10">
                        @foreach ($drivers as $value => $label)
                            <option value="{{ $value }}" @selected(old('driver', $provider->driver) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('driver')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="label" for="priority">{{ __('ui.admin.providers.priority') }}</label>
                    <input id="priority" type="number" name="priority" min="0" max="10000" value="{{ old('priority', $provider->priority ?? 0) }}" class="input h-10 font-mono" dir="ltr">
                    @error('priority')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label class="label" for="base_url">{{ __('ui.admin.providers.base_url') }}</label>
                <input id="base_url" type="url" name="base_url" value="{{ old('base_url', $provider->base_url) }}" required maxlength="2048" class="input h-10 font-mono text-xs @error('base_url') input-error @enderror" dir="ltr" placeholder="https://api.openai.com/v1">
                @error('base_url')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1.5 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.providers.base_url_hint') }}</p>
            </div>

            <div>
                <label class="label" for="api_key">{{ __('ui.admin.providers.api_key') }}</label>
                <input id="api_key" type="password" name="api_key" value="" maxlength="1000" class="input h-10 font-mono text-xs @error('api_key') input-error @enderror" dir="ltr" autocomplete="new-password" placeholder="{{ $isEdit ? $provider->maskedKey() : 'sk-...' }}">
                @error('api_key')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1.5 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.providers.api_key_hint') }}</p>
                @if ($isEdit)
                    <p class="mt-1.5 text-[11px] leading-5 text-ink-400">
                        <a href="{{ route('admin.provider-key-management.edit-keys', $provider) }}" class="font-medium text-ink-950 underline">{{ __('ui.admin.providers.multi_key_hint') }}</a>
                    </p>
                @endif
                @if ($isEdit && $provider->api_key)
                    <label class="mt-2 flex items-center gap-2 text-xs text-red-600">
                        <input type="checkbox" name="clear_api_key" value="1" class="checkbox">
                        {{ __('ui.admin.providers.clear_api_key') }}
                    </label>
                @endif
            </div>

            <div>
                <label class="label" for="headers_json">{{ __('ui.admin.providers.headers') }}</label>
                <textarea id="headers_json" name="headers_json" rows="4" maxlength="5000" class="input resize-y font-mono text-[11px] @error('headers_json') input-error @enderror" dir="ltr" placeholder='{"X-Custom-Header": "value"}'>{{ $headersJson }}</textarea>
                @error('headers_json')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1.5 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.providers.headers_hint') }}</p>
            </div>

            <div>
                <label class="label" for="notes">{{ __('ui.common.notes') }}</label>
                <textarea id="notes" name="notes" rows="3" maxlength="2000" class="input resize-y">{{ old('notes', $provider->notes) }}</textarea>
                @error('notes')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ink-950/10 p-3 text-sm">
                    <input type="checkbox" name="is_active" value="1" class="checkbox" @checked(old('is_active', $provider->is_active ?? true))>
                    {{ __('ui.common.active') }}
                </label>
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ink-950/10 p-3 text-sm">
                    <input type="checkbox" name="supports_streaming" value="1" class="checkbox" @checked(old('supports_streaming', $provider->supports_streaming ?? true))>
                    {{ __('ui.admin.providers.streaming') }}
                </label>
            </div>

            <div>
                <label class="label">{{ __('ui.admin.providers.feature_overrides') }}</label>
                <p class="mb-2 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.providers.feature_overrides_hint') }}</p>
                @include('admin.providers.partials.feature-overrides', ['selected' => $featureOverrides])
            </div>

            <div>
                <label class="label">{{ __('ui.admin.providers.available_plans') }}</label>
                <p class="mb-2 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.providers.available_plans_hint') }}</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($plans as $plan)
                        <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-ink-950/10 p-3 text-xs">
                            <input type="checkbox" name="plans[]" value="{{ $plan->getKey() }}" class="checkbox" @checked(in_array($plan->getKey(), $selectedPlans, true))>
                            <span class="font-medium">{{ $plan->name }}</span>
                            <span class="ms-auto font-mono text-[10px] text-ink-300" dir="ltr">{{ $plan->slug }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-2 border-t border-ink-950/10 pt-5">
                <a href="{{ route('admin.providers.index') }}" class="btn btn-outline h-10 flex-1">{{ __('ui.common.cancel') }}</a>
                <button type="submit" class="btn btn-primary h-10 flex-1">{{ __('ui.common.save') }}</button>
            </div>
        </form>

        <div class="space-y-4">
            <div class="rounded-3xl bg-ink-950 p-5 text-paper">
                <x-icon name="server" class="size-5 text-lime" />
                <h2 class="mt-3 font-semibold">{{ __('ui.admin.providers.info_title') }}</h2>
                <p class="mt-1 text-[11px] leading-5 text-paper/45">{{ __('ui.admin.providers.info_body') }}</p>
                <div class="mt-4 space-y-2 text-[10px]">
                    @foreach (['OpenAI', 'Anthropic', 'OpenAI compatible'] as $driverName)
                        <div class="flex items-center gap-2 text-paper/60">
                            <span class="size-1.5 rounded-full bg-lime"></span>
                            {{ $driverName }}
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card p-5">
                <h2 class="font-semibold">{{ __('ui.admin.providers.security') }}</h2>
                <ul class="mt-3 space-y-2 text-[11px] leading-5 text-ink-500">
                    <li class="flex items-start gap-2"><x-icon name="shield" class="mt-0.5 size-3.5 text-lime-dark" />{{ __('ui.admin.providers.security_1') }}</li>
                    <li class="flex items-start gap-2"><x-icon name="lock" class="mt-0.5 size-3.5 text-lime-dark" />{{ __('ui.admin.providers.security_2') }}</li>
                    <li class="flex items-start gap-2"><x-icon name="wifi" class="mt-0.5 size-3.5 text-lime-dark" />{{ __('ui.admin.providers.security_3') }}</li>
                </ul>
            </div>
        </div>
    </div>

    @if ($isEdit)
        <div class="card overflow-hidden">
            <div class="flex flex-col gap-2 border-b border-ink-950/10 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-semibold">{{ __('ui.admin.providers.models_title') }}</h2>
                    <p class="mt-0.5 text-xs text-ink-400">{{ __('ui.admin.providers.models_subtitle') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="badge badge-gray">{{ $models->count() }}</span>
                    <a href="{{ route('admin.providers.models.fetch', $provider) }}" class="btn btn-outline btn-sm h-10 px-4">
                        <x-icon name="download" class="size-4" />
                        {{ __('ui.admin.providers.fetch_models') }}
                    </a>
                </div>
            </div>

            @if ($models->isEmpty())
                <p class="p-8 text-center text-xs text-ink-400">{{ __('ui.admin.providers.no_models') }}</p>
            @else
                <div class="divide-y divide-ink-950/[0.06]">
                    @foreach ($models as $model)
                        <div x-data="{ editing: false }">
                            <div class="flex flex-wrap items-center gap-3 p-4">
                                <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-paper text-ink-500"><x-icon name="cpu" class="size-4" /></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate text-sm font-semibold">{{ $model->display_name }}</p>
                                        <span class="badge {{ $model->is_active ? 'badge-green' : 'badge-gray' }}">{{ $model->is_active ? __('ui.common.active') : __('ui.common.inactive') }}</span>
                                    </div>
                                    <p class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $model->model_id }}</p>
                                </div>
                                <div class="flex items-center gap-3 text-[10px] text-ink-400">
                                    <span class="font-mono" dir="ltr">${{ number_format((float) $model->input_price, 2) }} / ${{ number_format((float) $model->output_price, 2) }}</span>
                                    <span>{{ $model->context_window ? compact_number($model->context_window).' ctx' : '—' }}</span>
                                </div>
                                <button type="button" class="btn btn-ghost btn-sm" @click="editing = ! editing">{{ __('ui.common.edit') }}</button>
                                <form method="POST" action="{{ route('admin.providers.models.destroy', [$provider, $model]) }}" x-data @submit="if (! confirm(@js(__('ui.admin.providers.confirm_model_delete')))) { $event.preventDefault(); }">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-3.5" /></button>
                                </form>
                            </div>

                            <div x-show="editing" x-cloak class="border-t border-ink-950/[0.06] bg-paper/50 p-5">
                                <form method="POST" action="{{ route('admin.providers.models.update', [$provider, $model]) }}" class="space-y-4">
                                    @csrf
                                    @method('PATCH')
                                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                        <div>
                                            <label class="label" for="model-id-{{ $model->id }}">Model ID</label>
                                            <input id="model-id-{{ $model->id }}" type="text" name="model_id" value="{{ $model->model_id }}" required maxlength="190" class="input h-10 font-mono text-xs" dir="ltr">
                                        </div>
                                        <div>
                                            <label class="label" for="display-name-{{ $model->id }}">{{ __('ui.common.name') }}</label>
                                            <input id="display-name-{{ $model->id }}" type="text" name="display_name" value="{{ $model->display_name }}" required maxlength="190" class="input h-10">
                                        </div>
                                        <div>
                                            <label class="label" for="context-{{ $model->id }}">Context window</label>
                                            <input id="context-{{ $model->id }}" type="number" name="context_window" value="{{ $model->context_window }}" min="1" class="input h-10 font-mono" dir="ltr">
                                        </div>
                                        <div>
                                            <label class="label" for="max-output-{{ $model->id }}">Max output</label>
                                            <input id="max-output-{{ $model->id }}" type="number" name="max_output_tokens" value="{{ $model->max_output_tokens }}" min="1" class="input h-10 font-mono" dir="ltr">
                                        </div>
                                        <div>
                                            <label class="label" for="input-price-{{ $model->id }}">Input / 1M</label>
                                            <input id="input-price-{{ $model->id }}" type="number" name="input_price" value="{{ $model->input_price }}" required min="0" step="0.000001" class="input h-10 font-mono" dir="ltr">
                                        </div>
                                        <div>
                                            <label class="label" for="output-price-{{ $model->id }}">Output / 1M</label>
                                            <input id="output-price-{{ $model->id }}" type="number" name="output_price" value="{{ $model->output_price }}" required min="0" step="0.000001" class="input h-10 font-mono" dir="ltr">
                                        </div>
                                    </div>

                                    <div>
                                        <label class="label" for="description-{{ $model->id }}">{{ __('ui.common.description') }}</label>
                                        <input id="description-{{ $model->id }}" type="text" name="description" value="{{ $model->description }}" maxlength="1000" class="input h-10">
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                        @foreach ($capabilities as $capability)
                                            <label class="flex items-center gap-2 text-xs">
                                                <input type="checkbox" name="capabilities[{{ $capability }}]" value="1" class="checkbox" @checked(data_get($model->capabilities, $capability, false))>
                                                {{ $capability }}
                                            </label>
                                        @endforeach
                                    </div>

                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="text-xs text-ink-400">Reasoning efforts</span>
                                        @foreach ($reasoningOptions as $effort)
                                            <label class="flex items-center gap-2 text-xs">
                                                <input type="checkbox" name="reasoning_options[]" value="{{ $effort }}" class="checkbox" @checked(in_array($effort, $model->reasoning_options ?? [], true))>
                                                {{ $effort }}
                                            </label>
                                        @endforeach
                                    </div>

                                    <details class="rounded-xl border border-ink-950/10 p-3">
                                        <summary class="cursor-pointer text-xs font-medium text-ink-500">{{ __('ui.admin.providers.feature_overrides') }}</summary>
                                        <p class="mt-2 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.providers.feature_overrides_hint') }}</p>
                                        <div class="mt-3">
                                            @include('admin.providers.partials.feature-overrides', [
                                                'selected' => collect($featureKeys)->mapWithKeys(function ($feature) use ($model) {
                                                    $override = $model->featureOverride($feature);

                                                    return [$feature => $override === null ? 'inherit' : ($override ? 'on' : 'off')];
                                                })->all(),
                                                'field' => 'feature_overrides',
                                            ])
                                        </div>
                                    </details>

                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div class="flex items-center gap-4">
                                            <label class="flex items-center gap-2 text-xs">
                                                <input type="checkbox" name="is_active" value="1" class="checkbox" @checked($model->is_active)>
                                                {{ __('ui.common.active') }}
                                            </label>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs text-ink-400">{{ __('ui.admin.plans.sort_order') }}</span>
                                                <input type="number" name="sort_order" value="{{ $model->sort_order }}" min="0" max="10000" class="input h-8 w-20 font-mono text-xs" dir="ltr">
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm">{{ __('ui.common.save') }}</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="border-t border-ink-950/10 bg-paper/40 p-5">
                <h3 class="text-sm font-semibold">{{ __('ui.admin.providers.add_model') }}</h3>
                <form method="POST" action="{{ route('admin.providers.models.store', $provider) }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <label class="label" for="new-model-id">Model ID</label>
                            <input id="new-model-id" type="text" name="model_id" value="{{ old('model_id') }}" required maxlength="190" class="input h-10 font-mono text-xs @error('model_id') input-error @enderror" dir="ltr" placeholder="gpt-4o-mini">
                            @error('model_id')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="label" for="new-display-name">{{ __('ui.common.name') }}</label>
                            <input id="new-display-name" type="text" name="display_name" value="{{ old('display_name') }}" required maxlength="190" class="input h-10 @error('display_name') input-error @enderror" placeholder="GPT-4o mini">
                            @error('display_name')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="label" for="new-context">Context window</label>
                            <input id="new-context" type="number" name="context_window" value="{{ old('context_window') }}" min="1" class="input h-10 font-mono" dir="ltr">
                        </div>
                        <div>
                            <label class="label" for="new-max-output">Max output</label>
                            <input id="new-max-output" type="number" name="max_output_tokens" value="{{ old('max_output_tokens') }}" min="1" class="input h-10 font-mono" dir="ltr">
                        </div>
                        <div>
                            <label class="label" for="new-input-price">Input / 1M</label>
                            <input id="new-input-price" type="number" name="input_price" value="{{ old('input_price', 0) }}" required min="0" step="0.000001" class="input h-10 font-mono @error('input_price') input-error @enderror" dir="ltr">
                            @error('input_price')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="label" for="new-output-price">Output / 1M</label>
                            <input id="new-output-price" type="number" name="output_price" value="{{ old('output_price', 0) }}" required min="0" step="0.000001" class="input h-10 font-mono @error('output_price') input-error @enderror" dir="ltr">
                            @error('output_price')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label class="label" for="new-description">{{ __('ui.common.description') }}</label>
                        <input id="new-description" type="text" name="description" value="{{ old('description') }}" maxlength="1000" class="input h-10">
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach ($capabilities as $capability)
                            <label class="flex items-center gap-2 text-xs">
                                <input type="checkbox" name="capabilities[{{ $capability }}]" value="1" class="checkbox" @checked(in_array($capability, ['chat', 'streaming'], true))>
                                {{ $capability }}
                            </label>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-xs text-ink-400">Reasoning efforts</span>
                        @foreach ($reasoningOptions as $effort)
                            <label class="flex items-center gap-2 text-xs">
                                <input type="checkbox" name="reasoning_options[]" value="{{ $effort }}" class="checkbox" @checked(in_array($effort, old('reasoning_options', []), true))>
                                {{ $effort }}
                            </label>
                        @endforeach
                    </div>

                    <details class="rounded-xl border border-ink-950/10 p-3">
                        <summary class="cursor-pointer text-xs font-medium text-ink-500">{{ __('ui.admin.providers.feature_overrides') }}</summary>
                        <p class="mt-2 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.providers.feature_overrides_hint') }}</p>
                        <div class="mt-3">
                            @include('admin.providers.partials.feature-overrides', [
                                'selected' => old('feature_overrides', []),
                                'field' => 'feature_overrides',
                            ])
                        </div>
                    </details>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 text-xs">
                                <input type="checkbox" name="is_active" value="1" class="checkbox" checked>
                                {{ __('ui.common.active') }}
                            </label>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-ink-400">{{ __('ui.admin.plans.sort_order') }}</span>
                                <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" max="10000" class="input h-8 w-20 font-mono text-xs" dir="ltr">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('ui.admin.providers.add_model') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-4 overflow-hidden">
            <div class="flex flex-col gap-2 border-b border-ink-950/10 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-semibold">{{ __('ui.admin.providers.fallback_title') }}</h2>
                    <p class="mt-0.5 text-xs text-ink-400">{{ __('ui.admin.providers.fallback_subtitle') }}</p>
                </div>
                <span class="badge badge-gray">{{ $backupProviders->count() }}</span>
            </div>

            <form method="POST" action="{{ route('admin.providers.backup-chain', $provider) }}" class="space-y-4 p-5">
                @csrf
                <p class="text-[11px] leading-5 text-ink-400">{{ __('ui.admin.providers.fallback_hint') }}</p>

                @if ($candidateProviders->isEmpty())
                    <p class="rounded-xl border border-dashed border-ink-950/15 p-6 text-center text-xs text-ink-400">{{ __('ui.admin.providers.fallback_empty') }}</p>
                @else
                    <div class="divide-y divide-ink-950/[0.06] rounded-xl border border-ink-950/10">
                        @foreach ($candidateProviders as $candidate)
                            @php($position = $backupProviders->search(fn ($link) => $link->getKey() === $candidate->getKey()))
                            @php($inChain = $position !== false)
                            <label class="flex flex-wrap items-center gap-3 px-4 py-3">
                                <input type="checkbox" name="backup_providers[]" value="{{ $candidate->getKey() }}" class="checkbox" @checked($inChain)>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium">{{ $candidate->name }}</span>
                                    <span class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $candidate->slug }}</span>
                                </span>
                                <span class="flex items-center gap-2">
                                    <span class="text-[10px] text-ink-400">{{ __('ui.admin.providers.fallback_order_hint') }}</span>
                                    <input type="number" name="backup_order[{{ $candidate->getKey() }}]" value="{{ $inChain ? $position + 1 : '' }}" min="1" max="999" class="input h-8 w-16 font-mono text-xs" dir="ltr" placeholder="—">
                                </span>
                                <span class="badge {{ $candidate->is_active ? 'badge-green' : 'badge-gray' }}">{{ $candidate->is_active ? __('ui.common.active') : __('ui.common.inactive') }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary h-10 px-8">
                        <x-icon name="check-circle" class="size-4" />
                        {{ __('ui.common.save') }}
                    </button>
                </div>
            </form>
        </div>
    @endif
@endsection
