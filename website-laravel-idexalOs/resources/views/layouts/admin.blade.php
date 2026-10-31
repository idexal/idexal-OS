@php
    $adminUser = auth()->user();
    $adminOpenTickets = \App\Models\SupportTicket::query()->whereIn('status', [\App\Models\SupportTicket::STATUS_OPEN, \App\Models\SupportTicket::STATUS_PENDING])->count();
    $adminInitial = mb_strtoupper(mb_substr((string) ($adminUser?->name ?: 'A'), 0, 1));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ locale_direction() }}">
<head>
    @include('partials.head')
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-dvh bg-paper text-ink-950">
    <div x-data="{ sidebar: false, userMenu: false }">
        <aside class="fixed inset-y-0 start-0 z-40 hidden w-72 flex-col bg-ink-950 text-paper lg:flex">
            <div class="flex h-[72px] items-center justify-between border-b border-white/10 px-5">
                <a href="{{ route('admin.index') }}" class="flex items-center">
                    <img src="{{ asset('brand/logo-dark.png') }}" alt="{{ config('idexal.brand.name') }}" class="h-9 w-auto">
                </a>
                <span class="badge border-lime/20 bg-lime/15 text-lime">{{ __('ui.nav.admin') }}</span>
            </div>

            @include('partials.admin-nav')

            <div class="border-t border-white/10 p-4">
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <div class="flex items-center gap-2">
                        <span class="size-2 rounded-full bg-lime"></span>
                        <span class="text-[11px] font-semibold text-paper/70">{{ __('ui.common.online') }}</span>
                    </div>
                    <p class="mt-2 text-[11px] leading-5 text-paper/35">{{ __('ui.admin.subtitle') }}</p>
                </div>
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
                @include('partials.admin-nav')
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
                            <span class="text-ink-400">{{ __('ui.nav.admin') }}</span>
                            <x-icon name="chevron-left" class="size-3.5 text-ink-300 rtl:rotate-180" />
                            <span class="truncate font-semibold text-ink-950">@yield('page', __('ui.admin.nav.dashboard'))</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <a href="{{ route('home') }}" class="btn-icon" aria-label="{{ __('ui.nav.product') }}">
                            <x-icon name="external" />
                        </a>
                        <a href="{{ route('admin.support.index') }}" class="btn-icon relative" aria-label="{{ __('ui.admin.nav.support') }}">
                            <x-icon name="message" />
                            @if ($adminOpenTickets > 0)
                                <span class="absolute -end-1 -top-1 grid min-w-4 place-items-center rounded-full bg-lime px-1 font-mono text-[9px] font-semibold leading-4 text-ink-950">{{ $adminOpenTickets > 9 ? '9+' : $adminOpenTickets }}</span>
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
                                <span class="grid size-7 place-items-center rounded-lg bg-ink-950 font-mono text-[11px] font-semibold text-lime">{{ $adminInitial }}</span>
                                <span class="hidden max-w-28 truncate text-xs font-semibold sm:block">{{ $adminUser?->name }}</span>
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
                                    <p class="truncate text-sm font-semibold">{{ $adminUser?->name }}</p>
                                    <p class="truncate text-xs text-ink-400" dir="ltr">{{ $adminUser?->email }}</p>
                                    <span class="badge badge-lime mt-2">{{ __('ui.roles.admin') }}</span>
                                </div>
                                <div class="p-2">
                                    <a href="{{ route('dashboard.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-ink-600 hover:bg-paper">
                                        <x-icon name="grid" class="size-4" />
                                        {{ __('ui.admin.nav.back') }}
                                    </a>
                                    <a href="{{ route('admin.settings') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-ink-600 hover:bg-paper">
                                        <x-icon name="settings" class="size-4" />
                                        {{ __('ui.admin.nav.settings') }}
                                    </a>
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
                <div class="mx-auto w-full max-w-[1500px] space-y-6">
                    @include('partials.flash')
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
