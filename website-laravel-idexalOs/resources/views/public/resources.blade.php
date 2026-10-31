@extends('layouts.public')

@section('title', __('ui.resources.title').' — '.config('idexal.brand.name'))
@section('description', __('ui.resources.subtitle'))

@section('content')
    <section class="relative overflow-hidden border-b border-ink-950/10">
        <div class="pointer-events-none absolute inset-0 grid-lines opacity-50 [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>
        <div class="container-x relative py-14 lg:py-20">
            <span class="eyebrow">
                <span class="dot bg-lime-dark"></span>
                {{ __('ui.resources.eyebrow') }}
            </span>
            <h1 class="mt-5 max-w-3xl text-balance text-4xl font-semibold tracking-[-0.03em] sm:text-5xl">{{ __('ui.resources.title') }}</h1>
            <p class="mt-4 max-w-2xl text-base leading-8 text-ink-500">{{ __('ui.resources.subtitle') }}</p>
        </div>
    </section>

    <section class="container-x py-16 lg:py-20">
        <h2 class="text-xl font-semibold tracking-tight">{{ __('ui.resources.cards_title') }}</h2>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (__('ui.resources.cards') as $card)
                <a href="{{ localized_route($card['route']) }}" class="group flex flex-col rounded-2xl border border-ink-950/10 bg-white p-5 transition hover:border-ink-950/25 hover:shadow-card">
                    <p class="text-sm font-semibold text-ink-950">{{ $card['title'] }}</p>
                    <p class="mt-1.5 flex-1 text-xs leading-6 text-ink-500">{{ $card['body'] }}</p>
                    <span class="mt-4 inline-flex items-center gap-1.5 text-xs font-semibold text-ink-950">
                        {{ $card['label'] }}
                        <x-icon name="arrow-left" class="size-3.5 rtl:rotate-180" />
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    @if ($releases !== [])
        <section class="border-y border-ink-950/10 bg-paper-2/40">
            <div class="container-x py-16 lg:py-20">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="max-w-2xl">
                        <h2 class="text-xl font-semibold tracking-tight">{{ __('ui.resources.releases_title') }}</h2>
                        <p class="mt-2 text-sm leading-7 text-ink-500">{{ __('ui.resources.releases_body') }}</p>
                    </div>
                    <a href="{{ localized_route('changelog') }}" class="btn btn-outline btn-sm h-10 px-4">
                        {{ __('ui.resources.releases_all') }}
                        <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
                    </a>
                </div>

                <ol class="mt-8 divide-y divide-ink-950/10 border-y border-ink-950/10">
                    @foreach ($releases as $release)
                        <li class="flex flex-wrap items-baseline gap-x-4 gap-y-1 py-4">
                            <a href="{{ localized_route('changelog') }}#v{{ $release['version'] }}" class="font-mono text-sm font-medium text-ink-950" dir="ltr">{{ $release['version'] }}</a>
                            <span class="text-xs text-ink-400" dir="ltr">{{ $release['date'] }}</span>
                            <span class="text-sm text-ink-600">{{ $release['title'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    <section class="container-x py-16 lg:py-20">
        <div class="flex flex-wrap items-center justify-between gap-5 rounded-3xl border border-ink-950/10 bg-paper-2/60 p-6 lg:p-8">
            <div class="max-w-xl">
                <p class="text-lg font-semibold">{{ __('ui.resources.open_source_title') }}</p>
                <p class="mt-1 text-sm leading-7 text-ink-500">{{ __('ui.resources.open_source_body') }}</p>
            </div>
            <a href="{{ config('idexal.brand.github') }}" target="_blank" rel="noreferrer" class="btn btn-primary h-10 px-4">
                {{ __('ui.resources.open_source_cta') }}
                <x-icon name="external" class="size-4" />
            </a>
        </div>
    </section>
@endsection
