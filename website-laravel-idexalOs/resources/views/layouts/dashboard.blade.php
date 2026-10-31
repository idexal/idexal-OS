@php
    $layoutUser = auth()->user();
    $layoutContext = app(\App\Services\OrganizationContext::class);
    $layoutOrg = $layoutContext->organization();
    $layoutMembership = $layoutContext->membership();
    $layoutOrganizations = $layoutUser
        ? $layoutUser->memberships()
            ->with('organization')
            ->where('status', \App\Models\Membership::STATUS_ACTIVE)
            ->get()
            ->map(fn ($membership) => $membership->organization)
            ->filter(fn ($organization) => $organization instanceof \App\Models\Organization && ! $organization->isSuspended())
            ->values()
        : collect();
    $layoutUnread = $layoutUser ? $layoutUser->appNotifications()->whereNull('read_at')->count() : 0;
    $layoutPlan = $layoutOrg?->currentPlan();
    $layoutInitial = mb_strtoupper(mb_substr((string) ($layoutUser?->name ?: 'U'), 0, 1));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ locale_direction() }}">
<head>
    @include('partials.head')
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-dvh bg-paper text-ink-950">
    <div x-data="{ sidebar: false, userMenu: false, createOrg: false }">
        <aside class="fixed inset-y-0 start-0 z-40 hidden w-72 flex-col bg-ink-950 text-paper lg:flex">
            <div class="flex h-[72px] items-center justify-between border-b border-white/10 px-5">
                <a href="{{ route('dashboard.index') }}" class="flex items-center">
                    <img src="{{ asset('brand/logo-dark.png') }}" alt="{{ config('idexal.brand.name') }}" class="h-9 w-auto">
                </a>
                <span class="badge border-white/10 bg-white/5 text-paper/60">{{ __('ui.dashboard.title') }}</span>
            </div>

            <div class="border-b border-white/10 px-3 py-4">
                @if ($layoutOrganizations->count() > 1)
                    <form method="POST" action="{{ route('dashboard.organization.switch') }}">
                        @csrf
                        <label class="mb-1.5 block px-2 text-[10px] font-semibold uppercase tracking-[0.16em] text-paper/35">{{ __('ui.dashboard.org_switcher') }}</label>
                        <div class="relative">
                            <select
                                name="organization_id"
                                onchange="this.form.submit()"
                                class="w-full appearance-none rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5 pe-9 text-sm font-medium text-paper outline-none transition hover:bg-white/[0.07] focus:border-lime"
                            >
                                @foreach ($layoutOrganizations as $organization)
                                    <option value="{{ $organization->getKey() }}" @selected($layoutOrg?->getKey() === $organization->getKey()) class="bg-ink-900 text-paper">
                                        {{ $organization->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-icon name="chevron-down" class="pointer-events-none absolute end-3 top-1/2 size-4 -translate-y-1/2 text-paper/40" />
                        </div>
                    </form>
                @else
                    <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5">
                        <span class="grid size-8 place-items-center rounded-lg bg-lime font-mono text-xs font-semibold text-ink-950">{{ mb_substr((string) $layoutOrg?->name, 0, 1) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">{{ $layoutOrg?->name }}</p>
                            <p class="truncate font-mono text-[10px] text-paper/40" dir="ltr">{{ $layoutOrg?->slug }}</p>
                        </div>
                    </div>
                @endif

                <button type="button" @click="createOrg = true" class="mt-2 flex w-full items-center gap-2 rounded-lg px-2 py-2 text-xs font-medium text-paper/40 transition hover:bg-white/[0.06] hover:text-paper">
                    <x-icon name="plus" class="size-3.5" />
                    {{ __('ui.dashboard.switch_org') }}
                </button>
            </div>

            @include('partials.dashboard-nav')

            <div class="border-t border-white/10 p-3">
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[11px] font-medium text-paper/45">{{ __('ui.dashboard.current_plan') }}</span>
                        <span class="badge border-lime/20 bg-lime/15 text-lime">{{ $layoutPlan?->name ?? __('ui.billing.free') }}</span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <span class="text-[11px] text-paper/40">{{ __('ui.common.credits') }}</span>
                        <span class="font-mono text-sm text-paper">{{ money($layoutOrg?->credit_balance ?? 0, $layoutOrg?->currency) }}</span>
                    </div>
                    <a href="{{ route('dashboard.billing') }}" class="mt-3 flex items-center justify-between text-[11px] font-semibold text-lime transition hover:text-lime-bright">
                        {{ __('ui.dashboard.manage_plan') }}
                        <x-icon name="arrow-left" class="size-3.5 rtl:rotate-180" />
                    </a>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-paper/40 transition hover:bg-white/[0.06] hover:text-paper">
                        <x-icon name="logout" class="size-4" />
                        {{ __('ui.nav.logout') }}
                    </button>
                </form>
            </div>
        </aside>

        <div x-show="sidebar" x-cloak class="fixed inset-0 z-50 lg:hidden">
            <div class="absolute inset-0 bg-ink-950/60 backdrop-blur-sm" @click="sidebar = false"></div>
            <aside
                x-show="sidebar"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="-translate-x-full rtl:translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full rtl:translate-x-full"
                class="absolute inset-y-0 start-0 flex w-72 flex-col bg-ink-950 text-paper"
            >
                <div class="flex h-[72px] items-center justify-between border-b border-white/10 px-4">
                    <img src="{{ asset('brand/logo-dark.png') }}" alt="{{ config('idexal.brand.name') }}" class="h-9 w-auto">
                    <button type="button" class="btn-icon border-white/10 bg-white/5 text-paper" @click="sidebar = false" aria-label="{{ __('ui.common.close') }}">
                        <x-icon name="x" />
                    </button>
                </div>
                <div class="border-b border-white/10 p-3">
                    <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5">
                        <span class="grid size-8 place-items-center rounded-lg bg-lime font-mono text-xs font-semibold text-ink-950">{{ mb_substr((string) $layoutOrg?->name, 0, 1) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">{{ $layoutOrg?->name }}</p>
                            <p class="truncate font-mono text-[10px] text-paper/40" dir="ltr">{{ $layoutOrg?->slug }}</p>
                        </div>
                    </div>
                </div>
                @include('partials.dashboard-nav')
            </aside>
        </div>

        <div class="lg:ps-72">
            <header class="sticky top-0 z-30 border-b border-ink-950/10 bg-paper/85 backdrop-blur-xl">
                <div class="flex h-[72px] items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" class="btn-icon lg:hidden" @click="sidebar = true" aria-label="{{ __('ui.common.manage') }}">
                            <x-icon name="menu" />
                        </button>
                        <div class="hidden min-w-0 items-center gap-2 text-sm sm:flex">
                            <span class="text-ink-400">{{ __('ui.dashboard.title') }}</span>
                            <x-icon name="chevron-left" class="size-3.5 text-ink-300 rtl:rotate-180" />
                            <span class="truncate font-semibold text-ink-950">@yield('page', __('ui.dashboard.nav.overview'))</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <a href="{{ route('docs') }}" class="btn-icon hidden sm:inline-flex" aria-label="{{ __('ui.dashboard.nav.docs') }}">
                            <x-icon name="book" />
                        </a>
                        <a href="{{ route('dashboard.notifications') }}" class="btn-icon relative" aria-label="{{ __('ui.dashboard.nav.notifications') }}">
                            <x-icon name="bell" />
                            @if ($layoutUnread > 0)
                                <span class="absolute -end-1 -top-1 grid min-w-4 place-items-center rounded-full bg-lime px-1 font-mono text-[9px] font-semibold leading-4 text-ink-950">{{ $layoutUnread > 9 ? '9+' : $layoutUnread }}</span>
                            @endif
                        </a>
                        <a href="{{ route('locale.update', app()->getLocale() === 'ar' ? 'en' : 'ar') }}" class="btn-icon" aria-label="{{ __('ui.nav.language') }}">
                            <x-icon name="globe" />
                        </a>

                        <div class="relative ms-1">
                            <button
                                type="button"
                                @click="userMenu = !userMenu"
                                class="flex items-center gap-2 rounded-xl border border-ink-950/10 bg-white py-1.5 pe-3 ps-1.5 transition hover:border-ink-950/25"
                                :aria-expanded="userMenu"
                            >
                                <span class="grid size-7 place-items-center rounded-lg bg-ink-950 font-mono text-[11px] font-semibold text-lime">{{ $layoutInitial }}</span>
                                <span class="hidden max-w-28 truncate text-xs font-semibold sm:block">{{ $layoutUser?->name }}</span>
                                <x-icon name="chevron-down" class="size-3.5 text-ink-400" />
                            </button>

                            <div
                                x-show="userMenu"
                                x-cloak
                                @click.outside="userMenu = false"
                                x-transition
                                class="absolute end-0 top-full z-50 mt-2 w-60 overflow-hidden rounded-2xl border border-ink-950/10 bg-white shadow-float"
                            >
                                <div class="border-b border-ink-950/10 p-4">
                                    <p class="truncate text-sm font-semibold">{{ $layoutUser?->name }}</p>
                                    <p class="truncate text-xs text-ink-400" dir="ltr">{{ $layoutUser?->email }}</p>
                                    <div class="mt-2 flex items-center gap-1.5">
                                        <x-status :status="$layoutMembership?->role === 'owner' ? 'active' : 'active'" class="badge-gray" />
                                        <span class="text-[11px] text-ink-500">{{ __('ui.roles.'.($layoutMembership?->role ?? 'member')) }}</span>
                                    </div>
                                </div>
                                <div class="p-2">
                                    <a href="{{ route('dashboard.profile') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-ink-600 hover:bg-paper">
                                        <x-icon name="user" class="size-4" />
                                        {{ __('ui.dashboard.nav.profile') }}
                                    </a>
                                    <a href="{{ route('dashboard.settings') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-ink-600 hover:bg-paper">
                                        <x-icon name="settings" class="size-4" />
                                        {{ __('ui.dashboard.nav.settings') }}
                                    </a>
                                    @if ($layoutUser?->isAdmin())
                                        <a href="{{ route('admin.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-ink-600 hover:bg-paper">
                                            <x-icon name="shield" class="size-4" />
                                            {{ __('ui.nav.admin') }}
                                        </a>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('logout') }}" class="border-t border-ink-950/10 p-2">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50">
                                        <x-icon name="logout" class="size-4" />
                                        {{ __('ui.nav.logout') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <div class="mx-auto w-full max-w-[1400px] space-y-6">
                    @include('partials.flash')
                    @yield('content')
                </div>
            </main>
        </div>

        <div x-show="createOrg" x-cloak class="fixed inset-0 z-[60] grid place-items-center p-4">
            <div class="absolute inset-0 bg-ink-950/60 backdrop-blur-sm" @click="createOrg = false"></div>
            <div x-show="createOrg" x-transition class="relative w-full max-w-md rounded-3xl border border-ink-950/10 bg-white p-6 shadow-float">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ __('ui.dashboard.switch_org') }}</h2>
                        <p class="mt-1 text-sm text-ink-500">{{ __('ui.landing.features.teams.body') }}</p>
                    </div>
                    <button type="button" class="btn-icon" @click="createOrg = false" aria-label="{{ __('ui.common.close') }}">
                        <x-icon name="x" />
                    </button>
                </div>
                <form method="POST" action="{{ route('dashboard.organization.store') }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label class="label" for="new-org-name">{{ __('ui.common.name') }}</label>
                        <input id="new-org-name" type="text" name="name" required maxlength="120" class="input" placeholder="{{ __('ui.team.title') }}">
                    </div>
                    <button type="submit" class="btn btn-primary w-full">{{ __('ui.common.create') }}</button>
                </form>
            </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
