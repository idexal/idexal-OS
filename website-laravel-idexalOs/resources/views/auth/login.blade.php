@extends('layouts.auth')

@section('title', __('ui.auth.login_title').' — '.config('idexal.brand.name'))
@section('heading', __('ui.auth.login_title'))
@section('subheading', __('ui.auth.login_subtitle'))

@section('content')
    @if ($desktopAuth)
        <div class="mb-5 flex items-start gap-3 rounded-2xl border border-lime-dark/20 bg-lime/15 p-4">
            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-ink-950 text-lime">
                <x-icon name="monitor" class="size-4" />
            </span>
            <div>
                <p class="text-xs font-semibold text-ink-950">{{ __('ui.desktop.login_banner_title') }}</p>
                <p class="mt-0.5 text-[11px] leading-5 text-ink-600">{{ __('ui.desktop.login_banner_body') }}</p>
            </div>
        </div>
    @endif

    @include('partials.social-buttons', ['providers' => $socialProviders])

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4" x-data="{ show: false }">
        @csrf

        <div>
            <label class="label" for="email">{{ __('ui.auth.email') }}</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="email"
                class="input h-11 @error('email') input-error @enderror"
                dir="ltr"
                placeholder="you@example.com"
            >
            @error('email')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <label class="label !mb-0" for="password">{{ __('ui.auth.password') }}</label>
                <a href="{{ route('password.request') }}" class="text-[11px] font-medium text-ink-500 transition hover:text-ink-950">{{ __('ui.auth.forgot') }}</a>
            </div>
            <div class="relative">
                <input
                    id="password"
                    :type="show ? 'text' : 'password'"
                    name="password"
                    required
                    autocomplete="current-password"
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
        </div>

        <label class="flex items-center gap-2.5 text-sm text-ink-600">
            <input type="checkbox" name="remember" class="checkbox" @checked(old('remember'))>
            {{ __('ui.auth.remember') }}
        </label>

        <button type="submit" class="btn btn-primary h-11 w-full">
            {{ __('ui.auth.login_button') }}
            <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
        </button>
    </form>

    <div class="mt-6 flex items-center gap-3">
        <span class="h-px flex-1 bg-ink-950/10"></span>
        <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-300">{{ __('ui.auth.no_account') }}</span>
        <span class="h-px flex-1 bg-ink-950/10"></span>
    </div>

    <a href="{{ route('register') }}" class="btn btn-outline mt-4 h-11 w-full">{{ __('ui.auth.create_account') }}</a>

    <p class="mt-5 text-center text-[11px] leading-5 text-ink-400">
        {{ __('ui.auth.terms_agree') }}
        <a href="{{ route('legal.terms') }}" class="font-medium text-ink-700 underline decoration-ink-300 underline-offset-4 hover:text-ink-950">{{ __('ui.auth.terms') }}</a>
        {{ __('ui.common.of') }}
        <a href="{{ route('legal.privacy') }}" class="font-medium text-ink-700 underline decoration-ink-300 underline-offset-4 hover:text-ink-950">{{ __('ui.auth.privacy') }}</a>
    </p>
@endsection
