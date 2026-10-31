@extends('layouts.admin')

@section('title', $user->name)
@section('page', __('ui.admin.nav.users'))

@php
    $isSelf = $user->getKey() === auth()->id();
    $lastAdmin = $user->role === \App\Models\User::ROLE_ADMIN && $adminCount <= 1;
    $activeKeys = $apiKeys->whereNull('revoked_at')->count();
    $activeSessions = $desktopSessions->whereNull('revoked_at')->count();
@endphp

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-400 transition hover:text-ink-950">
                <x-icon name="arrow-right" class="size-3.5 rtl:rotate-180" />
                {{ __('ui.admin.users.title') }}
            </a>
            <div class="mt-3 flex items-center gap-3">
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-ink-950 font-mono text-base font-semibold text-lime">{{ $user->initials() ?: '?' }}</span>
                <div class="min-w-0">
                    <h1 class="truncate text-2xl font-semibold tracking-tight sm:text-3xl">
                        {{ $user->name }}
                        @if ($isSelf)
                            <span class="text-xs font-normal text-ink-400">({{ __('ui.team.you') }})</span>
                        @endif
                    </h1>
                    <p class="mt-0.5 truncate font-mono text-xs text-ink-400" dir="ltr">{{ $user->email }}</p>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="badge {{ $user->role === 'admin' ? 'badge-lime' : 'badge-gray' }}">{{ $roles[$user->role] ?? $user->role }}</span>
            <span class="badge {{ $user->status === 'active' ? 'badge-green' : 'badge-red' }}">{{ __('ui.statuses.'.$user->status) }}</span>
            @if ($user->email_verified_at)
                <span class="badge badge-green">{{ __('ui.admin.users.verified_yes') }}</span>
            @else
                <span class="badge badge-amber">{{ __('ui.admin.users.verified_no') }}</span>
            @endif
        </div>
    </div>

    @if ($user->trashed())
        <div class="alert alert-error">{{ __('ui.admin.users.soft_deleted') }}</div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
        <div class="space-y-4">
            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.users.profile') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ([
                        ['label' => __('ui.common.id'), 'value' => $user->getKey(), 'mono' => true],
                        ['label' => __('ui.profile.phone'), 'value' => $user->phone ?: '—', 'mono' => true],
                        ['label' => __('ui.profile.job_title'), 'value' => $user->job_title ?: '—'],
                        ['label' => __('ui.profile.language'), 'value' => strtoupper((string) ($user->locale ?: '—'))],
                        ['label' => __('ui.profile.timezone'), 'value' => $user->timezone ?: '—', 'mono' => true],
                        ['label' => __('ui.common.created_at'), 'value' => $user->created_at?->translatedFormat('d M Y H:i') ?? '—'],
                        ['label' => __('ui.admin.users.last_login'), 'value' => $user->last_login_at?->translatedFormat('d M Y H:i') ?? '—'],
                        ['label' => __('ui.admin.users.updated_at'), 'value' => $user->updated_at?->translatedFormat('d M Y H:i') ?? '—'],
                    ] as $row)
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ $row['label'] }}</p>
                            <p class="mt-1 truncate text-sm text-ink-700 {{ ! empty($row['mono']) ? 'font-mono text-xs' : '' }}" @if (! empty($row['mono'])) dir="ltr" @endif>{{ $row['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-ink-950/5 p-4">
                    <h2 class="font-semibold">{{ __('ui.admin.users.memberships_title') }}</h2>
                    <span class="badge badge-gray">{{ $user->memberships->count() }}</span>
                </div>
                @if ($user->memberships->isEmpty())
                    <p class="p-6 text-center text-xs text-ink-400">{{ __('ui.admin.users.memberships_empty') }}</p>
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('ui.admin.organizations.title') }}</th>
                                    <th>{{ __('ui.common.role') }}</th>
                                    <th>{{ __('ui.common.status') }}</th>
                                    <th>{{ __('ui.team.joined') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($user->memberships as $membership)
                                    <tr>
                                        <td>
                                            @if ($membership->organization)
                                                <a href="{{ route('admin.organizations.show', $membership->organization) }}" class="text-sm font-semibold hover:underline">{{ $membership->organization->name }}</a>
                                                <p class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $membership->organization->slug }}</p>
                                            @else
                                                <span class="text-ink-300">—</span>
                                            @endif
                                        </td>
                                        <td><span class="badge badge-gray">{{ $orgRoles[$membership->role] ?? $membership->role }}</span></td>
                                        <td><span class="badge {{ $membership->status === 'active' ? 'badge-green' : 'badge-red' }}">{{ __('ui.statuses.'.$membership->status) }}</span></td>
                                        <td class="whitespace-nowrap text-xs text-ink-400">{{ $membership->joined_at?->translatedFormat('d M Y') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="card overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-ink-950/5 p-4">
                    <div>
                        <h2 class="font-semibold">{{ __('ui.common.api_keys') }}</h2>
                        <p class="mt-0.5 text-[11px] text-ink-400">{{ __('ui.admin.users.keys_open', ['count' => $activeKeys]) }}</p>
                    </div>
                    @if ($activeKeys > 0)
                        <form method="POST" action="{{ route('admin.users.revoke-keys', $user) }}" x-data @submit="if (! confirm(@js(__('ui.admin.users.confirm_revoke_keys')))) { $event.preventDefault(); }">
                            @csrf
                            <button type="submit" class="btn btn-outline btn-sm h-9 px-3 text-red-600">{{ __('ui.admin.users.revoke_keys') }}</button>
                        </form>
                    @endif
                </div>
                @if ($apiKeys->isEmpty())
                    <p class="p-6 text-center text-xs text-ink-400">{{ __('ui.admin.users.keys_empty') }}</p>
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('ui.common.label') }}</th>
                                    <th>{{ __('ui.admin.users.abilities') }}</th>
                                    <th>{{ __('ui.admin.users.last_used') }}</th>
                                    <th>{{ __('ui.admin.users.expires') }}</th>
                                    <th>{{ __('ui.common.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($apiKeys as $key)
                                    <tr>
                                        <td class="text-sm">{{ $key->label ?: '—' }}</td>
                                        <td>
                                            <div class="flex flex-wrap gap-1">
                                                @foreach (array_slice((array) $key->abilities, 0, 3) as $ability)
                                                    <span class="badge badge-gray font-mono text-[9px]" dir="ltr">{{ $ability }}</span>
                                                @endforeach
                                                @if (count((array) $key->abilities) > 3)
                                                    <span class="badge badge-gray">+{{ count((array) $key->abilities) - 3 }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap text-xs text-ink-400">{{ $key->last_used_at?->translatedFormat('d M Y') ?? '—' }}</td>
                                        <td class="whitespace-nowrap text-xs text-ink-400">{{ $key->expires_at?->translatedFormat('d M Y') ?? __('ui.common.never') }}</td>
                                        <td>
                                            <span class="badge {{ $key->revoked_at === null ? 'badge-green' : 'badge-red' }}">
                                                {{ $key->revoked_at === null ? __('ui.statuses.active') : __('ui.admin.users.revoked') }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="card overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-ink-950/5 p-4">
                    <div>
                        <h2 class="font-semibold">{{ __('ui.desktop.sessions') }}</h2>
                        <p class="mt-0.5 text-[11px] text-ink-400">{{ __('ui.admin.users.sessions_open', ['count' => $activeSessions]) }}</p>
                    </div>
                    @if ($activeSessions > 0)
                        <form method="POST" action="{{ route('admin.users.revoke-sessions', $user) }}" x-data @submit="if (! confirm(@js(__('ui.admin.users.confirm_revoke_sessions')))) { $event.preventDefault(); }">
                            @csrf
                            <button type="submit" class="btn btn-outline btn-sm h-9 px-3 text-red-600">{{ __('ui.admin.users.revoke_sessions') }}</button>
                        </form>
                    @endif
                </div>
                @if ($desktopSessions->isEmpty())
                    <p class="p-6 text-center text-xs text-ink-400">{{ __('ui.admin.users.sessions_empty') }}</p>
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('ui.admin.users.device') }}</th>
                                    <th>{{ __('ui.admin.users.ip') }}</th>
                                    <th>{{ __('ui.admin.users.last_seen') }}</th>
                                    <th>{{ __('ui.common.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($desktopSessions as $session)
                                    <tr>
                                        <td>
                                            <p class="text-sm">{{ $session->device_name ?: '—' }}</p>
                                            <p class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $session->platform }} {{ $session->app_version }}</p>
                                        </td>
                                        <td class="font-mono text-xs text-ink-500" dir="ltr">{{ $session->ip ?: '—' }}</td>
                                        <td class="whitespace-nowrap text-xs text-ink-400">{{ $session->last_seen_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                                        <td>
                                            <span class="badge {{ $session->revoked_at === null ? 'badge-green' : 'badge-red' }}">
                                                {{ $session->revoked_at === null ? __('ui.statuses.active') : __('ui.admin.users.revoked') }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="card overflow-hidden">
                <div class="border-b border-ink-950/5 p-4">
                    <h2 class="font-semibold">{{ __('ui.admin.users.activity_log') }}</h2>
                </div>
                @if ($auditEntries->isEmpty())
                    <p class="p-6 text-center text-xs text-ink-400">{{ __('ui.admin.users.activity_empty') }}</p>
                @else
                    <ul class="divide-y divide-ink-950/5">
                        @foreach ($auditEntries as $entry)
                            <li class="flex items-start justify-between gap-3 p-3">
                                <div class="min-w-0">
                                    <p class="font-mono text-[11px] text-ink-600" dir="ltr">{{ $entry->action }}</p>
                                    @if ($entry->description)
                                        <p class="mt-0.5 truncate text-xs text-ink-500">{{ $entry->description }}</p>
                                    @endif
                                </div>
                                <span class="shrink-0 whitespace-nowrap text-[10px] text-ink-400">{{ $entry->created_at?->translatedFormat('d M Y H:i') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.users.edit') }}</h2>
                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="label" for="u-name">{{ __('ui.common.name') }}</label>
                        <input id="u-name" type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" class="input h-10 @error('name') input-error @enderror">
                        @error('name')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="u-email">{{ __('ui.common.email') }}</label>
                        <input id="u-email" type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="190" class="input h-10 font-mono @error('email') input-error @enderror" dir="ltr">
                        <p class="mt-1.5 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.users.email_change_note') }}</p>
                        @error('email')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="u-role">{{ __('ui.common.role') }}</label>
                            <select id="u-role" name="role" class="select h-10">
                                @foreach ($roles as $value => $label)
                                    <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @if ($isSelf)
                                <p class="mt-1.5 text-[11px] text-ink-400">{{ __('ui.admin.users.cannot_self') }}</p>
                            @endif
                            @error('role')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="label" for="u-status">{{ __('ui.common.status') }}</label>
                            <select id="u-status" name="status" class="select h-10">
                                <option value="active" @selected(old('status', $user->status) === 'active')>{{ __('ui.statuses.active') }}</option>
                                <option value="suspended" @selected(old('status', $user->status) === 'suspended')>{{ __('ui.statuses.suspended') }}</option>
                            </select>
                            @error('status')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="u-phone">{{ __('ui.profile.phone') }}</label>
                            <input id="u-phone" type="text" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="40" class="input h-10 font-mono" dir="ltr">
                        </div>
                        <div>
                            <label class="label" for="u-job">{{ __('ui.profile.job_title') }}</label>
                            <input id="u-job" type="text" name="job_title" value="{{ old('job_title', $user->job_title) }}" maxlength="120" class="input h-10">
                        </div>
                        <div>
                            <label class="label" for="u-locale">{{ __('ui.profile.language') }}</label>
                            <select id="u-locale" name="locale" class="select h-10">
                                @foreach ($locales as $locale)
                                    <option value="{{ $locale }}" @selected(old('locale', $user->locale) === $locale)>{{ strtoupper($locale) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label" for="u-timezone">{{ __('ui.profile.timezone') }}</label>
                            <input id="u-timezone" type="text" name="timezone" value="{{ old('timezone', $user->timezone) }}" maxlength="64" class="input h-10 font-mono" dir="ltr">
                        </div>
                    </div>

                    @if ($isSelf)
                        <p class="text-[11px] leading-5 text-ink-400">{{ __('ui.admin.users.cannot_self') }}</p>
                    @endif

                    <button type="submit" class="btn btn-primary h-10 w-full">{{ __('ui.common.save') }}</button>
                </form>
            </div>

            <div class="card card-pad space-y-2">
                <h2 class="font-semibold">{{ __('ui.admin.users.actions') }}</h2>

                @if ($user->email_verified_at === null)
                    <form method="POST" action="{{ route('admin.users.verify', $user) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline h-10 w-full justify-start">
                            <x-icon name="check-circle" class="size-4" />
                            {{ __('ui.admin.users.verify_now') }}
                        </button>
                    </form>
                @endif

                <form method="POST" action="{{ route('admin.users.reset', $user) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline h-10 w-full justify-start">
                        <x-icon name="mail" class="size-4" />
                        {{ __('ui.admin.users.send_reset') }}
                    </button>
                </form>

                @unless ($isSelf)
                    <form method="POST" action="{{ route('admin.users.status', $user) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $user->status === 'active' ? 'suspended' : 'active' }}">
                        <button type="submit" class="btn btn-outline h-10 w-full justify-start" @disabled($lastAdmin && $user->status === 'active')>
                            <x-icon :name="$user->status === 'active' ? 'alert' : 'check-circle'" class="size-4" />
                            {{ $user->status === 'active' ? __('ui.admin.users.suspend') : __('ui.admin.users.activate') }}
                        </button>
                    </form>

                    <form
                        method="POST"
                        action="{{ route('admin.users.destroy', $user) }}"
                        x-data
                        @submit="if (! confirm(@js(__('ui.admin.users.confirm_delete', ['name' => $user->name])))) { $event.preventDefault(); }"
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline h-10 w-full justify-start text-red-600" @disabled($user->ownedOrganizations->isNotEmpty())>
                            <x-icon name="trash" class="size-4" />
                            {{ __('ui.admin.users.delete') }}
                        </button>
                    </form>

                    @if ($user->ownedOrganizations->isNotEmpty())
                        <p class="text-[11px] leading-5 text-ink-400">{{ __('ui.admin.users.owns_organizations') }}</p>
                    @endif
                @endunless

                @foreach ($errors->all() as $message)
                    <p class="text-[11px] leading-5 text-red-600">{{ $message }}</p>
                @endforeach
            </div>

            @if ($user->ownedOrganizations->isNotEmpty())
                <div class="card card-pad">
                    <h2 class="font-semibold">{{ __('ui.admin.users.owns_title') }}</h2>
                    <ul class="mt-3 space-y-2">
                        @foreach ($user->ownedOrganizations as $owned)
                            <li>
                                <a href="{{ route('admin.organizations.show', $owned) }}" class="flex items-center justify-between gap-2 rounded-xl border border-ink-950/10 p-3 text-sm transition hover:border-ink-950/25">
                                    <span class="truncate font-medium">{{ $owned->name }}</span>
                                    <x-icon name="arrow-left" class="size-4 text-ink-300 rtl:rotate-180" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($user->socialAccounts->isNotEmpty())
                <div class="card card-pad">
                    <h2 class="font-semibold">{{ __('ui.admin.users.social_title') }}</h2>
                    <ul class="mt-3 space-y-2">
                        @foreach ($user->socialAccounts as $account)
                            <li class="flex items-center justify-between gap-2 text-sm">
                                <span class="font-medium capitalize">{{ $account->provider }}</span>
                                <span class="truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $account->nickname ?: $account->provider_user_id }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
@endsection
