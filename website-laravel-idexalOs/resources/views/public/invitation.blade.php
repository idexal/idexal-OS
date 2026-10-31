@extends('layouts.public')

@section('title', __('ui.team.invitation_title').' — '.config('idexal.brand.name'))

@section('content')
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 grid-lines opacity-50 [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>
        <div class="container-narrow relative py-16 lg:py-24">
            <div class="card overflow-hidden">
                <div class="mesh-dark px-6 py-10 text-center text-paper sm:px-10">
                    <img src="{{ asset('brand/logo-dark.png') }}" alt="{{ config('idexal.brand.name') }}" class="mx-auto h-9 w-auto">
                    <h1 class="mt-6 text-balance text-2xl font-semibold sm:text-3xl">{{ __('ui.team.invitation_title') }}</h1>
                    <p class="mt-2 text-sm text-paper/50">{{ $invitation->organization?->name }}</p>
                </div>

                <div class="p-6 sm:p-10">
                    @if ($state === 'accepted')
                        <div class="text-center">
                            <span class="mx-auto grid size-14 place-items-center rounded-full bg-emerald-50 text-emerald-600">
                                <x-icon name="check-circle" class="size-7" />
                            </span>
                            <h2 class="mt-5 text-lg font-semibold">{{ __('ui.team.already_accepted') }}</h2>
                            <a href="{{ route('dashboard.index') }}" class="btn btn-primary mt-6">{{ __('ui.nav.dashboard') }}</a>
                        </div>
                    @elseif ($state === 'expired')
                        <div class="text-center">
                            <span class="mx-auto grid size-14 place-items-center rounded-full bg-red-50 text-red-600">
                                <x-icon name="clock" class="size-7" />
                            </span>
                            <h2 class="mt-5 text-lg font-semibold">{{ __('ui.team.expired') }}</h2>
                            <p class="mt-2 text-sm text-ink-500">{{ __('ui.contact.support') }}</p>
                            <a href="{{ route('contact') }}" class="btn btn-outline mt-6">{{ __('ui.nav.contact') }}</a>
                        </div>
                    @else
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="rounded-2xl border border-ink-950/10 bg-paper/60 p-4">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __('ui.team.invited_by') }}</p>
                                <p class="mt-2 text-sm font-semibold">{{ $invitation->inviter?->name ?? '—' }}</p>
                            </div>
                            <div class="rounded-2xl border border-ink-950/10 bg-paper/60 p-4">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __('ui.common.role') }}</p>
                                <p class="mt-2 text-sm font-semibold">{{ __('ui.roles.'.$invitation->role) }}</p>
                            </div>
                        </div>

                        <div class="mt-5 rounded-2xl border border-ink-950/10 p-4 text-sm">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __('ui.team.invited_email') }}</p>
                            <p class="mt-2 font-mono text-sm" dir="ltr">{{ $invitation->email }}</p>
                        </div>

                        <div class="mt-6">
                            @auth
                                @if (strcasecmp(auth()->user()->email, $invitation->email) === 0)
                                    <form method="POST" action="{{ route('invitations.accept', $token) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary h-12 w-full">
                                            {{ __('ui.team.accept_invitation') }}
                                            <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
                                        </button>
                                    </form>
                                @else
                                    <div class="alert alert-warning">
                                        <x-icon name="alert" class="mt-0.5 size-4" />
                                        <p class="text-sm">{{ __('ui.team.wrong_email') }}</p>
                                    </div>
                                    <form method="POST" action="{{ route('logout') }}" class="mt-4">
                                        @csrf
                                        <button type="submit" class="btn btn-outline w-full">{{ __('ui.auth.logout') }}</button>
                                    </form>
                                @endif
                            @else
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <a href="{{ route('login') }}" class="btn btn-outline h-12">{{ __('ui.nav.login') }}</a>
                                    <a href="{{ route('register') }}" class="btn btn-primary h-12">{{ __('ui.auth.create_account') }}</a>
                                </div>
                                <p class="mt-4 text-center text-xs leading-6 text-ink-400">{{ __('ui.team.invitation_note') }}</p>
                            @endauth
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
