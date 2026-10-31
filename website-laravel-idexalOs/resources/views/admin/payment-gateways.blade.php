@extends('layouts.admin')

@section('title', __('ui.admin.payment_gateways.title'))
@section('page', __('ui.admin.nav.payment_gateways'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.payment_gateways.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.payment_gateways.subtitle') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.payments.index') }}" class="btn btn-outline btn-sm h-10 px-4">
                <x-icon name="card" class="size-4" />
                {{ __('ui.admin.nav.payments') }}
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
                <p class="text-sm font-medium">{{ __('ui.admin.payment_gateways.fix_errors') }}</p>
                <ul class="mt-1 list-inside list-disc text-xs">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
        <form method="POST" action="{{ route('admin.payment-gateways.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div class="card card-pad">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-xl bg-ink-950 text-lime"><x-icon name="card" class="size-5" /></span>
                        <div>
                            <h2 class="font-semibold">Stripe</h2>
                            <p class="text-[10px] text-ink-400">{{ __('ui.admin.payment_gateways.stripe_hint') }}</p>
                        </div>
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 text-xs font-medium">
                        <input type="checkbox" name="stripe_enabled" value="1" class="checkbox" @checked(old('stripe_enabled', $values['stripe_enabled']))>
                        {{ __('ui.admin.payment_gateways.enabled') }}
                    </label>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="stripe_key">{{ __('ui.admin.settings.stripe_key') }}</label>
                        <input id="stripe_key" type="text" name="stripe_key" value="{{ old('stripe_key', $values['stripe_key']) }}" maxlength="255" class="input h-10 font-mono text-xs @error('stripe_key') input-error @enderror" dir="ltr" placeholder="pk_live_...">
                        @error('stripe_key')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="stripe_secret">{{ __('ui.admin.settings.stripe_secret') }}</label>
                        <input id="stripe_secret" type="password" name="stripe_secret" value="" maxlength="255" class="input h-10 font-mono text-xs" dir="ltr" autocomplete="new-password" placeholder="{{ $hasStripeSecret ? '••••••••••••••••' : 'sk_live_...' }}">
                        @if ($hasStripeSecret)
                            <label class="mt-2 flex items-center gap-2 text-[11px] text-red-600">
                                <input type="checkbox" name="clear_stripe_secret" value="1" class="checkbox">
                                {{ __('ui.admin.settings.clear_secret') }}
                            </label>
                        @endif
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="stripe_webhook_secret">{{ __('ui.admin.settings.stripe_webhook_secret') }}</label>
                        <input id="stripe_webhook_secret" type="password" name="stripe_webhook_secret" value="" maxlength="255" class="input h-10 font-mono text-xs" dir="ltr" autocomplete="new-password" placeholder="{{ $hasStripeWebhookSecret ? '••••••••••••••••' : 'whsec_...' }}">
                        @if ($hasStripeWebhookSecret)
                            <label class="mt-2 flex items-center gap-2 text-[11px] text-red-600">
                                <input type="checkbox" name="clear_stripe_webhook_secret" value="1" class="checkbox">
                                {{ __('ui.admin.settings.clear_secret') }}
                            </label>
                        @endif
                        <div class="mt-2 flex items-center gap-2 rounded-lg bg-paper px-3 py-2">
                            <span class="text-[10px] uppercase tracking-wide text-ink-400">{{ __('ui.admin.payment_gateways.webhook_url') }}</span>
                            <code class="truncate font-mono text-[10px] text-ink-600" dir="ltr">{{ $stripeWebhookUrl }}</code>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-pad">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-xl bg-[#003087] text-white"><x-icon name="external" class="size-5" /></span>
                        <div>
                            <h2 class="font-semibold">PayPal</h2>
                            <p class="text-[10px] text-ink-400">{{ __('ui.admin.payment_gateways.paypal_hint') }}</p>
                        </div>
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 text-xs font-medium">
                        <input type="checkbox" name="paypal_enabled" value="1" class="checkbox" @checked(old('paypal_enabled', $values['paypal_enabled']))>
                        {{ __('ui.admin.payment_gateways.enabled') }}
                    </label>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="paypal_mode">{{ __('ui.admin.payment_gateways.mode') }}</label>
                        <select id="paypal_mode" name="paypal_mode" class="select h-10">
                            <option value="sandbox" @selected(old('paypal_mode', $values['paypal_mode']) === 'sandbox')>{{ __('ui.admin.payment_gateways.mode_sandbox') }}</option>
                            <option value="live" @selected(old('paypal_mode', $values['paypal_mode']) === 'live')>{{ __('ui.admin.payment_gateways.mode_live') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="paypal_client_id">{{ __('ui.admin.payment_gateways.client_id') }}</label>
                        <input id="paypal_client_id" type="text" name="paypal_client_id" value="{{ old('paypal_client_id', $values['paypal_client_id']) }}" maxlength="255" class="input h-10 font-mono text-xs @error('paypal_client_id') input-error @enderror" dir="ltr">
                        @error('paypal_client_id')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="paypal_secret">{{ __('ui.admin.payment_gateways.secret') }}</label>
                        <input id="paypal_secret" type="password" name="paypal_secret" value="" maxlength="255" class="input h-10 font-mono text-xs" dir="ltr" autocomplete="new-password" placeholder="{{ $hasPayPalSecret ? '••••••••••••••••' : '••••••••' }}">
                        @if ($hasPayPalSecret)
                            <label class="mt-2 flex items-center gap-2 text-[11px] text-red-600">
                                <input type="checkbox" name="clear_paypal_secret" value="1" class="checkbox">
                                {{ __('ui.admin.settings.clear_secret') }}
                            </label>
                        @endif
                    </div>
                    <div>
                        <label class="label" for="paypal_webhook_id">{{ __('ui.admin.payment_gateways.webhook_id') }}</label>
                        <input id="paypal_webhook_id" type="text" name="paypal_webhook_id" value="{{ old('paypal_webhook_id', $values['paypal_webhook_id']) }}" maxlength="255" class="input h-10 font-mono text-xs" dir="ltr">
                    </div>
                    <div class="sm:col-span-2">
                        <div class="flex items-center gap-2 rounded-lg bg-paper px-3 py-2">
                            <span class="text-[10px] uppercase tracking-wide text-ink-400">{{ __('ui.admin.payment_gateways.webhook_url') }}</span>
                            <code class="truncate font-mono text-[10px] text-ink-600" dir="ltr">{{ $paypalWebhookUrl }}</code>
                        </div>
                        <p class="mt-1.5 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.payment_gateways.paypal_webhook_hint') }}</p>
                    </div>
                </div>
            </div>

            <div class="card card-pad">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-xl bg-ink-950 text-lime"><x-icon name="building" class="size-5" /></span>
                        <div>
                            <h2 class="font-semibold">{{ __('ui.billing.bank_transfer') }}</h2>
                            <p class="text-[10px] text-ink-400">{{ __('ui.admin.payment_gateways.bank_hint') }}</p>
                        </div>
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 text-xs font-medium">
                        <input type="checkbox" name="bank_transfer_enabled" value="1" class="checkbox" @checked(old('bank_transfer_enabled', $values['bank_transfer_enabled']))>
                        {{ __('ui.admin.payment_gateways.enabled') }}
                    </label>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="bank_transfer_bank_name">{{ __('ui.billing.bank_name') }}</label>
                        <input id="bank_transfer_bank_name" type="text" name="bank_transfer_bank_name" value="{{ old('bank_transfer_bank_name', $values['bank_transfer_bank_name']) }}" maxlength="190" class="input h-10 text-sm">
                    </div>
                    <div>
                        <label class="label" for="bank_transfer_account_name">{{ __('ui.billing.holder') }}</label>
                        <input id="bank_transfer_account_name" type="text" name="bank_transfer_account_name" value="{{ old('bank_transfer_account_name', $values['bank_transfer_account_name']) }}" maxlength="190" class="input h-10 text-sm">
                    </div>
                    <div>
                        <label class="label" for="bank_transfer_account_number">{{ __('ui.admin.payment_gateways.account_number') }}</label>
                        <input id="bank_transfer_account_number" type="text" name="bank_transfer_account_number" value="{{ old('bank_transfer_account_number', $values['bank_transfer_account_number']) }}" maxlength="64" class="input h-10 font-mono text-xs" dir="ltr">
                    </div>
                    <div>
                        <label class="label" for="bank_transfer_iban">{{ __('ui.billing.iban') }}</label>
                        <input id="bank_transfer_iban" type="text" name="bank_transfer_iban" value="{{ old('bank_transfer_iban', $values['bank_transfer_iban']) }}" maxlength="64" class="input h-10 font-mono text-xs @error('bank_transfer_iban') input-error @enderror" dir="ltr">
                        @error('bank_transfer_iban')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="bank_transfer_swift">{{ __('ui.admin.payment_gateways.swift') }}</label>
                        <input id="bank_transfer_swift" type="text" name="bank_transfer_swift" value="{{ old('bank_transfer_swift', $values['bank_transfer_swift']) }}" maxlength="32" class="input h-10 font-mono text-xs" dir="ltr">
                    </div>
                    <div>
                        <label class="label" for="bank_transfer_routing_number">{{ __('ui.admin.payment_gateways.routing_number') }}</label>
                        <input id="bank_transfer_routing_number" type="text" name="bank_transfer_routing_number" value="{{ old('bank_transfer_routing_number', $values['bank_transfer_routing_number']) }}" maxlength="32" class="input h-10 font-mono text-xs" dir="ltr">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="bank_transfer_instructions">{{ __('ui.admin.payment_gateways.instructions') }}</label>
                        <textarea id="bank_transfer_instructions" name="bank_transfer_instructions" rows="3" maxlength="2000" class="input resize-y text-xs leading-6">{{ old('bank_transfer_instructions', $values['bank_transfer_instructions']) }}</textarea>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary h-11 w-full sm:w-auto sm:px-8">{{ __('ui.common.save') }}</button>
        </form>

        <div class="space-y-4">
            <div class="card p-5">
                <h2 class="font-semibold">{{ __('ui.admin.payment_gateways.status_title') }}</h2>
                <p class="mt-1 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.payment_gateways.status_hint') }}</p>
                <div class="mt-3 space-y-2">
                    @foreach ([
                        ['name' => 'Stripe', 'ready' => in_array(\App\Models\Payment::GATEWAY_STRIPE, $activeGateways, true)],
                        ['name' => 'PayPal', 'ready' => in_array(\App\Models\Payment::GATEWAY_PAYPAL, $activeGateways, true)],
                        ['name' => __('ui.billing.bank_transfer'), 'ready' => in_array(\App\Models\Payment::GATEWAY_BANK_TRANSFER, $activeGateways, true)],
                    ] as $gateway)
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-ink-950/10 px-3 py-2">
                            <span class="text-xs font-medium">{{ $gateway['name'] }}</span>
                            @if ($gateway['ready'])
                                <span class="badge badge-green">{{ __('ui.admin.payment_gateways.live') }}</span>
                            @else
                                <span class="badge badge-gray">{{ __('ui.admin.payment_gateways.off') }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
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
        </div>
    </div>
@endsection
