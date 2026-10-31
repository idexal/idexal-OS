@extends('layouts.admin')

@section('title', __('ui.admin.social_login.title'))
@section('page', __('ui.admin.nav.social_login'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.social_login.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.social_login.subtitle') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.payment-gateways') }}" class="btn btn-outline btn-sm h-10 px-4">
                <x-icon name="card" class="size-4" />
                {{ __('ui.admin.nav.payment_gateways') }}
            </a>
            <a href="{{ route('admin.settings') }}" class="btn btn-outline btn-sm h-10 px-4">
                <x-icon name="settings" class="size-4" />
                {{ __('ui.admin.nav.settings') }}
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">
            <x-icon name="shield" class="mt-0.5 size-4" />
            <div>
                <p class="text-sm font-medium">{{ __('ui.admin.social_login.fix_errors') }}</p>
                <ul class="mt-1 list-inside list-disc text-xs">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
        <form method="POST" action="{{ route('admin.social-login.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            @foreach ($providers as $key => $provider)
                <div class="card card-pad">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="grid size-10 place-items-center rounded-xl bg-ink-950 text-lime">
                                <x-social-icon :provider="$key" />
                            </span>
                            <div>
                                <h2 class="font-semibold">{{ $provider['label'] }}</h2>
                                <p class="text-[10px] text-ink-400">{{ __('ui.admin.social_login.driver_hint', ['driver' => $provider['driver']]) }}</p>
                            </div>
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 text-xs font-medium">
                            <input type="checkbox" name="social_{{ $key }}_enabled" value="1" class="checkbox" @checked(old('social_'.$key.'_enabled', $provider['enabled']))>
                            {{ __('ui.admin.social_login.enabled') }}
                        </label>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="social_{{ $key }}_client_id">{{ __('ui.admin.social_login.client_id') }}</label>
                            <input id="social_{{ $key }}_client_id" type="text" name="social_{{ $key }}_client_id" value="{{ old('social_'.$key.'_client_id', $provider['client_id']) }}" maxlength="255" class="input h-10 font-mono text-xs @error('social_'.$key.'_client_id') input-error @enderror" dir="ltr">
                            @error('social_'.$key.'_client_id')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="label" for="social_{{ $key }}_secret">{{ __('ui.admin.social_login.client_secret') }}</label>
                            <input id="social_{{ $key }}_secret" type="password" name="social_{{ $key }}_secret" value="" maxlength="255" class="input h-10 font-mono text-xs" dir="ltr" autocomplete="new-password" placeholder="{{ $provider['has_secret'] ? '••••••••••••••••' : '••••••••' }}">
                            @if ($provider['has_secret'])
                                <label class="mt-2 flex items-center gap-2 text-[11px] text-red-600">
                                    <input type="checkbox" name="clear_social_{{ $key }}_secret" value="1" class="checkbox">
                                    {{ __('ui.admin.settings.clear_secret') }}
                                </label>
                            @endif
                        </div>
                        <div class="sm:col-span-2">
                            <div class="flex items-center gap-2 rounded-lg bg-paper px-3 py-2">
                                <span class="text-[10px] uppercase tracking-wide text-ink-400">{{ __('ui.admin.social_login.redirect_url') }}</span>
                                <code class="truncate font-mono text-[10px] text-ink-600" dir="ltr">{{ $provider['redirect_url'] }}</code>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            <button type="submit" class="btn btn-primary h-11 w-full sm:w-auto sm:px-8">{{ __('ui.common.save') }}</button>
        </form>

        <div class="space-y-4">
            <div class="card p-5">
                <h2 class="font-semibold">{{ __('ui.admin.social_login.status_title') }}</h2>
                <p class="mt-1 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.social_login.status_hint') }}</p>
                <div class="mt-3 space-y-2">
                    @foreach ($providers as $key => $provider)
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-ink-950/10 px-3 py-2">
                            <span class="flex items-center gap-2 text-xs font-medium">
                                <x-social-icon :provider="$key" class="size-4" />
                                {{ $provider['label'] }}
                            </span>
                            @if (in_array($key, $liveProviders, true))
                                <span class="badge badge-green">{{ __('ui.admin.social_login.live') }}</span>
                            @else
                                <span class="badge badge-gray">{{ __('ui.admin.social_login.off') }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-3xl border border-ink-950/10 bg-white p-5">
                <div class="flex items-start gap-3">
                    <x-icon name="shield" class="mt-0.5 size-5 text-lime-dark" />
                    <div>
                        <p class="text-xs font-semibold">{{ __('ui.admin.social_login.note_title') }}</p>
                        <p class="mt-1 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.social_login.note_body') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
