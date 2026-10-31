@extends('layouts.public')

@section('title', __('ui.security.title'))
@section('description', __('ui.security.subtitle'))

@section('content')
    <section class="relative overflow-hidden border-b border-ink-950/10">
        <div class="pointer-events-none absolute inset-0 grid-lines opacity-50 [mask-image:linear-gradient(to_bottom,black,transparent_85%)]"></div>
        <div class="container-x relative py-16 lg:py-24">
            <span class="eyebrow">
                <span class="dot bg-lime-dark"></span>
                {{ __('ui.security.eyebrow') }}
            </span>
            <h1 class="mt-6 max-w-3xl text-balance text-4xl font-semibold leading-[1.16] tracking-[-0.03em] sm:text-5xl">
                {{ __('ui.security.title') }}
            </h1>
            <p class="mt-5 max-w-2xl text-pretty text-base leading-8 text-ink-500 sm:text-lg sm:leading-9">
                {{ __('ui.security.subtitle') }}
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ localized_route('contact') }}" class="btn btn-primary h-11 px-5">
                    {{ __('ui.security.report_cta') }}
                    <x-icon name="arrow-up-right" class="size-4" />
                </a>
                <a href="{{ localized_route('docs') }}" class="btn btn-outline h-11 px-5">
                    <x-icon name="book" class="size-4" />
                    {{ __('ui.nav.docs') }}
                </a>
            </div>
        </div>
    </section>

    <section class="container-x py-16 lg:py-24">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ([
                ['local', 'monitor'],
                ['credentials', 'key'],
                ['agents', 'shield'],
                ['browser', 'globe'],
                ['audit', 'chart'],
                ['roles', 'users'],
            ] as [$item, $icon])
                <article class="card card-pad">
                    <span class="inline-flex size-11 items-center justify-center rounded-2xl bg-ink-950 text-lime">
                        <x-icon :name="$icon" class="size-5" />
                    </span>
                    <h2 class="mt-5 text-lg font-semibold">{{ __('ui.security.'.$item.'_title') }}</h2>
                    <p class="mt-2 text-sm leading-7 text-ink-500">{{ __('ui.security.'.$item.'_body') }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="bg-ink-950 py-16 text-paper lg:py-24">
        <div class="container-x grid gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:items-start">
            <div class="min-w-0">
                <span class="eyebrow border-white/15 bg-white/5 text-paper/60">{{ __('ui.security.verify_eyebrow') }}</span>
                <h2 class="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('ui.security.verify_title') }}</h2>
                <p class="mt-4 text-sm leading-7 text-paper/55">{{ __('ui.security.verify_body') }}</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-6 min-w-0">
                <div class="flex items-center gap-3 border-b border-white/10 pb-4">
                    <span class="grid size-9 place-items-center rounded-xl bg-lime/15 text-lime">
                        <x-icon name="shield" class="size-4" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold">{{ __('ui.security.report_title') }}</p>
                        <p class="mt-0.5 text-xs text-paper/45">{{ __('ui.security.report_body') }}</p>
                    </div>
                </div>
                <a href="{{ localized_route('contact') }}" class="btn btn-lime mt-5 h-11 px-5">
                    {{ __('ui.security.report_cta') }}
                    <x-icon name="arrow-up-right" class="size-4" />
                </a>
            </div>
        </div>
    </section>
@endsection
