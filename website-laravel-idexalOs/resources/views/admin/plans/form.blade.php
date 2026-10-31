@extends('layouts.admin')

@section('title', $plan->exists ? __('ui.admin.plans.edit') : __('ui.admin.plans.create'))
@section('page', __('ui.admin.nav.plans'))

@php
    $isEdit = $plan->exists;
    $action = $isEdit ? route('admin.plans.update', $plan) : route('admin.plans.store');
    $limitRequests = old('limits.requests_per_month', $plan->limits[\App\Models\Plan::LIMIT_REQUESTS] ?? null);
    $limitTokens = old('limits.tokens_per_month', $plan->limits[\App\Models\Plan::LIMIT_TOKENS] ?? null);
    $limitSeats = old('limits.seats', $plan->limits[\App\Models\Plan::LIMIT_SEATS] ?? null);
    $limitKeys = old('limits.api_keys', $plan->limits[\App\Models\Plan::LIMIT_API_KEYS] ?? null);
    $limitProxyProfiles = old('limits.proxy_profiles', $plan->limits[\App\Models\Plan::LIMIT_PROXY_PROFILES] ?? null);
    $limitProxyBandwidth = old('limits.proxy_bandwidth_gb', $plan->limits[\App\Models\Plan::LIMIT_PROXY_BANDWIDTH_GB] ?? null);
    $selectedProviders = old('ai_provider_ids', $isEdit ? $plan->aiProviders()->pluck('ai_providers.id')->all() : []);
    $selectedModels = old('ai_model_ids', $isEdit ? $plan->aiModels()->pluck('ai_models.id')->all() : []);
@endphp

