@extends('layouts.public')

@section('title', $item['name'].' — '.config('idexal.brand.name'))
@section('description', \Illuminate\Support\Str::limit($item['description'], 150))

@section('content')
    @php
        $categoryTag = collect($item['tags'])->reject(fn ($tag) => in_array($tag, ['library'], true))->first();
        // A top-level skill's category tag is its own directory name, which
        // repeats the identifier; only a real grouping is worth showing.
        $category = $categoryTag && $categoryTag !== $item['slug']
            ? \Illuminate\Support\Str::headline((string) $categoryTag)
            : null;
        $typeLabel = __('ui.integrations.type_'.$type);
    @endphp

    <section class="relative overflow-hidden border-b border-ink-950/10">
        <div class="pointer-events-none absolute inset-0 grid-lines opacity-50 [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>
        <div class="container-x relative py-14 lg:py-20">
            <nav class="flex flex-wrap items-center gap-2 text-xs text-ink-400" aria-label="{{ __('ui.integrations.detail_breadcrumb') }}">
                <a href="{{ localized_route('integrations') }}" class="transition hover:text-ink-950">{{ __('ui.integrations.title_short') }}</a>
                <span aria-hidden="true">/</span>
                <a href="{{ localized_route('integrations', ['type' => $type]) }}" class="transition hover:text-ink-950">{{ $typeLabel }}</a>
                <span aria-hidden="true">/</span>
                <span class="text-ink-600">{{ $item['name'] }}</span>
            </nav>

            <div class="mt-8 grid gap-10 lg:grid-cols-[1.5fr_1fr] lg:items-start">
                <div class="min-w-0">
                    <span class="badge badge-gray">{{ $typeLabel }}</span>
                    @if ($category)
                        <span class="ms-2 text-[11px] font-medium uppercase tracking-[0.12em] text-ink-400">{{ $category }}</span>
                    @endif

                    <h1 class="mt-4 text-balance text-4xl font-semibold tracking-[-0.03em] sm:text-5xl">{{ $item['name'] }}</h1>

                    <p class="mt-4 max-w-2xl text-pretty text-base leading-8 text-ink-500">
                        {{ $item['description'] !== '' ? $item['description'] : __('ui.integrations.detail_no_description') }}
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ localized_route('download') }}" class="btn btn-primary h-11 px-6">
                            <x-icon name="download" class="size-4" />
                            {{ __('ui.integrations.detail_cta') }}
                        </a>
                        @if ($item['url'])
                            <a href="{{ $item['url'] }}" target="_blank" rel="noreferrer" class="btn btn-outline h-11 px-6">
                                {{ __('ui.integrations.source') }}
                                <x-icon name="external" class="size-4" />
                            </a>
                        @endif
                    </div>
                </div>

                <aside class="rounded-2xl border border-ink-950/10 bg-white p-5 shadow-sm">
                    <h2 class="text-[11px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __('ui.integrations.detail_meta') }}</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-ink-400">{{ __('ui.integrations.detail_type') }}</dt>
                            <dd class="font-medium text-ink-950">{{ $typeLabel }}</dd>
                        </div>
                        @if ($category)
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-ink-400">{{ __('ui.integrations.detail_category') }}</dt>
                                <dd class="font-medium text-ink-950">{{ $category }}</dd>
                            </div>
                        @endif
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-ink-400">{{ __('ui.integrations.detail_identifier') }}</dt>
                            <dd class="font-mono text-xs text-ink-600" dir="ltr">{{ $item['slug'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-ink-400">{{ __('ui.integrations.detail_catalogue_total') }}</dt>
                            <dd class="font-medium text-ink-950">{{ number_format($counts['all']) }}</dd>
                        </div>
                    </dl>
                </aside>
            </div>
        </div>
    </section>

    <section class="container-x py-16 lg:py-20">
        <div class="grid gap-12 lg:grid-cols-[1.2fr_0.8fr]">
            <div class="min-w-0">
                <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.integrations.detail_how_title') }}</h2>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-ink-500">{{ __('ui.integrations.detail_how_body') }}</p>

                <ol class="mt-8 space-y-5">
                    @foreach ([1, 2, 3] as $step)
                        <li class="flex gap-4">
                            <span class="mt-0.5 inline-flex size-8 shrink-0 items-center justify-center rounded-xl bg-lime/25 font-mono text-sm font-semibold text-ink-950">{{ $step }}</span>
                            <div>
                                <p class="text-sm font-semibold text-ink-950">{{ __('ui.integrations.detail_step_'.$step.'_title') }}</p>
                                <p class="mt-1 text-sm leading-7 text-ink-500">{{ __('ui.integrations.detail_step_'.$step.'_body') }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="min-w-0 rounded-2xl border border-ink-950/10 bg-paper-2/60 p-6">
                <h2 class="font-semibold">{{ __('ui.integrations.detail_about_title') }}</h2>
                <p class="mt-2 text-sm leading-7 text-ink-500">{{ __('ui.integrations.detail_about_body') }}</p>
                <a href="{{ localized_route('docs') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-950">
                    {{ __('ui.integrations.detail_about_cta') }}
                    <x-icon name="arrow-right" class="size-4 rtl:rotate-180" />
                </a>
            </div>
        </div>
    </section>

    @if ($related->isNotEmpty())
        <section class="border-t border-ink-950/10 bg-white/60 py-16 lg:py-20">
            <div class="container-x">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.integrations.detail_related') }}</h2>
                    <a href="{{ localized_route('integrations', ['type' => $type]) }}" class="text-sm font-semibold text-ink-950 hover:underline">{{ __('ui.integrations.filter_all') }}</a>
                </div>

                <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($related as $entry)
                        <a href="{{ localized_route('integrations.show', ['type' => $entry['type'], 'slug' => $entry['slug']]) }}" class="group flex flex-col rounded-2xl border border-ink-950/10 bg-white p-4 transition hover:border-ink-950/25">
                            <span class="badge badge-gray">{{ __('ui.integrations.type_'.$entry['type']) }}</span>
                            <h3 class="mt-3 text-sm font-semibold leading-6">{{ $entry['name'] }}</h3>
                            <p class="mt-1 line-clamp-2 text-xs leading-6 text-ink-500">{{ $entry['description'] }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
