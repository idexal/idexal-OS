@extends('layouts.admin')

@section('title', __('ui.admin.features.title'))
@section('page', __('ui.admin.nav.features'))

@section('content')
    <div>
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.features.title') }}</h1>
        <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.features.subtitle') }}</p>
    </div>

    <form method="POST" action="{{ route('admin.features.update') }}" class="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
        @csrf
        @method('PATCH')

        <div class="grid gap-4 md:grid-cols-2">
            <section class="card card-pad md:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="font-semibold">{{ __('ui.admin.features.desktop.title') }}</h2>
                        <p class="mt-1 max-w-2xl text-[11px] leading-5 text-ink-400">{{ __('ui.admin.features.desktop.hint') }}</p>
                    </div>
                    <span class="badge badge-gray">{{ count($desktop) }}</span>
                </div>

                <div class="mt-3 grid gap-2 lg:grid-cols-2">
                    @foreach ($desktop as $switch)
                        <label
                            class="flex cursor-pointer items-start justify-between gap-3 rounded-xl border border-ink-950/10 p-3 transition hover:border-ink-950/25"
                            x-data="{ on: {{ $switch['on'] ? 'true' : 'false' }} }"
                        >
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-semibold">{{ $switch['name'] }}</span>
                                    <span
                                        class="badge"
                                        :class="on ? 'badge-green' : 'badge-gray'"
                                        x-text="on ? @js(__('ui.admin.features.status_enabled')) : @js(__('ui.admin.features.status_disabled'))"
                                    >{{ $switch['on'] ? __('ui.admin.features.status_enabled') : __('ui.admin.features.status_disabled') }}</span>
                                </div>
                                <p class="mt-1 text-[10px] leading-4 text-ink-500">{{ $switch['body'] }}</p>
                                <p class="mt-1 font-mono text-[9px] text-ink-300" dir="ltr">{{ $switch['key'] }}</p>
                            </div>
                            <input
                                type="checkbox"
                                name="{{ $switch['field'] }}"
                                value="1"
                                class="checkbox mt-1"
                                x-model="on"
                                @checked(old($switch['field'], $switch['on']))
                            >
                        </label>
                    @endforeach
                </div>
            </section>

            @foreach ($features as $feature)
                <label
                    class="card card-pad block cursor-pointer transition hover:border-ink-950/25"
                    x-data="{ on: {{ $feature['on'] ? 'true' : 'false' }} }"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold">{{ $feature['name'] }}</h2>
                            @if ($feature['beta'])
                                <span class="badge badge-amber">{{ __('ui.admin.features.beta') }}</span>
                            @endif
                        </div>
                        <input
                            type="checkbox"
                            name="{{ $feature['field'] }}"
                            value="1"
                            class="checkbox mt-1"
                            x-model="on"
                            @checked(old($feature['field'], $feature['on']))
                        >
                    </div>

                    <p class="mt-2 text-[11px] leading-5 text-ink-500">{{ $feature['body'] }}</p>

                    <div class="mt-3 flex flex-wrap items-center gap-2 text-[11px]">
                        <span class="text-ink-400">{{ __('ui.admin.features.status_label') }}:</span>
                        <span
                            class="badge"
                            :class="on ? 'badge-green' : 'badge-gray'"
                            x-text="on ? @js(__('ui.admin.features.status_enabled')) : @js(__('ui.admin.features.status_disabled'))"
                        >{{ $feature['on'] ? __('ui.admin.features.status_enabled') : __('ui.admin.features.status_disabled') }}</span>
                        @if ($feature['note'])
                            <span class="text-ink-400">· {{ $feature['note'] }}</span>
                        @endif
                    </div>

                    @php($pinned = ($overrides[$feature['key']]['on'] ?? 0) + ($overrides[$feature['key']]['off'] ?? 0))
                    @if ($pinned > 0)
                        <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[10px]">
                            <span class="badge badge-gray">{{ __('ui.admin.features.overrides_count', ['count' => $pinned]) }}</span>
                            <span class="text-ink-400">{{ __('ui.admin.features.overrides_detail', ['on' => $overrides[$feature['key']]['on'] ?? 0, 'off' => $overrides[$feature['key']]['off'] ?? 0]) }}</span>
                        </div>
                    @endif

                    @php($recent = $recentUsage[$feature['key']] ?? 0)
                    @php($previous = $previousUsage[$feature['key']] ?? 0)
                    @if ($feature['on'] || $recent > 0 || $previous > 0)
                        <p class="mt-2 text-[10px] text-ink-400">
                            {{ __('ui.admin.features.usage_this_week', ['count' => compact_number($recent)]) }}
                            <span class="text-ink-300">· {{ __('ui.admin.features.usage_last_week', ['count' => compact_number($previous)]) }}</span>
                        </p>
                    @endif
                </label>
            @endforeach
        </div>

        <div class="space-y-4">
            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.features.default_model_label') }}</h2>
                <p class="mt-1 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.features.default_model_hint') }}</p>
                <select name="default_model_id" class="select mt-3 h-10">
                    <option value="">{{ __('ui.admin.features.select_model') }}</option>
                    @foreach ($models as $model)
                        <option value="{{ $model['id'] }}" @selected(old('default_model_id', $values['default_model_id']) === $model['id'])>{{ $model['label'] }} — {{ $model['id'] }}</option>
                    @endforeach
                </select>
                @if ($values['default_model_id'] !== '' && ! $modelIds->contains($values['default_model_id']))
                    <p class="mt-1.5 flex items-start gap-1.5 text-[11px] leading-5 text-amber-600">
                        <x-icon name="alert" class="mt-0.5 size-3.5 shrink-0" />
                        <span>{{ __('ui.admin.features.model_missing', ['model' => $values['default_model_id']]) }}</span>
                    </p>
                @endif
                @error('default_model_id')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.features.vision_model_label') }}</h2>
                <p class="mt-1 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.features.vision_model_hint') }}</p>
                <select name="vision_model_id" class="select mt-3 h-10">
                    <option value="">{{ __('ui.admin.features.select_model') }}</option>
                    @foreach ($models as $model)
                        <option value="{{ $model['id'] }}" @selected(old('vision_model_id', $values['vision_model_id']) === $model['id'])>{{ $model['label'] }} — {{ $model['id'] }}</option>
                    @endforeach
                </select>
                @if ($values['vision_model_id'] !== '' && ! $modelIds->contains($values['vision_model_id']))
                    <p class="mt-1.5 flex items-start gap-1.5 text-[11px] leading-5 text-amber-600">
                        <x-icon name="alert" class="mt-0.5 size-3.5 shrink-0" />
                        <span>{{ __('ui.admin.features.model_missing', ['model' => $values['vision_model_id']]) }}</span>
                    </p>
                @endif
                @error('vision_model_id')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.features.anthropic_desktop_name') }}</h2>
                <p class="mt-1 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.features.anthropic_desktop_hint') }}</p>
                <label class="mt-3 flex cursor-pointer items-start gap-3 rounded-xl border border-ink-950/10 p-3 text-sm transition hover:border-ink-950/25">
                    <input type="checkbox" name="gateway_anthropic_desktop" value="1" class="checkbox mt-0.5" @checked(old('gateway_anthropic_desktop', $values['gateway_anthropic_desktop']))>
                    <span>
                        <span class="font-medium">{{ __('ui.admin.features.anthropic_desktop_label') }}</span>
                        <span class="mt-0.5 block text-[10px] leading-4 text-ink-400">{{ __('ui.admin.features.anthropic_desktop_body') }}</span>
                    </span>
                </label>
            </div>

            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.features.require_plan_name') }}</h2>
                <p class="mt-1 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.features.require_plan_hint') }}</p>
                <label class="mt-3 flex cursor-pointer items-start gap-3 rounded-xl border border-ink-950/10 p-3 text-sm transition hover:border-ink-950/25">
                    <input type="checkbox" name="require_plan_selection" value="1" class="checkbox mt-0.5" @checked(old('require_plan_selection', $values['require_plan_selection']))>
                    <span>
                        <span class="font-medium">{{ __('ui.admin.features.require_plan_label') }}</span>
                        <span class="mt-0.5 block text-[10px] leading-4 text-ink-400">{{ __('ui.admin.features.require_plan_body') }}</span>
                    </span>
                </label>
            </div>

            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.features.patient_retry_name') }}</h2>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="patient_retry_attempts">{{ __('ui.admin.features.retry_attempts') }}</label>
                        <input id="patient_retry_attempts" type="number" name="patient_retry_attempts" min="1" max="20" value="{{ old('patient_retry_attempts', $values['patient_retry_attempts']) }}" class="input h-10 font-mono" dir="ltr">
                        <p class="mt-1.5 text-[10px] leading-4 text-ink-400">{{ __('ui.admin.features.retry_attempts_hint') }}</p>
                        @error('patient_retry_attempts')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="patient_retry_budget">{{ __('ui.admin.features.retry_budget') }}</label>
                        <input id="patient_retry_budget" type="number" name="patient_retry_budget" min="5" max="600" value="{{ old('patient_retry_budget', $values['patient_retry_budget']) }}" class="input h-10 font-mono" dir="ltr">
                        <p class="mt-1.5 text-[10px] leading-4 text-ink-400">{{ __('ui.admin.features.retry_budget_hint') }}</p>
                        @error('patient_retry_budget')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.features.web_search_name') }}</h2>
                <div class="mt-3">
                    <label class="label" for="web_search_price">{{ __('ui.admin.features.price_label') }}</label>
                    <input id="web_search_price" type="number" name="web_search_price" min="0" max="100" step="0.001" value="{{ old('web_search_price', $values['web_search_price']) }}" class="input h-10 font-mono" dir="ltr">
                    <p class="mt-1.5 text-[10px] leading-4 text-ink-400">{{ __('ui.admin.features.price_hint') }}</p>
                    @error('web_search_price')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <button type="submit" class="btn btn-primary h-11 w-full">{{ __('ui.admin.features.save') }}</button>
        </div>
    </form>

    <section class="card overflow-hidden">
        <div class="border-b border-ink-950/5 px-4 py-3">
            <h2 class="font-semibold">{{ __('ui.admin.features.changes_title') }}</h2>
            <p class="mt-1 max-w-2xl text-[11px] leading-5 text-ink-400">{{ __('ui.admin.features.changes_hint') }}</p>
        </div>

        @if ($switches === [])
            <p class="px-4 py-6 text-xs text-ink-400">{{ __('ui.admin.features.changes_empty') }}</p>
        @else
            <table class="w-full text-start text-[11px]">
                <thead>
                    <tr class="border-b border-ink-950/5 text-[10px] uppercase tracking-wide text-ink-400">
                        <th class="p-3 text-start font-medium">{{ __('ui.admin.features.changes_when') }}</th>
                        <th class="p-3 text-start font-medium">{{ __('ui.admin.features.changes_who') }}</th>
                        <th class="p-3 text-start font-medium">{{ __('ui.admin.features.changes_feature') }}</th>
                        <th class="p-3 text-start font-medium">{{ __('ui.admin.features.changes_state') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($switches as $switch)
                        <tr class="border-b border-ink-950/5 last:border-0">
                            <td class="whitespace-nowrap p-3 font-mono text-[10px] text-ink-400">{{ $switch['at']?->translatedFormat('d M H:i') }}</td>
                            <td class="p-3 text-ink-600">{{ $switch['actor'] }}</td>
                            <td class="p-3 text-ink-600">{{ $switch['feature'] }}</td>
                            <td class="p-3">
                                <span class="badge {{ $switch['on'] ? 'badge-green' : 'badge-gray' }}">
                                    {{ $switch['on'] ? __('ui.admin.features.changes_on') : __('ui.admin.features.changes_off') }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
