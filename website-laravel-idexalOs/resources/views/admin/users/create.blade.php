@extends('layouts.admin')

@section('title', __('ui.admin.users.create'))
@section('page', __('ui.admin.nav.users'))

@section('content')
    <div>
        <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-400 transition hover:text-ink-950">
            <x-icon name="arrow-right" class="size-3.5 rtl:rotate-180" />
            {{ __('ui.admin.users.title') }}
        </a>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.users.create') }}</h1>
        <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.users.create_hint') }}</p>
    </div>

    <form method="POST" action="{{ route('admin.users.store') }}" class="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
        @csrf

        <div class="space-y-4">
            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.users.identity') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="name">{{ __('ui.common.name') }}</label>
                        <input id="name" type="text" name="name" value="{{ old('name') }}" required maxlength="120" class="input h-10 @error('name') input-error @enderror" autocomplete="off">
                        @error('name')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="email">{{ __('ui.common.email') }}</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="190" class="input h-10 font-mono @error('email') input-error @enderror" dir="ltr" autocomplete="off">
                        @error('email')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="phone">{{ __('ui.profile.phone') }}</label>
                        <input id="phone" type="text" name="phone" value="{{ old('phone') }}" maxlength="40" class="input h-10 font-mono" dir="ltr">
                    </div>
                    <div>
                        <label class="label" for="job_title">{{ __('ui.profile.job_title') }}</label>
                        <input id="job_title" type="text" name="job_title" value="{{ old('job_title') }}" maxlength="120" class="input h-10">
                    </div>
                    <div>
                        <label class="label" for="locale">{{ __('ui.profile.language') }}</label>
                        <select id="locale" name="locale" class="select h-10">
                            @foreach ($locales as $locale)
                                <option value="{{ $locale }}" @selected(old('locale') === $locale)>{{ strtoupper($locale) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="timezone">{{ __('ui.profile.timezone') }}</label>
                        <input id="timezone" type="text" name="timezone" value="{{ old('timezone') }}" maxlength="64" class="input h-10 font-mono" dir="ltr" placeholder="UTC">
                    </div>
                </div>
            </div>

            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.users.access') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="role">{{ __('ui.common.role') }}</label>
                        <select id="role" name="role" class="select h-10" required>
                            @foreach ($roles as $value => $label)
                                <option value="{{ $value }}" @selected(old('role', \App\Models\User::ROLE_USER) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="status">{{ __('ui.common.status') }}</label>
                        <select id="status" name="status" class="select h-10" required>
                            <option value="active" @selected(old('status', 'active') === 'active')>{{ __('ui.statuses.active') }}</option>
                            <option value="suspended" @selected(old('status') === 'suspended')>{{ __('ui.statuses.suspended') }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="password">{{ __('ui.admin.users.temporary_password') }}</label>
                        <input id="password" type="text" name="password" minlength="8" maxlength="190" class="input h-10 font-mono @error('password') input-error @enderror" dir="ltr" autocomplete="new-password" placeholder="{{ __('ui.admin.users.password_optional') }}">
                        <p class="mt-1.5 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.users.password_hint') }}</p>
                        @error('password')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.users.membership') }}</h2>
                <p class="mt-1 text-xs leading-5 text-ink-400">{{ __('ui.admin.users.membership_hint') }}</p>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="label" for="organization_id">{{ __('ui.admin.organizations.title') }}</label>
                        <select id="organization_id" name="organization_id" class="select h-10">
                            <option value="">{{ __('ui.admin.users.no_organization') }}</option>
                            @foreach ($organizations as $org)
                                <option value="{{ $org->getKey() }}" @selected(old('organization_id') === $org->getKey())>{{ $org->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="org_role">{{ __('ui.admin.users.org_role') }}</label>
                        <select id="org_role" name="org_role" class="select h-10">
                            @foreach ($orgRoles as $value => $label)
                                <option value="{{ $value }}" @selected(old('org_role', \App\Models\Membership::ROLE_MEMBER) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.users.verification') }}</h2>
                <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-xl border border-ink-950/10 p-3 text-sm transition hover:border-ink-950/25">
                    <input type="checkbox" name="email_verified" value="1" class="checkbox mt-0.5" @checked(old('email_verified'))>
                    <span>
                        <span class="font-medium">{{ __('ui.admin.users.mark_verified') }}</span>
                        <span class="mt-0.5 block text-[11px] leading-5 text-ink-400">{{ __('ui.admin.users.mark_verified_hint') }}</span>
                    </span>
                </label>
            </div>

            <div class="flex gap-2">
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline h-11 flex-1">{{ __('ui.common.cancel') }}</a>
                <button type="submit" class="btn btn-primary h-11 flex-1">{{ __('ui.common.create') }}</button>
            </div>
        </div>
    </form>
@endsection
