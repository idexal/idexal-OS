@extends('layouts.public')

@section('title', __('changelog.meta_title'))
@section('description', __('changelog.meta_description'))

@push('schema')
    @php
        $itemList = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => __('changelog.title'),
            'inLanguage' => str_replace('_', '-', app()->getLocale()),
            'itemListElement' => collect($releases)
                ->map(fn (array $release, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $release['title'],
                    'url' => localized_route('changelog').'#v'.$release['version'],
                ])
                ->values()
                ->all(),
        ];
    @endphp
    <script type="application/ld+json">{!! json_ld($itemList) !!}</script>
@endpush

@section('content')
    <section class="relative overflow-hidden border-b border-ink-950/10">
        <div class="pointer-events-none absolute inset-0 grid-lines opacity-50 [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>
        <div class="container-x relative py-14 lg:py-20">
            <span class="eyebrow">
                <span class="dot bg-lime-dark"></span>
                {{ __('changelog.eyebrow') }}
            </span>
            <h1 class="mt-5 max-w-3xl text-balance text-4xl font-semibold tracking-[-0.03em] sm:text-5xl">
                {{ __('changelog.heading') }}
            </h1>
            <p class="mt-4 max-w-2xl text-pretty text-base leading-8 text-ink-500">
                {{ __('changelog.intro') }}
            </p>

            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a href="{{ localized_route('download') }}" class="btn btn-primary h-12 px-6">
                    <x-icon name="download" class="size-4" />
                    {{ __('changelog.cta') }}
                </a>
                <a href="{{ localized_route('home') }}" class="btn btn-outline h-12 px-6">
                    {{ __('changelog.back_home') }}
                </a>
            </div>
        </div>
    </section>

    <section class="container-x py-14 lg:py-20">
        <div class="grid gap-12 lg:grid-cols-[220px_1fr]">
            <aside class="min-w-0">
                <div class="sticky top-28 space-y-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-400">
                        {{ __('changelog.current') }}
                    </p>
                    <p class="font-mono text-2xl font-medium tracking-tight" dir="ltr">
                        v{{ $current['version'] ?? config('idexal.version') }}
                    </p>
                    @if ($current)
                        <p class="text-xs leading-6 text-ink-400">
                            {{ __('changelog.released', ['date' => $current['date']]) }}
                        </p>
                    @endif
                    <a href="{{ localized_route('download') }}" class="btn btn-outline btn-sm">
                        <x-icon name="download" class="size-4" />
                        {{ __('ui.nav.download') }}
                    </a>
                </div>
            </aside>

            <div class="min-w-0 divide-y divide-ink-950/10 border-y border-ink-950/10">
                @foreach ($releases as $release)
                    <article id="v{{ $release['version'] }}" class="grid scroll-mt-28 gap-4 py-8 sm:grid-cols-[140px_1fr] sm:gap-8">
                        <div class="min-w-0">
                            <p class="font-mono text-sm font-semibold text-ink-950" dir="ltr">
                                <a
                                    href="#v{{ $release['version'] }}"
                                    class="rounded-sm underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lime-dark"
                                    aria-label="{{ __('changelog.permalink', ['version' => $release['version']]) }}"
                                >v{{ $release['version'] }}</a>
                            </p>
                            <time datetime="{{ $release['date'] }}" class="mt-1 block text-xs text-ink-400" dir="ltr">
                                {{ $release['date'] }}
                            </time>
                            @if ($loop->first)
                                <span class="badge mt-3 bg-lime text-ink-950">{{ __('changelog.current') }}</span>
                            @endif
                        </div>

                        <div class="min-w-0">
                            <h2 class="text-balance text-xl font-semibold tracking-tight sm:text-2xl">
                                {{ $release['title'] }}
                            </h2>
                            <p class="mt-3 text-sm leading-7 text-ink-500">
                                {{ $release['summary'] }}
                            </p>
                            <ul class="mt-4 space-y-2.5 text-sm leading-7 text-ink-600">
                                @foreach ($release['changes'] as $change)
                                    <li class="flex gap-2.5">
                                        <x-icon name="check" class="mt-1.5 size-4 shrink-0 text-lime-dark" />
                                        <span class="min-w-0">{{ $change }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="container-x pb-8">
        <div class="relative overflow-hidden rounded-[2rem] mesh-dark px-6 py-14 text-paper sm:px-12 lg:px-16 lg:py-20">
            <div class="pointer-events-none absolute inset-0 grid-lines opacity-[0.12]"></div>
            <div class="relative flex flex-col items-start justify-between gap-8 lg:flex-row lg:items-center">
                <div class="max-w-2xl">
                    <h2 class="text-balance text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('changelog.cta_title') }}</h2>
                    <p class="mt-3 text-sm leading-7 text-paper/55">{{ __('changelog.cta_body') }}</p>
                </div>
                <a href="{{ localized_route('download') }}" class="btn btn-lime h-12 px-6">
                    {{ __('changelog.cta') }}
                    <x-icon name="arrow-up-right" class="size-4" />
                </a>
            </div>
        </div>
    </section>
@endsection
