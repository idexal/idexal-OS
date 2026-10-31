@extends('layouts.public')

@section('title', __('ui.integrations.title').' — '.config('idexal.brand.name'))
@section('description', __('ui.integrations.subtitle'))

@section('content')
    <section class="relative overflow-hidden border-b border-ink-950/10">
        <div class="pointer-events-none absolute inset-0 grid-lines opacity-50 [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>
        <div class="container-x relative py-16 lg:py-24">
            <div class="grid items-end gap-10 lg:grid-cols-[1.4fr_1fr]">
                <div>
                    <span class="eyebrow">
                        <span class="dot bg-lime-dark"></span>
                        {{ __('ui.integrations.eyebrow') }}
                    </span>
                    <h1 class="mt-6 max-w-3xl text-balance text-4xl font-semibold tracking-[-0.03em] sm:text-5xl lg:text-6xl">
                        {{ __('ui.integrations.title') }}
                    </h1>
                    <p class="mt-5 max-w-2xl text-pretty text-base leading-8 text-ink-500">
                        {{ __('ui.integrations.subtitle') }}
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ localized_route('download') }}" class="btn btn-primary h-11 px-6">{{ __('ui.integrations.cta_primary') }}</a>
                        <a href="{{ localized_route('docs') }}" class="btn btn-outline h-11 px-6">{{ __('ui.integrations.cta_secondary') }}</a>
                    </div>
                </div>

                <dl class="grid grid-cols-2 gap-3">
                    @foreach ([
                        ['value' => $counts['skill'], 'label' => __('ui.integrations.count_skills')],
                        ['value' => $counts['plugin'], 'label' => __('ui.integrations.count_plugins')],
                        ['value' => $counts['connector'], 'label' => __('ui.integrations.count_connectors')],
                        ['value' => $counts['all'], 'label' => __('ui.integrations.count_all')],
                    ] as $stat)
                        <div class="rounded-2xl border border-ink-950/10 bg-white p-4 shadow-sm">
                            <dt class="text-[10px] font-semibold uppercase tracking-[0.16em] text-ink-400">{{ $stat['label'] }}</dt>
                            <dd class="mt-1 font-mono text-2xl font-medium">{{ number_format($stat['value']) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
    </section>

    @if ($featured !== [] && $type === '' && $query === '')
        <section class="container-x py-16 lg:py-20">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.integrations.featured_title') }}</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-7 text-ink-500">{{ __('ui.integrations.featured_subtitle') }}</p>
                </div>
            </div>

            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featured as $card)
                    <article class="card card-pad flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <span class="badge badge-gray">{{ $card['tag'] }}</span>
                            <x-icon name="sparkles" class="size-4 text-lime-dark" />
                        </div>
                        <h3 class="mt-4 font-semibold">{{ $card['title'] }}</h3>
                        <p class="mt-2 flex-1 text-sm leading-7 text-ink-500">{{ $card['body'] }}</p>
                        <p class="mt-4 font-mono text-[11px] text-ink-400">{{ $card['name'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section id="catalogue" class="border-y border-ink-950/10 bg-white/60 py-16 lg:py-20">
        <div class="container-x">
            <div class="flex flex-wrap items-end justify-between gap-6">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.integrations.catalogue_title') }}</h2>
                    <p class="mt-2 text-sm leading-7 text-ink-500">
                        {{ trans_choice('ui.integrations.catalogue_count', $total, ['count' => number_format($total)]) }}
                    </p>
                </div>

                <form method="get" action="{{ localized_route('integrations') }}" class="flex w-full max-w-md items-center gap-2">
                    @if ($type !== '')
                        <input type="hidden" name="type" value="{{ $type }}">
                    @endif
                    <label class="sr-only" for="catalogue-search">{{ __('ui.integrations.search') }}</label>
                    <input
                        id="catalogue-search"
                        name="q"
                        type="search"
                        value="{{ $query }}"
                        placeholder="{{ __('ui.integrations.search_placeholder') }}"
                        class="input h-11 flex-1"
                    >
                    <button type="submit" class="btn btn-primary h-11 px-5">{{ __('ui.integrations.search') }}</button>
                </form>
            </div>

            <nav class="mt-6 flex flex-wrap gap-2" aria-label="{{ __('ui.integrations.filter_label') }}">
                @foreach ([
                    '' => __('ui.integrations.filter_all'),
                    'skill' => __('ui.integrations.count_skills'),
                    'plugin' => __('ui.integrations.count_plugins'),
                    'connector' => __('ui.integrations.count_connectors'),
                ] as $value => $label)
                    <a
                        href="{{ localized_route('integrations', array_filter(['type' => $value, 'q' => $query])) }}"
                        class="rounded-full border px-4 py-2 text-xs font-semibold transition {{ $type === $value ? 'border-ink-950 bg-ink-950 text-paper' : 'border-ink-950/10 bg-white text-ink-600 hover:border-ink-950/30' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($items as $item)
                    <article class="rounded-2xl border border-ink-950/10 bg-white p-4 transition hover:border-ink-950/25">
                        <div class="flex items-center justify-between gap-3">
                            <span class="badge badge-gray">{{ __('ui.integrations.type_'.$item['type']) }}</span>
                            @if ($item['url'])
                                <a href="{{ $item['url'] }}" target="_blank" rel="noreferrer" class="text-[11px] font-semibold text-ink-400 transition hover:text-ink-950">
                                    {{ __('ui.integrations.source') }}
                                </a>
                            @endif
                        </div>
                        <h3 class="mt-3 text-sm font-semibold leading-6">{{ $item['title'] }}</h3>
                        @if ($item['description'] !== '')
                            <p class="mt-1 line-clamp-2 text-xs leading-6 text-ink-500">{{ $item['description'] }}</p>
                        @else
                            <p class="mt-1 font-mono text-[11px] text-ink-400">{{ $item['name'] }}</p>
                        @endif
                    </article>
                @empty
                    <p class="col-span-full rounded-2xl border border-dashed border-ink-300 bg-white p-10 text-center text-sm text-ink-400">
                        {{ __('ui.integrations.empty') }}
                    </p>
                @endforelse
            </div>

            @if ($paginator->hasPages())
                <nav class="mt-10 flex flex-wrap items-center justify-between gap-4" aria-label="{{ __('ui.integrations.pagination') }}">
                    <p class="text-xs text-ink-400">
                        {{ __('ui.integrations.page_of', ['page' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}
                    </p>

                    <div class="flex items-center gap-2">
                        @if ($paginator->onFirstPage())
                            <span class="btn btn-outline h-10 px-4 opacity-40">{{ __('ui.integrations.previous') }}</span>
                        @else
                            <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-outline h-10 px-4">{{ __('ui.integrations.previous') }}</a>
                        @endif

                        @if ($paginator->hasMorePages())
                            <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-primary h-10 px-4">{{ __('ui.integrations.next') }}</a>
                        @else
                            <span class="btn btn-primary h-10 px-4 opacity-40">{{ __('ui.integrations.next') }}</span>
                        @endif
                    </div>
                </nav>
            @endif
        </div>
    </section>

    <section class="container-x py-16 lg:py-20">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.integrations.how_title') }}</h2>
            <p class="mt-2 text-sm leading-7 text-ink-500">{{ __('ui.integrations.how_subtitle') }}</p>
        </div>

        <div class="mt-10 grid gap-4 md:grid-cols-3">
            @foreach ([1, 2, 3] as $step)
                <div class="card card-pad">
                    <span class="inline-flex size-9 items-center justify-center rounded-xl bg-lime/20 font-mono text-sm font-semibold text-ink-950">{{ $step }}</span>
                    <h3 class="mt-4 font-semibold">{{ __('ui.integrations.how_'.$step.'_title') }}</h3>
                    <p class="mt-1.5 text-sm leading-7 text-ink-500">{{ __('ui.integrations.how_'.$step.'_body') }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-10 flex flex-wrap justify-center gap-3">
            <a href="{{ localized_route('contact') }}" class="btn btn-primary h-11 px-6">{{ __('ui.integrations.cta_primary') }}</a>
            <a href="{{ localized_route('providers') }}" class="btn btn-outline h-11 px-6">{{ __('ui.integrations.cta_secondary') }}</a>
        </div>
    </section>
@endsection
