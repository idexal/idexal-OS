@extends('layouts.auth')

@section('title', __('ui.auth.reset_title').' — '.config('idexal.brand.name'))
@section('heading', __('ui.auth.reset_title'))
@section('subheading', __('ui.auth.reset_subtitle'))

@section('content')
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label class="label" for="email">{{ __('ui.auth.email') }}</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email', $email) }}"
                required
                autocomplete="email"
                class="input h-11 @error('email') input-error @enderror"
                dir="ltr"
            >
            @error('email')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="label" for="password">{{ __('ui.auth.password') }}</label>
            <input
                id="password"
                type="password"
                name="password"
                required
                autocomplete="new-password"
                class="input h-11 @error('password') input-error @enderror"
                dir="ltr"
            >
            @error('password')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="label" for="password_confirmation">{{ __('ui.auth.confirm_password') }}</label>
            <input
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                class="input h-11"
                dir="ltr"
            >
        </div>

        <button type="submit" class="btn btn-primary h-11 w-full">
            {{ __('ui.auth.reset_button') }}
            <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
        </button>
    </form>
@endsection
