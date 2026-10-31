@extends('layouts.public')

@section('title', __('ui.products.title').' — '.config('idexal.brand.name'))
@section('description', __('ui.products.subtitle'))

@section('content')
    <section class="relative overflow-hidden border-b border-ink-950/10">
        <div class="pointer-events-none absolute inset-0 grid-lines opacity-50 [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>
        <div class="container-x relative py-14 lg:py-20">
            <span class="eyebrow">
                <span class="dot bg-lime-dark"></span>
                {{ __('ui.products.eyebrow') }}
            </span>
            <h1 class="mt-5 max-w-3xl text-balance text-4xl font-semibold tracking-[-0.03em] sm:text-5xl">{{ __('ui.products.title') }}</h1>
            <p class="mt-4 max-w-2xl text-base leading-8 text-ink-500">{{ __('ui.products.subtitle') }}</p>
            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a href="{{ localized_route('download') }}" class="btn btn-primary h-10 px-4">
                    {{ __('ui.nav.download') }}
                    <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
                </a>
                <a href="{{ localized_route('pricing') }}" class="btn btn-outline h-10 px-4">{{ __('ui.providers.see_plans') }}</a>
            </div>
        </div>
    </section>

    <section class="container-x py-16 lg:py-24">
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($products as $slug => $product)
                @php($accent = [
                    'lime' => 'bg-lime-dark',
                    'sky' => 'bg-sky-500',
                    'ember' => 'bg-orange-500',
                    'gold' => 'bg-amber-500',
                    'ink' => 'bg-ink-950',
                    'teal' => 'bg-teal-600',
                    'rose' => 'bg-rose-500',
                ][$product['accent'] ?? 'lime'] ?? 'bg-lime-dark')
                <article class="group flex flex-col rounded-3xl border border-ink-950/10 bg-white p-6 transition hover:border-ink-950/25 hover:shadow-card lg:p-7">
                    <div class="flex items-center gap-2.5">
                        <span class="dot {{ $accent }}"></span>
                        <h2 class="text-lg font-semibold tracking-tight">{{ $product['name'] }}</h2>
                    </div>
                    <p class="mt-1 text-sm font-medium text-ink-500">{{ $product['tagline'] }}</p>
                    <p class="mt-3 flex-1 text-sm leading-7 text-ink-500">{{ $product['summary'] }}</p>

                    <dl class="mt-5 grid grid-cols-3 gap-2 border-t border-ink-950/5 pt-4">
                        @foreach ($product['facts'] as $fact)
                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-[0.12em] text-ink-400">{{ $fact['label'] }}</dt>
                                <dd class="mt-1 text-xs font-medium text-ink-950">{{ $fact['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <a href="{{ localized_route('products.show', ['slug' => $slug]) }}" class="btn btn-primary btn-sm h-9 px-3.5">
                            {{ __('ui.products.learn_more') }}
                            <x-icon name="arrow-left" class="size-3.5 rtl:rotate-180" />
                        </a>
                        <a href="{{ localized_route('docs.show', ['path' => $product['docs']]) }}" class="text-xs font-medium text-ink-500 underline decoration-ink-950/20 underline-offset-4 transition hover:text-ink-950">
                            {{ __('ui.products.read_docs') }}
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endsection
