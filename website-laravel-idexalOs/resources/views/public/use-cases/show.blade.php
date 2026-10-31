@extends('layouts.public')

@php($accent = [
    'lime' => 'bg-lime-dark',
    'sky' => 'bg-sky-500',
    'ember' => 'bg-orange-500',
    'gold' => 'bg-amber-500',
    'ink' => 'bg-ink-950',
    'teal' => 'bg-teal-600',
    'rose' => 'bg-rose-500',
][$useCase['accent'] ?? 'lime'] ?? 'bg-lime-dark')

@section('title', $useCase['role'].' — '.config('idexal.brand.name'))
@section('description', $useCase['summary'])

@section('content')
    <section class="relative overflow-hidden border-b border-ink-950/10">
        <div class="pointer-events-none absolute inset-0 grid-lines opacity-50 [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>
        <div class="container-x relative py-14 lg:py-20">
            <nav class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-ink-400" aria-label="{{ __('ui.docs.breadcrumb') }}">
                <a href="{{ localized_route('use-cases') }}" class="transition hover:text-ink-950">{{ __('ui.use_cases.all') }}</a>
                <span aria-hidden="true">/</span>
                <span class="text-ink-600">{{ __('ui.use_cases.'.($useCase['group'] ?? 'roles')) }}</span>
            </nav>

            <div class="mt-5 flex items-center gap-2.5">
                <span class="dot {{ $accent }}"></span>
                <span class="eyebrow !text-ink-500">{{ $useCase['tagline'] }}</span>
            </div>
            <h1 class="mt-3 max-w-3xl text-balance text-4xl font-semibold tracking-[-0.03em] sm:text-5xl">{{ $useCase['role'] }}</h1>
            <p class="mt-4 max-w-2xl text-base leading-8 text-ink-500">{{ $useCase['summary'] }}</p>

            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a href="{{ localized_route('download') }}" class="btn btn-primary h-10 px-4">
                    {{ __('ui.use_cases.try_it') }}
                    <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
                </a>
                <a href="{{ localized_route('docs.show', ['path' => $useCase['docs']]) }}" class="btn btn-outline h-10 px-4">
                    {{ __('ui.use_cases.read_docs') }}
                </a>
            </div>
        </div>
    </section>

    <section class="container-x py-16 lg:py-20">
        <div class="grid gap-12 lg:grid-cols-[1.4fr_1fr]">
            <div class="min-w-0">
                <h2 class="text-xl font-semibold tracking-tight">{{ __('ui.use_cases.tasks_title') }}</h2>
                <ol class="mt-6 space-y-4">
                    @foreach ($useCase['tasks'] as $index => $task)
                        <li class="rounded-2xl border border-ink-950/10 bg-white p-5">
                            <div class="flex items-baseline gap-3">
                                <span class="font-mono text-xs text-ink-300" dir="ltr">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <h3 class="text-base font-semibold text-ink-950">{{ $task['title'] }}</h3>
                            </div>
                            <p class="mt-2 ps-7 text-sm leading-7 text-ink-500">{{ $task['body'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>

            <aside class="min-w-0 space-y-6">
                <div class="rounded-2xl border border-ink-950/10 bg-paper-2/60 p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __('ui.use_cases.outcomes_title') }}</h2>
                    <ul class="mt-4 space-y-3">
                        @foreach ($useCase['outcomes'] as $outcome)
                            <li class="flex items-start gap-2.5 text-sm leading-7 text-ink-600">
                                <x-icon name="check" class="mt-1.5 size-3.5 shrink-0 text-lime-dark" />
                                {{ $outcome }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if ($products !== [])
                    <div class="rounded-2xl border border-ink-950/10 bg-white p-6">
                        <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __('ui.use_cases.products_title') }}</h2>
                        <ul class="mt-4 space-y-2">
                            @foreach ($products as $productSlug => $product)
                                <li>
                                    <a href="{{ localized_route('products.show', ['slug' => $productSlug]) }}" class="flex items-center justify-between gap-3 rounded-xl px-2 py-2 text-sm transition hover:bg-paper-2">
                                        <span class="font-medium text-ink-950">{{ $product['name'] }}</span>
                                        <x-icon name="arrow-left" class="size-3.5 text-ink-400 rtl:rotate-180" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </aside>
        </div>
    </section>

    <section class="container-x pb-20">
        <div class="rounded-3xl border border-ink-950/10 bg-paper-2/60 p-6 lg:p-8">
            <div class="flex flex-wrap items-center justify-between gap-5">
                <div>
                    <p class="text-lg font-semibold">{{ __('ui.use_cases.browse') }}</p>
                    <p class="mt-1 max-w-xl text-sm leading-7 text-ink-500">{{ __('ui.use_cases.subtitle') }}</p>
                </div>
                <a href="{{ localized_route('use-cases') }}" class="btn btn-primary h-10 px-4">
                    {{ __('ui.use_cases.all') }}
                    <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
                </a>
            </div>
        </div>
    </section>
@endsection