@section('content')
    <div>
        <a href="{{ route('admin.plans.index') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-400 transition hover:text-ink-950">
            <x-icon name="arrow-right" class="size-3.5 rtl:rotate-180" />
            {{ __('ui.admin.plans.title') }}
        </a>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ $isEdit ? __('ui.admin.plans.edit') : __('ui.admin.plans.create') }}</h1>
        <p class="mt-1.5 text-sm text-ink-500">{{ __('ui.admin.plans.subtitle') }}</p>
    </div>

    <form method="POST" action="{{ $action }}" class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
        @csrf
        @if ($isEdit)
            @method('PATCH')
        @endif

        <div class="space-y-4">
            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.plans.details') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="name">{{ __('ui.common.name') }}</label>
                        <input id="name" type="text" name="name" value="{{ old('name', $plan->name) }}" required maxlength="120" class="input h-10 @error('name') input-error @enderror">
                        @error('name')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="slug">{{ __('ui.admin.plans.slug') }}</label>
                        <input id="slug" type="text" name="slug" value="{{ old('slug', $plan->slug) }}" required maxlength="80" class="input h-10 font-mono @error('slug') input-error @enderror" dir="ltr">
                        @error('slug')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="description">{{ __('ui.common.description') }}</label>
                        <textarea id="description" name="description" rows="3" maxlength="500" class="input resize-y @error('description') input-error @enderror">{{ old('description', $plan->description) }}</textarea>
                        @error('description')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.plans.pricing') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="label" for="price_monthly">{{ __('ui.admin.plans.price_monthly') }}</label>
                        <input id="price_monthly" type="number" name="price_monthly" min="0" max="100000" step="0.01" value="{{ old('price_monthly', $plan->price_monthly ?? 0) }}" required class="input h-10 font-mono @error('price_monthly') input-error @enderror" dir="ltr">
                        @error('price_monthly')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="price_yearly">{{ __('ui.admin.plans.price_yearly') }}</label>
                        <input id="price_yearly" type="number" name="price_yearly" min="0" max="1000000" step="0.01" value="{{ old('price_yearly', $plan->price_yearly ?? 0) }}" required class="input h-10 font-mono @error('price_yearly') input-error @enderror" dir="ltr">
                        @error('price_yearly')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="currency">{{ __('ui.settings.currency') }}</label>
                        <input id="currency" type="text" name="currency" value="{{ old('currency', $plan->currency ?? 'USD') }}" required maxlength="3" class="input h-10 font-mono uppercase @error('currency') input-error @enderror" dir="ltr">
                        @error('currency')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.pricing.limits') }}</h2>
                <p class="mt-1 text-xs text-ink-400">{{ __('ui.admin.plans.limits_hint') }}</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        ['requests_per_month', __('ui.pricing.limit_requests'), $limitRequests],
                        ['tokens_per_month', __('ui.pricing.limit_tokens'), $limitTokens],
                        ['seats', __('ui.pricing.limit_seats'), $limitSeats],
                        ['api_keys', __('ui.pricing.limit_keys'), $limitKeys],
                        ['proxy_profiles', __('ui.pricing.limit_proxy_profiles'), $limitProxyProfiles],
                        ['proxy_bandwidth_gb', __('ui.pricing.limit_proxy_bandwidth'), $limitProxyBandwidth],
                        ['monthly_cost_usd', __('ui.pricing.limit_monthly_cost'), old('limits.monthly_cost_usd', $plan->limits[\App\Models\Plan::LIMIT_MONTHLY_COST_USD] ?? null)],
                    ] as [$field, $label, $value])
                        <div>
                            <label class="label" for="limit-{{ $field }}">{{ $label }}</label>
                            <div class="relative">
                                <input id="limit-{{ $field }}" type="number" name="limits[{{ $field }}]" min="0" value="{{ $value }}" class="input h-10 pe-14 font-mono" dir="ltr" placeholder="{{ __('ui.common.unlimited') }}">
                                <span class="absolute end-3 top-1/2 -translate-y-1/2 text-[10px] text-ink-300">{{ __('ui.common.unlimited') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.pricing.features') }}</h2>
                <div class="mt-4 space-y-2.5">
                    @foreach ($features as $feature)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ink-950/10 p-3 text-sm transition hover:border-ink-950/25">
                            <input type="checkbox" name="features[{{ $feature }}]" value="1" class="checkbox" @checked(old('features.'.$feature, $plan->features[$feature] ?? false))>
                            <span>{{ __('ui.pricing.feature_'.$feature) }}</span>
                            <code class="ms-auto font-mono text-[10px] text-ink-300" dir="ltr">{{ $feature }}</code>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="card card-pad">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="font-semibold">{{ __('ui.admin.plans.ai_providers') }}</h2>
                    <a href="{{ route('admin.providers.index') }}" class="shrink-0 text-[11px] font-medium text-ink-400 transition hover:text-ink-950">{{ __('ui.admin.plans.ai_providers_manage') }}</a>
                </div>
                <p class="mt-1 text-xs leading-5 text-ink-400">{{ __('ui.admin.plans.ai_providers_hint') }}</p>
                <p class="mt-1 text-[10px] leading-4 text-ink-400">{{ __('ui.admin.plans.ai_combos_note') }}</p>
                <input type="hidden" name="ai_providers_present" value="1">
                <input type="hidden" name="ai_models_present" value="1">
                <div class="mt-4 space-y-2.5">
                    @forelse ($aiProviders as $provider)
                        <div class="rounded-xl border border-ink-950/10 p-3 transition hover:border-ink-950/25">
                            <label class="flex cursor-pointer items-start gap-3">
                                <input type="checkbox" name="ai_provider_ids[]" value="{{ $provider->getKey() }}" class="checkbox mt-0.5" @checked(in_array($provider->getKey(), $selectedProviders, true))>
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2 text-sm font-medium">
                                        {{ $provider->name }}
                                        @unless ($provider->is_active)
                                            <span class="badge badge-gray">{{ __('ui.common.inactive') }}</span>
                                        @endunless
                                    </span>
                                    <span class="mt-0.5 block font-mono text-[10px] text-ink-300" dir="ltr">{{ $provider->slug }}</span>
                                </span>
                                <span class="shrink-0 pt-0.5 text-[10px] text-ink-400">{{ __('ui.admin.plans.ai_provider_models', ['count' => $provider->models->where('is_active', true)->count()]) }}</span>
                            </label>
                            @if ($provider->models->isNotEmpty())
                                <div class="mt-2 space-y-1.5 ps-7">
                                    @foreach ($provider->models as $model)
                                        <label class="flex cursor-pointer items-center gap-2 text-[11px] text-ink-500">
                                            <input type="checkbox" name="ai_model_ids[]" value="{{ $model->getKey() }}" class="checkbox" @checked(in_array($model->getKey(), $selectedModels, true))>
                                            <span class="font-medium">{{ $model->display_name }}</span>
                                            <span class="font-mono text-[10px] text-ink-300" dir="ltr">{{ $model->model_id }}</span>
                                            @unless ($model->is_active)
                                                <span class="text-[10px] text-ink-300">{{ __('ui.common.inactive') }}</span>
                                            @endunless
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="rounded-xl border border-dashed border-ink-950/15 p-4 text-center text-xs text-ink-400">{{ __('ui.admin.plans.ai_providers_empty') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.plans.visibility') }}</h2>
                <div class="mt-4 space-y-2.5">
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ink-950/10 p-3 text-sm">
                        <input type="checkbox" name="is_active" value="1" class="checkbox" @checked(old('is_active', $plan->is_active ?? true))>
                        {{ __('ui.common.active') }}
                    </label>
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ink-950/10 p-3 text-sm">
                        <input type="checkbox" name="is_public" value="1" class="checkbox" @checked(old('is_public', $plan->is_public ?? true))>
                        {{ __('ui.common.public') }}
                    </label>
                </div>
                <div class="mt-4">
                    <label class="label" for="sort_order">{{ __('ui.admin.plans.sort_order') }}</label>
                    <input id="sort_order" type="number" name="sort_order" min="0" max="10000" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" class="input h-10 font-mono" dir="ltr">
                </div>
            </div>

            <div class="card card-pad">
                <h2 class="font-semibold">Stripe</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="label" for="stripe_price_monthly_id">{{ __('ui.admin.plans.stripe_monthly') }}</label>
                        <input id="stripe_price_monthly_id" type="text" name="stripe_price_monthly_id" value="{{ old('stripe_price_monthly_id', $plan->stripe_price_monthly_id) }}" maxlength="190" class="input h-10 font-mono text-xs" dir="ltr" placeholder="price_...">
                    </div>
                    <div>
                        <label class="label" for="stripe_price_yearly_id">{{ __('ui.admin.plans.stripe_yearly') }}</label>
                        <input id="stripe_price_yearly_id" type="text" name="stripe_price_yearly_id" value="{{ old('stripe_price_yearly_id', $plan->stripe_price_yearly_id) }}" maxlength="190" class="input h-10 font-mono text-xs" dir="ltr" placeholder="price_...">
                    </div>
                </div>
            </div>

            <div class="flex gap-2">
                <a href="{{ route('admin.plans.index') }}" class="btn btn-outline h-11 flex-1">{{ __('ui.common.cancel') }}</a>
                <button type="submit" class="btn btn-primary h-11 flex-1">{{ __('ui.common.save') }}</button>
            </div>
        </div>
    </form>
@endsection
