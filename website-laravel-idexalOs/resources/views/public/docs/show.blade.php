@extends('layouts.public')

@section('title', ($page['title'] ?? __('ui.docs.search_title')).' — '.config('idexal.brand.name'))
@section('description', $page['description'] ?? __('ui.docs.subtitle'))

@push('head')
    <style>
        /* The documentation is authored as Markdown, so its elements are styled
           here rather than by utility classes on every node. */
        .docs-prose { color: var(--color-ink-600); font-size: 15px; line-height: 1.85; }
        .docs-prose > * + * { margin-top: 1.1rem; }
        .docs-prose h1 { margin-top: 0; font-size: 30px; line-height: 1.25; letter-spacing: -0.02em; color: var(--color-ink-950); font-weight: 600; }
        .docs-prose h2 { margin-top: 2.4rem; font-size: 22px; line-height: 1.35; letter-spacing: -0.01em; color: var(--color-ink-950); font-weight: 600; }
        .docs-prose h3 { margin-top: 1.8rem; font-size: 17px; color: var(--color-ink-950); font-weight: 600; }
        .docs-prose h4 { margin-top: 1.4rem; font-size: 15px; color: var(--color-ink-950); font-weight: 600; }
        .docs-prose a { color: var(--color-ink-950); text-decoration: underline; text-underline-offset: 3px; }
        .docs-prose strong { color: var(--color-ink-950); font-weight: 600; }
        .docs-prose ul { list-style: disc; padding-inline-start: 1.4rem; }
        .docs-prose ol { list-style: decimal; padding-inline-start: 1.4rem; }
        .docs-prose li + li { margin-top: 0.35rem; }
        .docs-prose code { background: var(--color-paper-2); border: 1px solid color-mix(in oklab, var(--color-ink-950) 10%, transparent); border-radius: 6px; padding: 0.1rem 0.35rem; font-family: var(--font-mono); font-size: 0.85em; color: var(--color-ink-950); }
        .docs-prose pre { background: var(--color-ink-950); color: #e8efe8; border-radius: 14px; padding: 1rem 1.15rem; overflow-x: auto; direction: ltr; }
        .docs-prose pre code { background: transparent; border: 0; color: inherit; padding: 0; font-size: 12.5px; line-height: 1.7; }
        .docs-prose blockquote { border-inline-start: 3px solid var(--color-lime-dark); background: color-mix(in oklab, var(--color-lime) 8%, white); border-radius: 0 12px 12px 0; padding: 0.85rem 1.1rem; color: var(--color-ink-700); }
        .docs-prose blockquote p + p { margin-top: 0.6rem; }
        .docs-prose table { width: 100%; border-collapse: collapse; font-size: 13.5px; display: block; overflow-x: auto; }
        .docs-prose th, .docs-prose td { border: 1px solid color-mix(in oklab, var(--color-ink-950) 12%, transparent); padding: 0.55rem 0.7rem; text-align: start; }
        .docs-prose th { background: var(--color-paper-2); color: var(--color-ink-950); font-weight: 600; }
        .docs-prose img { border-radius: 14px; border: 1px solid color-mix(in oklab, var(--color-ink-950) 10%, transparent); }
        .docs-prose hr { border-color: color-mix(in oklab, var(--color-ink-950) 10%, transparent); }
        .docs-nav a { display: block; border-radius: 8px; padding: 0.3rem 0.6rem; font-size: 13px; line-height: 1.6; color: var(--color-ink-500); }
        .docs-nav a:hover { background: var(--color-paper-2); color: var(--color-ink-950); }
        .docs-nav a[aria-current="page"] { background: var(--color-ink-950); color: var(--color-paper); font-weight: 500; }
    </style>
@endpush

@section('content')
    <section class="border-b border-ink-950/10 bg-paper-2/40">
        <div class="container-x flex flex-wrap items-center justify-between gap-4 py-6">
            <div class="flex items-baseline gap-3">
                <span class="eyebrow"><span class="dot bg-lime-dark"></span>{{ __('ui.docs.eyebrow') }}</span>
                <span class="hidden text-xs text-ink-400 sm:inline">{{ __('ui.docs.count', ['count' => number_format($pageCount)]) }}</span>
            </div>
            <form method="GET" action="{{ localized_route('docs') }}" class="flex w-full max-w-sm items-center gap-2">
                <input type="search" name="q" value="{{ $searchQuery ?? '' }}" placeholder="{{ __('ui.docs.search_placeholder') }}"
                       class="w-full rounded-xl border border-ink-950/15 bg-white px-3.5 py-2 text-sm outline-none focus:border-ink-950 focus:ring-2 focus:ring-lime/70"
                       aria-label="{{ __('ui.docs.search_placeholder') }}">
                <button type="submit" class="btn btn-primary h-10 px-4">{{ __('ui.docs.search') }}</button>
            </form>
        </div>
    </section>

    <div class="container-x grid gap-10 py-10 lg:grid-cols-[240px_minmax(0,1fr)] xl:grid-cols-[240px_minmax(0,1fr)_200px]">
        <aside class="lg:sticky lg:top-6 lg:max-h-[85vh] lg:overflow-y-auto" aria-label="{{ __('ui.docs.navigation') }}">
            <nav class="docs-nav space-y-5">
                @foreach ($navigation as $tab)
                    <div>
                        <p class="px-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ $tab['tab'] }}</p>
                        <div class="mt-1.5 space-y-0.5">
                            @foreach ($tab['items'] as $item)
                                @if ($item['type'] === 'page')
                                    <a href="{{ localized_route('docs.show', ['path' => $item['slug']]) }}"
                                       @if (($page['slug'] ?? null) === $item['slug']) aria-current="page" @endif>{{ $item['title'] }}</a>
                                @else
                                    <p class="mt-2 px-2 text-[11px] font-medium text-ink-400">{{ $item['title'] }}</p>
                                    @foreach ($item['items'] as $child)
                                        @if ($child['type'] === 'page')
                                            <a href="{{ localized_route('docs.show', ['path' => $child['slug']]) }}"
                                               @if (($page['slug'] ?? null) === $child['slug']) aria-current="page" @endif>{{ $child['title'] }}</a>
                                        @endif
                                    @endforeach
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>
        </aside>

        <main class="min-w-0">
            @if ($results !== null)
                <h1 class="text-2xl font-semibold tracking-tight">{{ __('ui.docs.search_results', ['query' => $searchQuery]) }}</h1>
                <p class="mt-2 text-sm text-ink-500">{{ trans_choice('ui.docs.search_hits', count($results), ['count' => count($results)]) }}</p>

                @if (count($results) === 0)
                    <div class="mt-6 rounded-2xl border border-ink-950/10 bg-white p-6 text-sm text-ink-500">
                        {{ __('ui.docs.no_results') }}
                        <a href="{{ localized_route('docs') }}" class="font-medium text-ink-950 underline">{{ __('ui.docs.back_home') }}</a>
                    </div>
                @else
                    <ul class="mt-6 space-y-3">
                        @foreach ($results as $result)
                            <li class="rounded-2xl border border-ink-950/10 bg-white p-4">
                                <a href="{{ localized_route('docs.show', ['path' => $result['slug']]) }}" class="text-sm font-semibold text-ink-950">{{ $result['title'] }}</a>
                                <p class="mt-1 font-mono text-[10px] text-ink-400" dir="ltr">{{ $result['slug'] }}</p>
                                <p class="mt-1.5 text-xs leading-6 text-ink-500" dir="auto">{{ $result['snippet'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @else
                <nav class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-ink-400" aria-label="{{ __('ui.docs.breadcrumb') }}">
                    @if ($page['tab'])<span>{{ $page['tab'] }}</span><span aria-hidden="true">/</span>@endif
                    @if ($page['group'])<span>{{ $page['group'] }}</span><span aria-hidden="true">/</span>@endif
                    <span class="text-ink-600">{{ $page['title'] }}</span>
                </nav>

                <article class="docs-prose mt-5" dir="auto">
                    <h1>{{ $page['title'] }}</h1>
                    @if ($page['description'])
                        <p class="text-base leading-8 text-ink-500">{{ $page['description'] }}</p>
                    @endif
                    {!! $page['html'] !!}
                </article>

                <div class="mt-10 flex flex-col gap-3 border-t border-ink-950/10 pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        @if ($page['prev'])
                            <a href="{{ localized_route('docs.show', ['path' => $page['prev']['slug']]) }}" class="text-sm font-medium text-ink-950">
                                <span class="block text-[11px] font-normal text-ink-400">{{ __('ui.docs.previous') }}</span>
                                {{ $page['prev']['title'] }}
                            </a>
                        @endif
                    </div>
                    <div class="sm:text-end">
                        @if ($page['next'])
                            <a href="{{ localized_route('docs.show', ['path' => $page['next']['slug']]) }}" class="text-sm font-medium text-ink-950">
                                <span class="block text-[11px] font-normal text-ink-400">{{ __('ui.docs.next') }}</span>
                                {{ $page['next']['title'] }}
                            </a>
                        @endif
                    </div>
                </div>

                <p class="mt-6 text-[11px] text-ink-400">
                    <a href="{{ localized_route('docs.show', ['path' => $page['slug'].'.md']) }}" class="underline">{{ __('ui.docs.view_source') }}</a>
                </p>
            @endif
        </main>

        <aside class="hidden xl:block" aria-label="{{ __('ui.docs.on_this_page') }}">
            @if ($page && count($page['toc']) > 0)
                <div class="sticky top-6">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __('ui.docs.on_this_page') }}</p>
                    <ul class="mt-2 space-y-1.5 text-xs leading-5 text-ink-500">
                        @foreach ($page['toc'] as $heading)
                            <li @class(['ps-3' => $heading['level'] === 3])>{{ $heading['text'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
@endsection
