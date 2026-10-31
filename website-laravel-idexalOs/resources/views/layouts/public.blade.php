<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ locale_direction() }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-dvh bg-paper text-ink-950">
    <a
        href="#main"
        class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60] focus:rounded-full focus:bg-ink-950 focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-paper"
    >{{ __('ui.common.skip_to_content') }}</a>

    <header
        class="sticky top-0 z-50 border-b border-ink-950/10 bg-paper/85 backdrop-blur-xl"
        x-data="{ open: false }"
    >
        @php
            $navProducts = __('ui.products.items');
            $navUseCases = __('ui.use_cases.items');
        @endphp
        <div class="container-x flex h-[72px] items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-4 xl:gap-8">
                <a href="{{ localized_route('home') }}" class="flex shrink-0 items-center gap-2.5" aria-label="{{ config('idexal.brand.name') }}">
                    <img src="{{ asset('brand/logo-light.png') }}" alt="{{ config('idexal.brand.name') }}" class="h-9 w-auto xl:h-10">
                </a>

                {{-- Six sections plus the account actions do not fit between
                     1024px and ~1350px, so the menu collapses one breakpoint
                     later than the old layout did. The nav itself never wraps:
                     a wrapped row detached the chevrons from their labels. --}}
                @php
                    $productIcons = ['desktop' => 'monitor', 'web' => 'globe', 'gateway' => 'zap', 'mcp' => 'server', 'den' => 'building', 'server' => 'database', 'sdk' => 'code', 'extensions' => 'layers'];
                    $useCaseIcons = ['engineering' => 'code', 'operations' => 'layers', 'finance' => 'card', 'marketing' => 'sparkles', 'support' => 'message', 'it' => 'server', 'answer-from-knowledge' => 'book', 'automate-recurring' => 'refresh', 'across-documents' => 'database', 'own-a-tool' => 'terminal'];
                    $resourceIcons = ['developers' => 'terminal', 'docs' => 'book', 'providers' => 'cpu', 'integrations' => 'layers', 'changelog' => 'clock', 'security' => 'shield', 'pricing' => 'card', 'enterprise' => 'building', 'download' => 'download', 'contact' => 'mail'];
                @endphp

                <nav class="hidden flex-nowrap items-center gap-0.5 xl:flex" aria-label="{{ __('ui.nav.primary') }}">
                    <x-nav-menu :label="__('ui.nav.products')" :href="localized_route('products')" :active="request()->routeIs('products', 'products.show')" panel="w-[47rem]">
                        <div class="grid gap-2 lg:grid-cols-[1fr_1fr_13.5rem]">
                            <div class="grid gap-0.5 lg:col-span-2 lg:grid-cols-2">
                                @foreach ($navProducts as $slug => $product)
                                    <a href="{{ localized_route('products.show', ['slug' => $slug]) }}" class="group flex items-start gap-3 rounded-2xl p-2.5 transition hover:bg-paper-2">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-paper-2 text-ink-600 transition group-hover:bg-lime/30 group-hover:text-ink-950">
                                            <x-icon :name="$productIcons[$slug] ?? 'grid'" class="size-4" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block truncate text-[13px] font-semibold text-ink-950">{{ $product['name'] }}</span>
                                            <span class="mt-0.5 block text-[11px] leading-4 text-ink-500">{{ $product['tagline'] }}</span>
                                        </span>
                                    </a>
                                @endforeach
                            </div>

                            <a href="{{ localized_route('register') }}" class="group relative flex flex-col justify-between overflow-hidden rounded-2xl bg-ink-950 p-4 text-paper">
                                <div class="pointer-events-none absolute -end-6 -top-8 size-28 rounded-full bg-lime/20 blur-2xl"></div>
                                <div class="relative">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-paper/60">
                                        <span class="size-1 rounded-full bg-lime"></span>
                                        {{ __('ui.products.eyebrow') }}
                                    </span>
                                    <p class="mt-3 text-sm font-semibold leading-5">{{ __('ui.products.title') }}</p>
                                    <p class="mt-1.5 text-[11px] leading-4 text-paper/55">{{ __('ui.nav.products_rail') }}</p>
                                </div>
                                <span class="relative mt-4 inline-flex items-center gap-1.5 text-[11px] font-semibold text-lime">
                                    {{ __('ui.nav.register') }}
                                    <x-icon name="arrow-right" class="size-3.5 transition group-hover:translate-x-0.5 rtl:rotate-180" />
                                </span>
                            </a>
                        </div>
                        <div class="mt-2 flex items-center justify-between border-t border-ink-950/5 px-2 pt-2.5">
                            <a href="{{ localized_route('products') }}" class="text-[11px] font-semibold text-ink-950 hover:underline">{{ __('ui.products.all') }}</a>
                            <a href="{{ localized_route('pricing') }}" class="inline-flex items-center gap-1 text-[11px] font-medium text-ink-500 hover:text-ink-950">
                                {{ __('ui.nav.pricing') }}
                                <x-icon name="arrow-right" class="size-3 rtl:rotate-180" />
                            </a>
                        </div>
                    </x-nav-menu>

                    <x-nav-menu :label="__('ui.nav.use_cases')" :href="localized_route('use-cases')" :active="request()->routeIs('use-cases', 'use-cases.show')" panel="w-[43rem]">
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach (['roles', 'tasks'] as $group)
                                <div class="min-w-0">
                                    <p class="px-2.5 pb-1 pt-1.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __("ui.use_cases.{$group}") }}</p>
                                    @foreach ($navUseCases as $slug => $useCase)
                                        @if (($useCase['group'] ?? 'roles') === $group)
                                            <a href="{{ localized_route('use-cases.show', ['slug' => $slug]) }}" class="group flex items-start gap-3 rounded-2xl p-2.5 transition hover:bg-paper-2">
                                                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-paper-2 text-ink-600 transition group-hover:bg-lime/30 group-hover:text-ink-950">
                                                    <x-icon :name="$useCaseIcons[$slug] ?? 'users'" class="size-3.5" />
                                                </span>
                                                <span class="min-w-0">
                                                    <span class="block text-[13px] font-semibold leading-5 text-ink-950">{{ $useCase['role'] }}</span>
                                                    <span class="mt-0.5 line-clamp-2 block text-[11px] leading-4 text-ink-500">{{ $useCase['tagline'] }}</span>
                                                </span>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-2 flex items-center justify-between border-t border-ink-950/5 px-2 pt-2.5">
                            <a href="{{ localized_route('use-cases') }}" class="text-[11px] font-semibold text-ink-950 hover:underline">{{ __('ui.use_cases.all') }}</a>
                            <a href="{{ localized_route('integrations') }}" class="inline-flex items-center gap-1 text-[11px] font-medium text-ink-500 hover:text-ink-950">
                                {{ __('ui.nav.integrations') }}
                                <x-icon name="arrow-right" class="size-3 rtl:rotate-180" />
                            </a>
                        </div>
                    </x-nav-menu>

                    <a href="{{ localized_route('pricing') }}" class="nav-link {{ request()->routeIs('pricing') ? 'nav-link-active' : '' }}" @if (request()->routeIs('pricing')) aria-current="page" @endif>{{ __('ui.nav.pricing') }}</a>
                    <a href="{{ localized_route('enterprise') }}" class="nav-link {{ request()->routeIs('enterprise') ? 'nav-link-active' : '' }}" @if (request()->routeIs('enterprise')) aria-current="page" @endif>{{ __('ui.nav.enterprise') }}</a>

                    <x-nav-menu :label="__('ui.nav.resources')" :href="localized_route('resources')" :active="request()->routeIs('resources', 'providers', 'security', 'changelog', 'contact', 'developers', 'integrations', 'docs')" align="end" panel="w-[47rem]">
                        <div class="grid gap-0.5 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach (__('ui.resources.cards') as $card)
                                <a href="{{ localized_route($card['route']) }}" class="group flex flex-col rounded-2xl p-2.5 transition hover:bg-paper-2">
                                    <span class="inline-flex items-center gap-2">
                                        <span class="grid size-7 shrink-0 place-items-center rounded-lg bg-paper-2 text-ink-600 transition group-hover:bg-lime/30 group-hover:text-ink-950">
                                            <x-icon :name="$resourceIcons[$card['route']] ?? 'layers'" class="size-3.5" />
                                        </span>
                                        <span class="text-[12.5px] font-semibold text-ink-950">{{ $card['title'] }}</span>
                                    </span>
                                    <span class="mt-1.5 line-clamp-2 text-[11px] leading-4 text-ink-500">{{ $card['body'] }}</span>
                                </a>
                            @endforeach
                        </div>
                        <div class="mt-2 flex items-center justify-between border-t border-ink-950/5 px-2 pt-2.5">
                            <a href="{{ localized_route('resources') }}" class="text-[11px] font-semibold text-ink-950 hover:underline">{{ __('ui.resources.eyebrow') }}</a>
                            <a href="{{ config('idexal.brand.github') }}" target="_blank" rel="noreferrer" class="inline-flex items-center gap-1 text-[11px] font-medium text-ink-500 hover:text-ink-950">
                                {{ __('ui.resources.open_source_cta') }}
                                <x-icon name="external" class="size-3" />
                            </a>
                        </div>
                    </x-nav-menu>
                    <a href="{{ localized_route('download') }}" class="nav-link {{ request()->routeIs('download') ? 'nav-link-active' : '' }}" @if (request()->routeIs('download')) aria-current="page" @endif>{{ __('ui.nav.download') }}</a>
                </nav>
            </div>

            <div class="hidden shrink-0 items-center gap-2 xl:flex">
                {{-- The language lives in the URL, so a visitor can copy or share
                     the page and the recipient keeps the language. --}}
                <a
                    href="{{ locale_url(app()->getLocale() === 'ar' ? 'en' : 'ar') }}"
                    hreflang="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}"
                    lang="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}"
                    class="btn btn-ghost btn-sm gap-1.5"
                    aria-label="{{ __('ui.nav.language') }}"
                >
                    <x-icon name="globe" class="size-4" />
                    {{ app()->getLocale() === 'ar' ? __('ui.nav.english') : __('ui.nav.arabic') }}
                </a>

                @auth
                    @if (auth()->user()->isAdmin())
                        <a href="{{ localized_route('admin.index') }}" class="btn btn-outline btn-sm">{{ __('ui.nav.admin') }}</a>
                    @endif
                    <a href="{{ localized_route('dashboard.index') }}" class="btn btn-primary btn-sm">
                        {{ __('ui.nav.dashboard') }}
                        <x-icon name="arrow-up-right" class="size-4" />
                    </a>
                @else
                    <a href="{{ localized_route('login') }}" class="btn btn-ghost btn-sm">{{ __('ui.nav.login') }}</a>
                    <a href="{{ localized_route('register') }}" class="btn btn-primary btn-sm">{{ __('ui.nav.register') }}</a>
                @endauth
            </div>

            {{-- Below the full menu the account actions collapse into the
                 drawer, but the primary call to action stays visible: a
                 tablet visitor should not have to open a menu to sign up. --}}
            @guest
                <a href="{{ localized_route('register') }}" class="btn btn-primary btn-sm ms-auto hidden shrink-0 sm:inline-flex xl:hidden">
                    {{ __('ui.nav.register') }}
                </a>
            @endguest

            <button
                type="button"
                class="btn-icon xl:hidden"
                aria-controls="site-menu"
                @click="open = !open"
                :aria-expanded="open"
                aria-label="{{ __('ui.common.manage') }}"
            >
                <x-icon name="menu" x-show="!open" />
                <x-icon name="x" x-show="open" x-cloak />
            </button>
        </div>

        <div id="site-menu" x-show="open" x-cloak x-transition.opacity class="border-t border-ink-950/10 bg-paper xl:hidden">
            <nav class="container-x flex flex-col gap-1 py-4" aria-label="{{ __('ui.nav.primary') }}">
                <p class="px-3 pt-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __('ui.nav.products') }}</p>
                @foreach ($navProducts as $slug => $product)
                    <a href="{{ localized_route('products.show', ['slug' => $slug]) }}" class="nav-link ps-5" @click="open = false">{{ $product['name'] }}</a>
                @endforeach
                <a href="{{ localized_route('products') }}" class="nav-link" @click="open = false">{{ __('ui.products.all') }}</a>
                <p class="px-3 pt-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __('ui.nav.use_cases') }}</p>
                @foreach ($navUseCases as $slug => $useCase)
                    <a href="{{ localized_route('use-cases.show', ['slug' => $slug]) }}" class="nav-link ps-5" @click="open = false">{{ $useCase['role'] }}</a>
                @endforeach
                <a href="{{ localized_route('use-cases') }}" class="nav-link" @click="open = false">{{ __('ui.use_cases.all') }}</a>
                <div class="my-2 divider"></div>
                <a href="{{ localized_route('pricing') }}" class="nav-link" @click="open = false">{{ __('ui.nav.pricing') }}</a>
                <a href="{{ localized_route('enterprise') }}" class="nav-link" @click="open = false">{{ __('ui.nav.enterprise') }}</a>
                <a href="{{ localized_route('resources') }}" class="nav-link" @click="open = false">{{ __('ui.nav.resources') }}</a>
                @foreach (__('ui.resources.cards') as $card)
                    <a href="{{ localized_route($card['route']) }}" class="nav-link ps-5" @click="open = false">{{ $card['title'] }}</a>
                @endforeach
                <a href="{{ localized_route('download') }}" class="nav-link" @click="open = false">{{ __('ui.nav.download') }}</a>
                <a href="{{ config('idexal.brand.github') }}" target="_blank" rel="noreferrer" class="nav-link" @click="open = false">{{ __('ui.resources.open_source_cta') }}</a>

                <div class="my-2 divider"></div>

                <a
                    href="{{ locale_url(app()->getLocale() === 'ar' ? 'en' : 'ar') }}"
                    hreflang="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}"
                    lang="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}"
                    class="nav-link"
                    @click="open = false"
                >
                    {{ app()->getLocale() === 'ar' ? __('ui.nav.english') : __('ui.nav.arabic') }}
                </a>

                @auth
                    <a href="{{ localized_route('dashboard.index') }}" class="btn btn-primary mt-2">{{ __('ui.nav.dashboard') }}</a>
                @else
                    <a href="{{ localized_route('login') }}" class="btn btn-outline mt-2">{{ __('ui.nav.login') }}</a>
                    <a href="{{ localized_route('register') }}" class="btn btn-primary">{{ __('ui.nav.register') }}</a>
                @endauth
            </nav>
        </div>
    </header>

    <main id="main" tabindex="-1" class="outline-none">
        <div class="container-x pt-6 empty:hidden">
            @include('partials.flash')
        </div>
        @yield('content')
    </main>

    <footer class="mt-24 bg-ink-950 text-paper">
        <div class="container-x py-14">
            <div class="grid gap-12 lg:grid-cols-[1.3fr_2fr]">
                <div class="min-w-0">
                    <img src="{{ asset('brand/logo-dark.png') }}" alt="{{ config('idexal.brand.name') }}" class="h-11 w-auto">
                    <p class="mt-5 max-w-sm text-sm leading-7 text-paper/55">{{ __('ui.landing.footer_note') }}</p>
                    <div class="mt-6 flex items-center gap-2">
                        <a href="{{ config('idexal.brand.github') }}" target="_blank" rel="noreferrer" class="btn-icon border-white/10 bg-white/5 text-paper/70 hover:border-white/25 hover:text-paper" aria-label="GitHub">
                            <svg viewBox="0 0 24 24" class="size-5" fill="currentColor" aria-hidden="true"><path d="M12 .7a11.3 11.3 0 0 0-3.6 22c.6.1.8-.2.8-.5v-2c-3.2.7-3.9-1.4-3.9-1.4-.5-1.3-1.3-1.7-1.3-1.7-1-.7.1-.7.1-.7 1.2.1 1.8 1.2 1.8 1.2 1 1.8 2.8 1.3 3.4 1 .1-.8.4-1.3.7-1.6-2.6-.3-5.3-1.3-5.3-5.8 0-1.3.5-2.3 1.2-3.1-.1-.3-.5-1.5.1-3.1 0 0 1-.3 3.2 1.2a11 11 0 0 1 5.8 0c2.2-1.5 3.2-1.2 3.2-1.2.6 1.6.2 2.8.1 3.1.8.8 1.2 1.8 1.2 3.1 0 4.5-2.7 5.5-5.3 5.8.4.4.8 1.1.8 2.2v3.3c0 .3.2.6.8.5A11.3 11.3 0 0 0 12 .7Z"/></svg>
                        </a>
                        <a href="mailto:{{ config('idexal.brand.support_email') }}" class="btn-icon border-white/10 bg-white/5 text-paper/70 hover:border-white/25 hover:text-paper" aria-label="Email">
                            <x-icon name="mail" />
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-8 sm:grid-cols-2 lg:grid-cols-4 min-w-0">
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-paper/40">{{ __('ui.landing.product') }}</h3>
                        <ul class="mt-4 space-y-2.5 text-sm text-paper/60">
                            @foreach ($navProducts as $slug => $product)
                                <li><a href="{{ localized_route('products.show', ['slug' => $slug]) }}" class="transition hover:text-paper">{{ $product['name'] }}</a></li>
                            @endforeach
                            <li><a href="{{ localized_route('pricing') }}" class="transition hover:text-paper">{{ __('ui.nav.pricing') }}</a></li>
                            <li><a href="{{ localized_route('providers') }}" class="transition hover:text-paper">{{ __('ui.nav.providers') }}</a></li>
                            <li><a href="{{ localized_route('download') }}" class="transition hover:text-paper">{{ __('ui.nav.download') }}</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-paper/40">{{ __('ui.nav.use_cases') }}</h3>
                        <ul class="mt-4 space-y-2.5 text-sm text-paper/60">
                            @foreach ($navUseCases as $slug => $useCase)
                                @if (($useCase['group'] ?? 'roles') === 'roles')
                                    <li><a href="{{ localized_route('use-cases.show', ['slug' => $slug]) }}" class="transition hover:text-paper">{{ $useCase['role'] }}</a></li>
                                @endif
                            @endforeach
                            <li><a href="{{ localized_route('use-cases') }}" class="transition hover:text-paper">{{ __('ui.use_cases.all') }}</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-paper/40">{{ __('ui.nav.resources') }}</h3>
                        <ul class="mt-4 space-y-2.5 text-sm text-paper/60">
                            <li><a href="{{ localized_route('resources') }}" class="transition hover:text-paper">{{ __('ui.nav.resources') }}</a></li>
                            <li><a href="{{ localized_route('integrations') }}" class="transition hover:text-paper">{{ __('ui.nav.integrations') }}</a></li>
                            <li><a href="{{ localized_route('docs') }}#quickstart" class="transition hover:text-paper">{{ __('ui.docs.quickstart') }}</a></li>
                            <li><a href="{{ localized_route('changelog') }}" class="transition hover:text-paper">{{ __('ui.nav.changelog') }}</a></li>
                            <li><a href="{{ localized_route('developers') }}" class="transition hover:text-paper">{{ __('ui.nav.developers') }}</a></li>
                            <li><a href="{{ localized_route('enterprise') }}" class="transition hover:text-paper">{{ __('ui.nav.enterprise') }}</a></li>
                            <li><a href="{{ localized_route('security') }}" class="transition hover:text-paper">{{ __('ui.nav.security') }}</a></li>
                            <li><a href="{{ localized_route('contact') }}" class="transition hover:text-paper">{{ __('ui.nav.contact') }}</a></li>
                            <li><a href="{{ config('idexal.brand.github') }}" target="_blank" rel="noreferrer" class="transition hover:text-paper">GitHub</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-paper/40">{{ __('ui.landing.legal') }}</h3>
                        <ul class="mt-4 space-y-2.5 text-sm text-paper/60">
                            <li><a href="{{ localized_route('legal.terms') }}" class="transition hover:text-paper">{{ __('ui.auth.terms') }}</a></li>
                            <li><a href="{{ localized_route('legal.privacy') }}" class="transition hover:text-paper">{{ __('ui.auth.privacy') }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="mt-12 flex flex-col items-start justify-between gap-3 border-t border-white/10 pt-6 text-xs text-paper/40 sm:flex-row sm:items-center">
                <p>&copy; {{ now()->year }} {{ config('idexal.brand.legal_name') }}. {{ __('ui.common.rights') }}</p>
                <p class="font-mono" dir="ltr">{{ config('idexal.brand.domain') }}</p>
            </div>
        </div>
    </footer>
</body>
</html>
