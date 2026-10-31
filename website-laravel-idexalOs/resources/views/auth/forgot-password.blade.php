@extends('layouts.auth')

@section('title', __('ui.auth.forgot_title').' — '.config('idexal.brand.name'))
@section('heading', __('ui.auth.forgot_title'))
@section('subheading', __('ui.auth.forgot_subtitle'))

@section('content')
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
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

        <button type="submit" class="btn btn-primary h-11 w-full">
            <x-icon name="mail" class="size-4" />
            {{ __('ui.auth.send_reset') }}
        </button>
    </form>

    <a href="{{ route('login') }}" class="btn btn-ghost mt-4 h-11 w-full">
        <x-icon name="arrow-right" class="size-4 rtl:rotate-180" />
        {{ __('ui.auth.login_button') }}
    </a>
@endsection
