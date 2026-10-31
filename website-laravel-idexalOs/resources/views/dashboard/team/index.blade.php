@extends('layouts.dashboard')

@section('title', __('ui.team.title'))
@section('page', __('ui.dashboard.nav.team'))

@php
    $activeSeats = $memberships->count();
    $seatPercent = $seatLimit ? min(100, (int) round(($activeSeats / max(1, $seatLimit)) * 100)) : 0;
@endphp

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.team.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.team.subtitle') }}</p>
        </div>
        @if ($canManage)
            <a href="#invite" class="btn btn-primary btn-sm h-10 px-4">
                <x-icon name="plus" class="size-4" />
                {{ __('ui.team.invite') }}
            </a>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="card p-5">
            <p class="stat-label">{{ __('ui.team.members') }}</p>
            <p class="stat-value mt-2">{{ compact_number($activeSeats) }}</p>
            <p class="mt-2 text-[10px] text-ink-400">{{ __('ui.team.seat_usage') }}</p>
        </div>
        <div class="card p-5">
            <p class="stat-label">{{ __('ui.team.seat_usage') }}</p>
            <div class="mt-4 flex items-baseline justify-between">
                <p class="font-mono text-2xl font-medium">{{ $activeSeats }} / {{ $seatLimit ?? '∞' }}</p>
                <span class="text-[10px] text-ink-400">{{ $seatLimit ? $seatPercent.'%' : __('ui.usage.unlimited') }}</span>
            </div>
            <div class="progress mt-3"><span style="width: {{ $seatLimit ? max(2, $seatPercent) : 2 }}%"></span></div>
        </div>
        <div class="card p-5">
            <p class="stat-label">{{ __('ui.team.pending') }}</p>
            <p class="stat-value mt-2">{{ compact_number($invitations->count()) }}</p>
            <p class="mt-2 text-[10px] text-ink-400">{{ __('ui.team.no_pending') }}</p>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-ink-950/10 p-5">
            <div>
                <h2 class="font-semibold">{{ __('ui.team.members') }}</h2>
                <p class="mt-0.5 text-xs text-ink-400">{{ __('ui.team.subtitle') }}</p>
            </div>
            @if ($seatLimit)
                <span class="badge badge-gray">{{ $activeSeats }} / {{ $seatLimit }}</span>
            @endif
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('ui.common.name') }}</th>
                        <th>{{ __('ui.team.role') }}</th>
                        <th>{{ __('ui.team.joined') }}</th>
                        <th>{{ __('ui.users.last_login') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($memberships as $member)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="grid size-9 place-items-center rounded-full bg-ink-950 font-mono text-xs font-semibold text-lime">
                                        {{ mb_substr((string) $member->user?->name, 0, 1) }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-ink-950">
                                            {{ $member->user?->name }}
                                            @if ($member->user_id === auth()->id())
                                                <span class="text-[10px] font-normal text-ink-400">({{ __('ui.team.you') }})</span>
                                            @endif
                                        </p>
                                        <p class="truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $member->user?->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($canManage && ! $member->isOwner() && $member->user_id !== auth()->id())
                                    <form method="POST" action="{{ route('dashboard.team.members.update', $member) }}">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role" onchange="this.form.submit()" class="select h-8 w-32 py-0 text-xs">
                                            @foreach ($roles as $role)
                                                <option value="{{ $role }}" @selected($member->role === $role)>{{ __('ui.roles.'.$role) }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @else
                                    <span class="badge {{ $member->isOwner() ? 'badge-lime' : 'badge-gray' }}">{{ __('ui.roles.'.$member->role) }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-xs text-ink-500">{{ $member->joined_at?->translatedFormat('d M Y') ?? '—' }}</td>
                            <td class="whitespace-nowrap text-xs text-ink-400">{{ $member->user?->last_login_at?->diffForHumans() ?? __('ui.common.never') }}</td>
                            <td class="text-end">
                                @if ($canManage && ! $member->isOwner() && $member->user_id !== auth()->id())
                                    <form
                                        method="POST"
                                        action="{{ route('dashboard.team.members.destroy', $member) }}"
                                        x-data
                                        @submit="if (! confirm(@js(__('ui.team.confirm_remove')))) { $event.preventDefault(); }"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm text-red-600 hover:bg-red-50">{{ __('ui.team.remove') }}</button>
                                    </form>
                                @elseif ($member->isOwner())
                                    <span class="text-[10px] text-ink-300">{{ __('ui.team.owner') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-[1.25fr_1fr]">
        @if ($canManage)
            <div id="invite" class="card card-pad scroll-mt-24">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-lime/20 text-ink-950"><x-icon name="users" class="size-5" /></span>
                    <div>
                        <h2 class="font-semibold">{{ __('ui.team.invite_title') }}</h2>
                        <p class="mt-1 text-xs leading-6 text-ink-400">{{ __('ui.team.subtitle') }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('dashboard.team.invite') }}" class="mt-5 grid gap-4 sm:grid-cols-[1fr_160px_auto] sm:items-end">
                    @csrf
                    <div>
                        <label class="label" for="invite-email">{{ __('ui.team.email') }}</label>
                        <input id="invite-email" type="email" name="email" value="{{ old('email') }}" required maxlength="190" class="input h-10 @error('email') input-error @enderror" dir="ltr" placeholder="teammate@company.com">
                        @error('email')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="invite-role">{{ __('ui.team.role') }}</label>
                        <select id="invite-role" name="role" class="select h-10">
                            @foreach ($roles as $role)
                                <option value="{{ $role }}" @selected(old('role', 'member') === $role)>{{ __('ui.roles.'.$role) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary h-10 px-5">{{ __('ui.team.send_invite') }}</button>
                </form>
            </div>
        @endif

        <div class="card overflow-hidden {{ ! $canManage ? 'xl:col-span-2' : '' }}">
            <div class="border-b border-ink-950/10 p-5">
                <h2 class="font-semibold">{{ __('ui.team.pending') }}</h2>
                <p class="mt-0.5 text-xs text-ink-400">{{ __('ui.team.no_pending') }}</p>
            </div>

            @if ($invitations->isEmpty())
                <p class="p-8 text-center text-xs text-ink-400">{{ __('ui.team.no_pending') }}</p>
            @else
                <div class="divide-y divide-ink-950/[0.06]">
                    @foreach ($invitations as $invitation)
                        <div class="flex items-center gap-3 p-4">
                            <span class="grid size-9 shrink-0 place-items-center rounded-full bg-paper text-ink-400"><x-icon name="mail" class="size-4" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-mono text-xs font-medium" dir="ltr">{{ $invitation->email }}</p>
                                <p class="text-[10px] text-ink-400">{{ __('ui.roles.'.$invitation->role) }} · {{ __('ui.team.expires') }} {{ $invitation->expires_at?->translatedFormat('d M') }}</p>
                            </div>
                            <span class="badge badge-amber">{{ __('ui.statuses.pending') }}</span>
                            @if ($canManage)
                                <form
                                    method="POST"
                                    action="{{ route('dashboard.team.invitations.destroy', $invitation) }}"
                                    x-data
                                    @submit="if (! confirm(@js(__('ui.common.confirm')))) { $event.preventDefault(); }"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-sm text-red-600"><x-icon name="x" class="size-3.5" /></button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="rounded-2xl border border-ink-950/10 bg-white p-5">
        <h2 class="text-sm font-semibold">{{ __('ui.team.role') }}</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['admin', 'shield'],
                ['developer', 'code'],
                ['billing', 'card'],
                ['member', 'user'],
            ] as [$role, $icon])
                <div class="flex items-start gap-2.5">
                    <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-lg bg-paper text-ink-500"><x-icon :name="$icon" class="size-3.5" /></span>
                    <div>
                        <p class="text-xs font-semibold">{{ __('ui.roles.'.$role) }}</p>
                        <p class="mt-0.5 text-[10px] leading-4 text-ink-400">{{ __('ui.team.role_'.$role) }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
