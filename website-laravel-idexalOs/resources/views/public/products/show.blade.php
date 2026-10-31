@extends('layouts.public')

@php($accent = [
    'lime' => 'bg-lime-dark',
    'sky' => 'bg-sky-500',
    'ember' => 'bg-orange-500',
    'gold' => 'bg-amber-500',
    'ink' => 'bg-ink-950',
    'teal' => 'bg-teal-600',
    'rose' => 'bg-rose-500',
][$product['accent'] ?? 'lime'] ?? 'bg-lime-dark')

@section('title', $product['name'].' — '.config('idexal.brand.name'))
@section('description', $product['summary'])

@section('content')
    <section class="relative overflow-hidden border-b border-ink-950/10">
        <div class="pointer-events-none absolute inset-0 grid-lines opacity-50 [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>
        <div class="container-x relative py-14 lg:py-20">
            <nav class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-ink-400" aria-label="{{ __('ui.docs.breadcrumb') }}">
                <a href="{{ localized_route('products') }}" class="transition hover:text-ink-950">{{ __('ui.products.all') }}</a>
                <span aria-hidden="true">/</span>
                <span class="text-ink-600">{{ $product['name'] }}</span>
            </nav>

            <div class="mt-5 flex items-center gap-2.5">
                <span class="dot {{ $accent }}"></span>
                <span class="eyebrow !text-ink-500">{{ $product['tagline'] }}</span>
            </div>
            <h1 class="mt-3 max-w-3xl text-balance text-4xl font-semibold tracking-[-0.03em] sm:text-5xl">{{ $product['name'] }}</h1>
            <p class="mt-4 max-w-2xl text-base leading-8 text-ink-500">{{ $product['summary'] }}</p>

            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a href="{{ localized_route('download') }}" class="btn btn-primary h-10 px-4">
                    {{ __('ui.products.try_it') }}
                    <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
                </a>
                <a href="{{ localized_route('docs.show', ['path' => $product['docs']]) }}" class="btn btn-outline h-10 px-4">
                    {{ __('ui.products.read_docs') }}
                </a>
            </div>

            <dl class="mt-8 flex flex-wrap gap-3">
                @foreach ($product['facts'] as $fact)
                    <div class="rounded-xl border border-ink-950/10 bg-white px-3.5 py-2.5 shadow-sm">
                        <dt class="text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ $fact['label'] }}</dt>
                        <dd class="mt-0.5 text-sm font-medium text-ink-950">{{ $fact['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    <section class="container-x py-16 lg:py-20">
        <h2 class="text-xl font-semibold tracking-tight">{{ __('ui.products.capabilities_title') }}</h2>
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            @foreach ($product['capabilities'] as $capability)
                <article class="rounded-3xl border border-ink-950/10 bg-white p-6">
                    <h3 class="flex items-center gap-2.5 text-base font-semibold text-ink-950">
                        <span class="dot {{ $accent }}"></span>
                        {{ $capability['title'] }}
                    </h3>
                    <p class="mt-2.5 text-sm leading-7 text-ink-500">{{ $capability['body'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="border-y border-ink-950/10 bg-paper-2/40">
        <div class="container-x py-16 lg:py-20">
            <h2 class="text-xl font-semibold tracking-tight">{{ __('ui.products.steps_title') }}</h2>
            <ol class="mt-8 grid gap-6 md:grid-cols-3">
                @foreach ($product['steps'] as $index => $step)
                    <li class="relative">
                        <span class="font-mono text-xs text-ink-300" dir="ltr">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <h3 class="mt-2 text-base font-semibold text-ink-950">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-sm leading-7 text-ink-500">{{ $step['body'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    @if ($related !== [])
        <section class="container-x py-16 lg:py-20">
            <h2 class="text-xl font-semibold tracking-tight">{{ __('ui.products.related_title') }}</h2>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $relatedSlug => $relatedProduct)
                    <a href="{{ localized_route('products.show', ['slug' => $relatedSlug]) }}"
                       class="group rounded-2xl border border-ink-950/10 bg-white p-5 transition hover:border-ink-950/25 hover:shadow-card">
                        <p class="text-sm font-semibold text-ink-950">{{ $relatedProduct['name'] }}</p>
                        <p class="mt-1 text-xs leading-6 text-ink-500">{{ $relatedProduct['tagline'] }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="container-x pb-20">
        <div class="rounded-3xl border border-ink-950/10 bg-paper-2/60 p-6 lg:p-8">
            <div class="flex flex-wrap items-center justify-between gap-5">
                <div>
                    <p class="text-lg font-semibold">{{ __('ui.products.see_suite') }}</p>
                    <p class="mt-1 max-w-xl text-sm leading-7 text-ink-500">{{ __('ui.products.subtitle') }}</p>
                </div>
                <a href="{{ localized_route('products') }}" class="btn btn-primary h-10 px-4">
                    {{ __('ui.products.all') }}
                    <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
                </a>
            </div>
        </div>
    </section>
@endsection
