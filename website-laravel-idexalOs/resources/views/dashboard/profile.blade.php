@extends('layouts.dashboard')

@section('title', __('ui.profile.title'))
@section('page', __('ui.dashboard.nav.profile'))

@php
    $timezones = timezone_identifiers_list();
@endphp

@section('content')
    <div>
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.profile.title') }}</h1>
        <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.profile.subtitle') }}</p>
    </div>

    <div class="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
        <div class="space-y-4">
            <div class="card card-pad">
                <div class="flex items-start gap-4">
                    <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-ink-950 font-mono text-lg font-semibold text-lime">
                        {{ mb_substr((string) $user->name, 0, 1) }}
                    </span>
                    <div>
                        <h2 class="font-semibold">{{ __('ui.profile.personal_info') }}</h2>
                        <p class="mt-1 text-xs leading-6 text-ink-400">{{ __('ui.profile.subtitle') }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('dashboard.profile.update') }}" class="mt-6 space-y-4">
                    @csrf
                    @method('PATCH')

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="name">{{ __('ui.common.name') }}</label>
                            <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" class="input h-10 @error('name') input-error @enderror" autocomplete="name">
                            @error('name')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="label" for="email">{{ __('ui.common.email') }}</label>
                            <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="190" class="input h-10 @error('email') input-error @enderror" dir="ltr" autocomplete="email">
                            @error('email')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="label" for="phone">{{ __('ui.profile.phone') }} <span class="font-normal text-ink-400">({{ __('ui.common.optional') }})</span></label>
                            <input id="phone" type="tel" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30" class="input h-10 @error('phone') input-error @enderror" dir="ltr" autocomplete="tel">
                            @error('phone')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="label" for="job_title">{{ __('ui.profile.job_title') }} <span class="font-normal text-ink-400">({{ __('ui.common.optional') }})</span></label>
                            <input id="job_title" type="text" name="job_title" value="{{ old('job_title', $user->job_title) }}" maxlength="120" class="input h-10 @error('job_title') input-error @enderror">
                            @error('job_title')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="label" for="locale">{{ __('ui.profile.language') }}</label>
                            <select id="locale" name="locale" class="select h-10">
                                <option value="ar" @selected(old('locale', $user->locale) === 'ar')>العربية</option>
                                <option value="en" @selected(old('locale', $user->locale) === 'en')>English</option>
                            </select>
                            @error('locale')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="label" for="timezone">{{ __('ui.profile.timezone') }}</label>
                            <select id="timezone" name="timezone" class="select h-10 text-xs">
                                @foreach ($timezones as $timezone)
                                    <option value="{{ $timezone }}" @selected(old('timezone', $user->timezone) === $timezone)>{{ $timezone }}</option>
                                @endforeach
                            </select>
                            @error('timezone')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary h-10 px-5">{{ __('ui.common.save') }}</button>
                </form>
            </div>

            <div class="card card-pad">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-paper text-ink-500"><x-icon name="lock" class="size-5" /></span>
                    <div>
                        <h2 class="font-semibold">{{ __('ui.profile.change_password') }}</h2>
                        <p class="mt-1 text-xs leading-6 text-ink-400">{{ __('ui.profile.password_hint') }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('dashboard.profile.password') }}" class="mt-5 grid gap-4 sm:grid-cols-3">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label class="label" for="current_password">{{ __('ui.profile.current_password') }}</label>
                        <input id="current_password" type="password" name="current_password" required class="input h-10 @error('current_password') input-error @enderror" dir="ltr" autocomplete="current-password">
                        @error('current_password')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="password">{{ __('ui.profile.new_password') }}</label>
                        <input id="password" type="password" name="password" required class="input h-10 @error('password') input-error @enderror" dir="ltr" autocomplete="new-password">
                        @error('password')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="password_confirmation">{{ __('ui.auth.confirm_password') }}</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" required class="input h-10" dir="ltr" autocomplete="new-password">
                    </div>
                    <div class="sm:col-span-3">
                        <button type="submit" class="btn btn-outline h-10 px-5">{{ __('ui.profile.change_password') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card p-5">
                <h2 class="font-semibold">{{ __('ui.profile.account_info') }}</h2>
                <div class="mt-4 space-y-3 text-xs">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">ID</span>
                        <span class="max-w-[160px] truncate font-mono text-[10px]" dir="ltr">{{ $user->id }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.common.role') }}</span>
                        <span class="badge badge-gray">{{ __('ui.roles.'.$user->role) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.profile.member_since') }}</span>
                        <span class="font-mono">{{ $user->created_at?->translatedFormat('d M Y') }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.profile.email_status') }}</span>
                        @if ($user->email_verified_at)
                            <span class="badge badge-green">{{ __('ui.common.verified') }}</span>
                        @else
                            <span class="badge badge-amber">{{ __('ui.common.unverified') }}</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.profile.last_login') }}</span>
                        <span>{{ $user->last_login_at?->diffForHumans() ?? __('ui.common.never') }}</span>
                    </div>
                </div>
            </div>

            @if ($organization)
                <div class="card p-5">
                    <h2 class="font-semibold">{{ __('ui.profile.organization') }}</h2>
                    <div class="mt-3 flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-xl bg-lime/20 font-mono text-xs font-semibold text-ink-950">{{ $organization->initials() }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $organization->name }}</p>
                            <p class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $organization->slug }}</p>
                        </div>
                    </div>
                    <a href="{{ route('dashboard.settings') }}" class="btn btn-outline btn-sm mt-4 w-full">{{ __('ui.settings.title') }}</a>
                </div>
            @endif

            <div class="rounded-3xl border border-ink-950/10 bg-white p-5">
                <div class="flex items-start gap-3">
                    <x-icon name="shield" class="mt-0.5 size-5 text-lime-dark" />
                    <div>
                        <p class="text-xs font-semibold">{{ __('ui.profile.security_title') }}</p>
                        <p class="mt-1 text-[11px] leading-5 text-ink-400">{{ __('ui.profile.security_body') }}</p>
                        <a href="{{ route('dashboard.desktop') }}" class="mt-3 inline-flex items-center gap-1.5 text-[11px] font-semibold text-ink-950">
                            {{ __('ui.desktop.sessions') }}
                            <x-icon name="arrow-left" class="size-3 rtl:rotate-180" />
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
