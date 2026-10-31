<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ locale_direction() }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-dvh bg-paper text-ink-950">
    <div class="grid min-h-dvh lg:grid-cols-[1.05fr_1fr]">
        <aside class="relative hidden overflow-hidden mesh-dark p-12 text-paper lg:flex lg:flex-col lg:justify-between">
            <div class="pointer-events-none absolute inset-0 grid-lines opacity-[0.15]"></div>

            <div class="relative">
                <a href="{{ route('home') }}" class="inline-flex">
                    <img src="{{ asset('brand/logo-dark.png') }}" alt="{{ config('idexal.brand.name') }}" class="h-11 w-auto">
                </a>
            </div>

            <div class="relative max-w-xl">
                <span class="eyebrow border-white/15 bg-white/5 text-paper/70">
                    <span class="dot bg-lime"></span>
                    {{ __('ui.landing.eyebrow') }}
                </span>
                <h1 class="mt-6 text-balance text-4xl font-semibold leading-[1.3] tracking-tight xl:text-5xl">
                    {{ __('ui.landing.hero_title') }}
                </h1>
                <p class="mt-5 max-w-lg text-base leading-8 text-paper/55">
                    {{ __('ui.landing.hero_subtitle') }}
                </p>

                <div class="mt-10 grid grid-cols-3 gap-4">
                    @foreach ([
                        ['10+', __('ui.landing.stat_models')],
                        ['1', __('ui.landing.stat_gateway')],
                        ['∞', __('ui.landing.stat_teams')],
                    ] as [$value, $label])
                        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                            <p class="font-mono text-2xl text-lime" dir="ltr">{{ $value }}</p>
                            <p class="mt-1 text-[11px] leading-5 text-paper/45">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="relative flex items-center justify-between text-xs text-paper/35">
                <span class="font-mono" dir="ltr">{{ config('idexal.brand.domain') }}</span>
                <span>{{ __('ui.landing.hero_note') }}</span>
            </div>
        </aside>

        <main class="flex min-h-dvh flex-col">
            <div class="flex items-center justify-between px-6 py-6 sm:px-10">
                <a href="{{ route('home') }}" class="flex items-center gap-2 lg:hidden">
                    <img src="{{ asset('brand/logo-light.png') }}" alt="{{ config('idexal.brand.name') }}" class="h-9 w-auto">
                </a>
                <a href="{{ route('home') }}" class="hidden items-center gap-1.5 text-sm font-medium text-ink-500 transition hover:text-ink-950 lg:inline-flex">
                    <x-icon name="arrow-right" class="size-4 rtl:rotate-180" />
                    {{ __('ui.common.back') }}
                </a>

                <a href="{{ route('locale.update', app()->getLocale() === 'ar' ? 'en' : 'ar') }}" class="btn btn-ghost btn-sm gap-1.5">
                    <x-icon name="globe" class="size-4" />
                    {{ app()->getLocale() === 'ar' ? __('ui.nav.english') : __('ui.nav.arabic') }}
                </a>
            </div>

            <div class="flex flex-1 items-center justify-center px-6 pb-12 sm:px-10">
                <div class="w-full max-w-md">
                    <div class="mb-8">
                        <h2 class="text-3xl font-semibold tracking-tight @yield('heading_class')">@yield('heading')</h2>
                        <p class="mt-2 text-sm leading-7 text-ink-500">@yield('subheading')</p>
                    </div>

                    <div class="space-y-4 empty:hidden">
                        @include('partials.flash')
                    </div>

                    @yield('content')

                    <div class="mt-8 text-center text-xs leading-6 text-ink-400">
                        <p>&copy; {{ now()->year }} {{ config('idexal.brand.name') }} &middot; <a href="{{ route('legal.terms') }}" class="hover:text-ink-950">{{ __('ui.auth.terms') }}</a> &middot; <a href="{{ route('legal.privacy') }}" class="hover:text-ink-950">{{ __('ui.auth.privacy') }}</a></p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
