@extends('layouts.admin')

@section('title', $provider->exists ? __('ui.admin.proxy_providers.edit') : __('ui.admin.proxy_providers.create'))
@section('page', __('ui.admin.nav.proxy_providers'))

@php
    $isEdit = $provider->exists;
    $action = $isEdit ? route('admin.proxy-providers.update', $provider) : route('admin.proxy-providers.store');
    $countries = old('countries', implode(', ', $provider->countries ?? []));
    $chosenPlans = old('plans', $selectedPlans);
@endphp

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a href="{{ route('admin.proxy-providers.index') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-400 transition hover:text-ink-950">
                <x-icon name="arrow-right" class="size-3.5 rtl:rotate-180" />
                {{ __('ui.admin.proxy_providers.title') }}
            </a>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ $isEdit ? __('ui.admin.proxy_providers.edit') : __('ui.admin.proxy_providers.create') }}</h1>
            <p class="mt-1.5 text-sm text-ink-500">{{ __('ui.admin.proxy_providers.subtitle') }}</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $action }}" class="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
        @csrf
        @if ($isEdit)
            @method('PATCH')
        @endif

        <div class="space-y-4">
            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.proxy_providers.details') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="name">{{ __('ui.common.name') }}</label>
                        <input id="name" type="text" name="name" value="{{ old('name', $provider->name) }}" required maxlength="120" class="input h-10 @error('name') input-error @enderror">
                        @error('name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="slug">{{ __('ui.admin.proxy_providers.slug') }}</label>
                        <input id="slug" type="text" name="slug" value="{{ old('slug', $provider->slug) }}" required maxlength="80" class="input h-10 font-mono @error('slug') input-error @enderror" dir="ltr">
                        @error('slug')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="driver">{{ __('ui.admin.proxy_providers.driver') }}</label>
                        <select id="driver" name="driver" class="input h-10">
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver }}" @selected(old('driver', $provider->driver) === $driver)>{{ $driver }}</option>
                            @endforeach
                        </select>
                        @error('driver')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="protocol">{{ __('ui.admin.proxy_providers.protocol') }}</label>
                        <select id="protocol" name="protocol" class="input h-10">
                            @foreach ($protocols as $protocol)
                                <option value="{{ $protocol }}" @selected(old('protocol', $provider->protocol) === $protocol)>{{ strtoupper($protocol) }}</option>
                            @endforeach
                        </select>
                        @error('protocol')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="description">{{ __('ui.common.description') }}</label>
                        <textarea id="description" name="description" rows="3" maxlength="2000" class="input resize-y">{{ old('description', $provider->description) }}</textarea>
                        @error('description')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="website_url">{{ __('ui.admin.proxy_providers.website_url') }}</label>
                        <input id="website_url" type="url" name="website_url" value="{{ old('website_url', $provider->website_url) }}" maxlength="2048" class="input h-10 font-mono text-xs" dir="ltr" placeholder="https://">
                        @error('website_url')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="docs_url">{{ __('ui.admin.proxy_providers.docs_url') }}</label>
                        <input id="docs_url" type="url" name="docs_url" value="{{ old('docs_url', $provider->docs_url) }}" maxlength="2048" class="input h-10 font-mono text-xs" dir="ltr" placeholder="https://">
                        @error('docs_url')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.proxy_providers.coverage') }}</h2>
                <p class="mt-1 text-xs text-ink-400">{{ __('ui.admin.proxy_providers.coverage_hint') }}</p>
                <div class="mt-4">
                    <label class="label" for="countries">{{ __('ui.admin.proxy_providers.countries') }}</label>
                    <input id="countries" type="text" name="countries" value="{{ $countries }}" maxlength="1000" class="input h-10 font-mono text-xs" dir="ltr" placeholder="us, gb, de">
                    @error('countries')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.proxy_providers.availability') }}</h2>
                <div class="mt-4 space-y-2.5">
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ink-950/10 p-3 text-sm">
                        <input type="checkbox" name="is_active" value="1" class="checkbox" @checked(old('is_active', $provider->is_active ?? true))>
                        {{ __('ui.common.active') }}
                    </label>
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ink-950/10 p-3 text-sm">
                        <input type="checkbox" name="is_public" value="1" class="checkbox" @checked(old('is_public', $provider->is_public ?? true))>
                        {{ __('ui.common.public') }}
                    </label>
                </div>
                <div class="mt-4">
                    <label class="label" for="priority">{{ __('ui.admin.providers.priority') }}</label>
                    <input id="priority" type="number" name="priority" min="0" max="10000" value="{{ old('priority', $provider->priority ?? 0) }}" class="input h-10 font-mono" dir="ltr">
                </div>
            </div>

            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.proxy_providers.plans') }}</h2>
                <p class="mt-1 text-xs leading-5 text-ink-400">{{ __('ui.admin.proxy_providers.plans_hint') }}</p>
                <div class="mt-4 space-y-2">
                    @forelse ($plans as $plan)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ink-950/10 p-3 text-sm">
                            <input type="checkbox" name="plans[]" value="{{ $plan->id }}" class="checkbox" @checked(in_array($plan->id, $chosenPlans, true))>
                            <span>{{ $plan->name }}</span>
                            <code class="ms-auto font-mono text-[10px] text-ink-300" dir="ltr">{{ $plan->slug }}</code>
                        </label>
                    @empty
                        <p class="text-xs text-ink-400">{{ __('ui.admin.plans.empty') }}</p>
                    @endforelse
                </div>
                @error('plans')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex gap-2">
                <a href="{{ route('admin.proxy-providers.index') }}" class="btn btn-outline h-11 flex-1">{{ __('ui.common.cancel') }}</a>
                <button type="submit" class="btn btn-primary h-11 flex-1">{{ __('ui.common.save') }}</button>
            </div>

            @if ($isEdit)
                <div class="card card-pad border-red-200">
                    <h2 class="font-semibold text-red-700">{{ __('ui.admin.proxy_providers.danger') }}</h2>
                    <p class="mt-1 text-xs leading-5 text-ink-400">{{ __('ui.admin.proxy_providers.delete_hint') }}</p>
                    <button type="submit" form="delete-proxy-provider" class="btn btn-outline mt-3 h-10 w-full border-red-200 text-red-700">{{ __('ui.common.delete') }}</button>
                </div>
            @endif
        </div>
    </form>

    @if ($isEdit)
        <form id="delete-proxy-provider" method="POST" action="{{ route('admin.proxy-providers.destroy', $provider) }}" onsubmit="return confirm('{{ __('ui.admin.proxy_providers.delete_confirm') }}')">
            @csrf
            @method('DELETE')
        </form>
    @endif
@endsection
