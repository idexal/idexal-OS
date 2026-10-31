@extends('layouts.dashboard')

@section('title', __('ui.settings.title'))
@section('page', __('ui.dashboard.nav.settings'))

@section('content')
    <div>
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.settings.title') }}</h1>
        <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.settings.subtitle') }}</p>
    </div>

    @if (! $canManage)
        <div class="alert alert-info">
            <x-icon name="lock" class="mt-0.5 size-4" />
            <p>{{ __('ui.settings.read_only') }}</p>
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
        <div class="card card-pad">
            <div class="flex items-start gap-4">
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-ink-950 font-mono text-sm font-semibold text-lime">{{ $organization->initials() }}</span>
                <div>
                    <h2 class="font-semibold">{{ __('ui.settings.organization_profile') }}</h2>
                    <p class="mt-1 text-xs leading-6 text-ink-400">{{ __('ui.settings.subtitle') }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('dashboard.settings.update') }}" class="mt-6 space-y-5">
                @csrf
                @method('PATCH')

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="name">{{ __('ui.settings.organization_name') }}</label>
                        <input id="name" type="text" name="name" value="{{ old('name', $organization->name) }}" required maxlength="120" class="input h-10 @error('name') input-error @enderror" @disabled(! $canManage)>
                        @error('name')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="billing_email">{{ __('ui.settings.billing_email') }}</label>
                        <input id="billing_email" type="email" name="billing_email" value="{{ old('billing_email', $organization->billing_email) }}" maxlength="190" class="input h-10 @error('billing_email') input-error @enderror" dir="ltr" @disabled(! $canManage)>
                        @error('billing_email')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="label" for="currency">{{ __('ui.settings.currency') }}</label>
                        <select id="currency" name="currency" class="select h-10" @disabled(! $canManage)>
                            @foreach (['USD', 'EUR', 'GBP', 'SAR', 'AED', 'EGP', 'QAR', 'KWD', 'BHD', 'OMR', 'JOD'] as $currency)
                                <option value="{{ $currency }}" @selected(old('currency', $organization->currency) === $currency)>{{ $currency }}</option>
                            @endforeach
                        </select>
                        @error('currency')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="country">{{ __('ui.settings.country') }}</label>
                        <input id="country" type="text" name="country" value="{{ old('country', $organization->country) }}" maxlength="2" class="input h-10 font-mono uppercase @error('country') input-error @enderror" dir="ltr" placeholder="SA" @disabled(! $canManage)>
                        @error('country')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="city">{{ __('ui.settings.city') }}</label>
                        <input id="city" type="text" name="city" value="{{ old('city', $organization->city) }}" maxlength="120" class="input h-10 @error('city') input-error @enderror" @disabled(! $canManage)>
                        @error('city')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="address_line_1">{{ __('ui.settings.address_line_1') }}</label>
                        <input id="address_line_1" type="text" name="address_line_1" value="{{ old('address_line_1', $organization->address_line_1) }}" maxlength="190" class="input h-10 @error('address_line_1') input-error @enderror" @disabled(! $canManage)>
                        @error('address_line_1')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="address_line_2">{{ __('ui.settings.address_line_2') }}</label>
                        <input id="address_line_2" type="text" name="address_line_2" value="{{ old('address_line_2', $organization->address_line_2) }}" maxlength="190" class="input h-10 @error('address_line_2') input-error @enderror" @disabled(! $canManage)>
                        @error('address_line_2')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="postal_code">{{ __('ui.settings.postal_code') }}</label>
                        <input id="postal_code" type="text" name="postal_code" value="{{ old('postal_code', $organization->postal_code) }}" maxlength="30" class="input h-10 font-mono @error('postal_code') input-error @enderror" dir="ltr" @disabled(! $canManage)>
                        @error('postal_code')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="tax_id">{{ __('ui.settings.tax_id') }}</label>
                        <input id="tax_id" type="text" name="tax_id" value="{{ old('tax_id', $organization->tax_id) }}" maxlength="60" class="input h-10 font-mono @error('tax_id') input-error @enderror" dir="ltr" @disabled(! $canManage)>
                        @error('tax_id')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @if ($canManage)
                    <div class="flex items-center justify-between gap-4 border-t border-ink-950/10 pt-5">
                        <p class="text-[11px] leading-5 text-ink-400">{{ __('ui.settings.invoice_note') }}</p>
                        <button type="submit" class="btn btn-primary h-10 shrink-0 px-5">{{ __('ui.common.save') }}</button>
                    </div>
                @endif
            </form>
        </div>

        <div class="space-y-4">
            <div class="card p-5">
                <h2 class="font-semibold">{{ __('ui.settings.details') }}</h2>
                <div class="mt-4 space-y-3 text-xs">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.settings.slug') }}</span>
                        <span class="font-mono text-[10px]" dir="ltr">{{ $organization->slug }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.common.type') }}</span>
                        <span class="badge badge-gray">{{ $organization->isPersonal() ? __('ui.settings.personal') : __('ui.settings.team') }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.common.status') }}</span>
                        <span class="badge {{ $organization->status === 'active' ? 'badge-green' : 'badge-red' }}">{{ __('ui.statuses.'.$organization->status) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.settings.created') }}</span>
                        <span>{{ $organization->created_at?->translatedFormat('d M Y') }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.dashboard.stats.credits') }}</span>
                        <span class="font-mono font-semibold">{{ money($organization->creditBalance(), $organization->currency) }}</span>
                    </div>
                </div>
            </div>

            <div class="card p-5">
                <h2 class="font-semibold">{{ __('ui.settings.quick_links') }}</h2>
                <div class="mt-3 space-y-2">
                    @if ($canManageBilling)
                        <a href="{{ route('dashboard.billing') }}" class="flex items-center gap-3 rounded-xl border border-ink-950/10 p-3 text-xs font-medium transition hover:border-ink-950/25">
                            <span class="grid size-8 place-items-center rounded-lg bg-paper text-ink-500"><x-icon name="card" class="size-3.5" /></span>
                            {{ __('ui.billing.title') }}
                            <x-icon name="arrow-left" class="ms-auto size-3.5 text-ink-300 rtl:rotate-180" />
                        </a>
                    @endif
                    <a href="{{ route('dashboard.team') }}" class="flex items-center gap-3 rounded-xl border border-ink-950/10 p-3 text-xs font-medium transition hover:border-ink-950/25">
                        <span class="grid size-8 place-items-center rounded-lg bg-paper text-ink-500"><x-icon name="users" class="size-3.5" /></span>
                        {{ __('ui.team.title') }}
                        <x-icon name="arrow-left" class="ms-auto size-3.5 text-ink-300 rtl:rotate-180" />
                    </a>
                    <a href="{{ route('dashboard.api-keys.index') }}" class="flex items-center gap-3 rounded-xl border border-ink-950/10 p-3 text-xs font-medium transition hover:border-ink-950/25">
                        <span class="grid size-8 place-items-center rounded-lg bg-paper text-ink-500"><x-icon name="key" class="size-3.5" /></span>
                        {{ __('ui.common.api_keys') }}
                        <x-icon name="arrow-left" class="ms-auto size-3.5 text-ink-300 rtl:rotate-180" />
                    </a>
                </div>
            </div>

            <div class="rounded-3xl border border-ink-950/10 bg-white p-5">
                <div class="flex items-start gap-3">
                    <x-icon name="shield" class="mt-0.5 size-5 text-lime-dark" />
                    <div>
                        <p class="text-xs font-semibold">{{ __('ui.settings.owner') }}</p>
                        <p class="mt-1 text-[11px] leading-5 text-ink-400">{{ $organization->owner?->name }} · <span dir="ltr">{{ $organization->owner?->email }}</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
