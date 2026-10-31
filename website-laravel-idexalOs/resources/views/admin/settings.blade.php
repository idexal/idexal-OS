@extends('layouts.admin')

@section('title', __('ui.admin.settings.title'))
@section('page', __('ui.admin.nav.settings'))

@section('content')
    <div>
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.settings.title') }}</h1>
        <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.settings.subtitle') }}</p>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
        @csrf
        @method('PATCH')

        <div class="space-y-4">
            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.settings.general') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="site_name">{{ __('ui.admin.settings.site_name') }}</label>
                        <input id="site_name" type="text" name="site_name" value="{{ old('site_name', $values['site_name'] ?: config('idexal.brand.name')) }}" required maxlength="120" class="input h-10 @error('site_name') input-error @enderror">
                        @error('site_name')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="support_email">{{ __('ui.admin.settings.support_email') }}</label>
                        <input id="support_email" type="email" name="support_email" value="{{ old('support_email', $values['support_email'] ?: config('idexal.brand.support_email')) }}" required maxlength="190" class="input h-10 @error('support_email') input-error @enderror" dir="ltr">
                        @error('support_email')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="site_tagline">{{ __('ui.admin.settings.site_tagline') }}</label>
                        <input id="site_tagline" type="text" name="site_tagline" value="{{ old('site_tagline', $values['site_tagline']) }}" maxlength="190" class="input h-10 @error('site_tagline') input-error @enderror">
                        @error('site_tagline')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.settings.platform') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="label" for="default_currency">{{ __('ui.settings.currency') }}</label>
                        <input id="default_currency" type="text" name="default_currency" value="{{ old('default_currency', $values['default_currency']) }}" required maxlength="3" class="input h-10 font-mono uppercase @error('default_currency') input-error @enderror" dir="ltr">
                        @error('default_currency')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="tax_rate">{{ __('ui.admin.settings.tax_rate') }}</label>
                        <input id="tax_rate" type="number" name="tax_rate" value="{{ old('tax_rate', $values['tax_rate']) }}" required min="0" max="100" step="0.01" class="input h-10 font-mono @error('tax_rate') input-error @enderror" dir="ltr">
                        @error('tax_rate')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="invoice_due_days">{{ __('ui.admin.settings.invoice_due_days') }}</label>
                        <input id="invoice_due_days" type="number" name="invoice_due_days" value="{{ old('invoice_due_days', $values['invoice_due_days']) }}" required min="1" max="365" class="input h-10 font-mono @error('invoice_due_days') input-error @enderror" dir="ltr">
                        @error('invoice_due_days')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        ['registration_enabled', __('ui.admin.settings.registration_enabled'), __('ui.admin.settings.registration_hint')],
                        ['require_email_verification', __('ui.admin.settings.require_email_verification'), __('ui.admin.settings.verification_hint')],
                        ['gateway_enabled', __('ui.admin.settings.gateway_enabled'), __('ui.admin.settings.gateway_hint')],
                        ['proxy_enabled', __('ui.admin.settings.proxy_enabled'), __('ui.admin.settings.proxy_hint')],
                        ['browser_prewarm_enabled', __('ui.admin.settings.browser_prewarm_enabled'), __('ui.admin.settings.browser_prewarm_hint')],
                        ['billing_enabled', __('ui.admin.settings.billing_enabled'), __('ui.admin.settings.billing_hint')],
                    ] as [$field, $label, $hint])
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-ink-950/10 p-3 transition hover:border-ink-950/25">
                            <input type="checkbox" name="{{ $field }}" value="1" class="checkbox mt-0.5" @checked(old($field, $values[$field]))>
                            <span>
                                <span class="block text-sm font-medium">{{ $label }}</span>
                                <span class="mt-0.5 block text-[10px] leading-4 text-ink-400">{{ $hint }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-4">
                    <label class="label" for="payment_instructions">{{ __('ui.admin.settings.payment_instructions') }}</label>
                    <textarea id="payment_instructions" name="payment_instructions" rows="5" maxlength="5000" class="input resize-y text-xs leading-6 @error('payment_instructions') input-error @enderror" placeholder="{{ __('ui.billing.manual_note') }}">{{ old('payment_instructions', $values['payment_instructions']) }}</textarea>
                    @error('payment_instructions')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1.5 text-[11px] text-ink-400">{{ __('ui.admin.settings.payment_instructions_hint') }}</p>
                </div>
            </div>

            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.settings.desktop') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="desktop_latest_version">{{ __('ui.admin.settings.latest_version') }}</label>
                        <input id="desktop_latest_version" type="text" name="desktop_latest_version" value="{{ old('desktop_latest_version', $values['desktop_latest_version']) }}" required maxlength="40" class="input h-10 font-mono @error('desktop_latest_version') input-error @enderror" dir="ltr" placeholder="0.18.0">
                        @error('desktop_latest_version')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="desktop_min_version">{{ __('ui.admin.settings.min_version') }}</label>
                        <input id="desktop_min_version" type="text" name="desktop_min_version" value="{{ old('desktop_min_version', $values['desktop_min_version']) }}" required maxlength="40" class="input h-10 font-mono @error('desktop_min_version') input-error @enderror" dir="ltr" placeholder="0.17.0">
                        @error('desktop_min_version')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <p class="mt-2 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.settings.desktop_hint') }}</p>

                <div class="mt-4">
                    <label class="label" for="maintenance_message">{{ __('ui.admin.settings.maintenance_message') }}</label>
                    <textarea id="maintenance_message" name="maintenance_message" rows="3" maxlength="2000" class="input resize-y text-xs leading-6">{{ old('maintenance_message', $values['maintenance_message']) }}</textarea>
                    <p class="mt-1.5 text-[11px] text-ink-400">{{ __('ui.admin.settings.maintenance_hint') }}</p>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card card-pad">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-ink-950 text-lime"><x-icon name="card" class="size-5" /></span>
                    <div>
                        <h2 class="font-semibold">{{ __('ui.admin.payment_gateways.title') }}</h2>
                        <p class="text-[10px] text-ink-400">{{ __('ui.admin.payment_gateways.subtitle') }}</p>
                    </div>
                </div>
                <p class="mt-3 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.payment_gateways.settings_link_hint') }}</p>
                <a href="{{ route('admin.payment-gateways') }}" class="btn btn-outline btn-sm mt-4 h-10 w-full">
                    <x-icon name="card" class="size-4" />
                    {{ __('ui.admin.payment_gateways.manage') }}
                </a>
            </div>

            <div class="rounded-3xl border border-ink-950/10 bg-white p-5">
                <div class="flex items-start gap-3">
                    <x-icon name="shield" class="mt-0.5 size-5 text-lime-dark" />
                    <div>
                        <p class="text-xs font-semibold">{{ __('ui.admin.settings.security_title') }}</p>
                        <p class="mt-1 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.settings.security_body') }}</p>
                    </div>
                </div>
            </div>

            <div class="card p-5">
                <h2 class="font-semibold">{{ __('ui.admin.settings.system') }}</h2>
                <div class="mt-3 space-y-2 text-[11px]">
                    <div class="flex justify-between gap-3">
                        <span class="text-ink-400">Laravel</span>
                        <span class="font-mono">{{ app()->version() }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-ink-400">PHP</span>
                        <span class="font-mono">{{ PHP_VERSION }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.admin.settings.environment') }}</span>
                        <span class="badge badge-gray">{{ app()->environment() }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.admin.settings.timezone') }}</span>
                        <span class="font-mono">{{ config('app.timezone') }}</span>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary h-11 w-full">{{ __('ui.common.save') }}</button>
        </div>
    </form>
@endsection
