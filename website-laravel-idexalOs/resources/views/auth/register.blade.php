@extends('layouts.auth')

@section('title', __('ui.auth.register_title').' — '.config('idexal.brand.name'))
@section('heading', __('ui.auth.register_title'))
@section('subheading', __('ui.auth.register_subtitle'))

@section('content')
    @include('partials.social-buttons', ['providers' => $socialProviders])

    <form method="POST" action="{{ route('register.store') }}" class="space-y-4" x-data="{ show: false }">
        @csrf

        @if (! empty($referralCode))
            <input type="hidden" name="ref" value="{{ old('ref', $referralCode) }}">
        @endif

        <div>
            <label class="label" for="name">{{ __('ui.auth.name') }}</label>
            <input
                id="name"
                type="text"
                name="name"
                value="{{ old('name') }}"
                required
                autofocus
                autocomplete="name"
                maxlength="120"
                class="input h-11 @error('name') input-error @enderror"
            >
            @error('name')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="label" for="email">{{ __('ui.auth.email') }}</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autocomplete="email"
                maxlength="190"
                class="input h-11 @error('email') input-error @enderror"
                dir="ltr"
                placeholder="you@example.com"
            >
            @error('email')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="label" for="password">{{ __('ui.auth.password') }}</label>
            <div class="relative">
                <input
                    id="password"
                    :type="show ? 'text' : 'password'"
                    name="password"
                    required
                    autocomplete="new-password"
                    class="input h-11 pe-11 @error('password') input-error @enderror"
                    dir="ltr"
                >
                <button type="button" class="absolute end-2 top-1/2 grid size-8 -translate-y-1/2 place-items-center rounded-lg text-ink-400 transition hover:bg-paper hover:text-ink-950" @click="show = !show" :aria-label="show ? '{{ __('ui.common.hide') }}' : '{{ __('ui.common.show') }}'">
                    <x-icon name="eye" class="size-4" />
                </button>
            </div>
            @error('password')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1.5 text-[11px] text-ink-400">{{ __('ui.auth.password_hint') }}</p>
        </div>

        <div>
            <label class="label" for="password_confirmation">{{ __('ui.auth.confirm_password') }}</label>
            <input
                id="password_confirmation"
                :type="show ? 'text' : 'password'"
                name="password_confirmation"
                required
                autocomplete="new-password"
                class="input h-11"
                dir="ltr"
            >
        </div>

        <button type="submit" class="btn btn-primary h-11 w-full">
            {{ __('ui.auth.register_button') }}
            <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
        </button>
    </form>

    <p class="mt-4 text-center text-[11px] leading-5 text-ink-400">
        {{ __('ui.auth.terms_agree') }}
        <a href="{{ route('legal.terms') }}" class="font-medium text-ink-700 underline decoration-ink-300 underline-offset-4 hover:text-ink-950">{{ __('ui.auth.terms') }}</a>
        {{ __('ui.common.of') }}
        <a href="{{ route('legal.privacy') }}" class="font-medium text-ink-700 underline decoration-ink-300 underline-offset-4 hover:text-ink-950">{{ __('ui.auth.privacy') }}</a>
    </p>

    <div class="mt-6 flex items-center gap-3">
        <span class="h-px flex-1 bg-ink-950/10"></span>
        <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-300">{{ __('ui.auth.have_account') }}</span>
        <span class="h-px flex-1 bg-ink-950/10"></span>
    </div>

    <a href="{{ route('login') }}" class="btn btn-outline mt-4 h-11 w-full">{{ __('ui.auth.login_button') }}</a>
@endsection
