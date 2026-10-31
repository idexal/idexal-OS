@extends('layouts.public')

@section('title', __('ui.enterprise.title').' — '.config('idexal.brand.name'))
@section('description', __('ui.enterprise.subtitle'))

@section('content')
    <section class="relative overflow-hidden border-b border-ink-950/10">
        <div class="pointer-events-none absolute inset-0 grid-lines opacity-50 [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>
        <div class="container-x relative py-14 lg:py-20">
            <span class="eyebrow">
                <span class="dot bg-ink-950"></span>
                {{ __('ui.enterprise.eyebrow') }}
            </span>
            <h1 class="mt-5 max-w-3xl text-balance text-4xl font-semibold tracking-[-0.03em] sm:text-5xl">{{ __('ui.enterprise.title') }}</h1>
            <p class="mt-4 max-w-2xl text-base leading-8 text-ink-500">{{ __('ui.enterprise.subtitle') }}</p>

            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a href="{{ localized_route('contact') }}" class="btn btn-primary h-10 px-4">
                    {{ __('ui.enterprise.cta') }}
                    <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
                </a>
                <a href="{{ localized_route('docs.show', ['path' => __('ui.enterprise.docs')]) }}" class="btn btn-outline h-10 px-4">
                    {{ __('ui.enterprise.cta_docs') }}
                </a>
            </div>

            <dl class="mt-8 flex flex-wrap gap-3">
                @foreach (__('ui.enterprise.facts') as $fact)
                    <div class="rounded-xl border border-ink-950/10 bg-white px-3.5 py-2.5 shadow-sm">
                        <dt class="text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ $fact['label'] }}</dt>
                        <dd class="mt-0.5 text-sm font-medium text-ink-950">{{ $fact['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    <section class="container-x py-16 lg:py-20">
        <h2 class="text-xl font-semibold tracking-tight">{{ __('ui.enterprise.capabilities_title') }}</h2>
        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach (__('ui.enterprise.capabilities') as $capability)
                <article class="rounded-3xl border border-ink-950/10 bg-white p-6">
                    <h3 class="flex items-center gap-2.5 text-base font-semibold text-ink-950">
                        <span class="dot bg-ink-950"></span>
                        {{ $capability['title'] }}
                    </h3>
                    <p class="mt-2.5 text-sm leading-7 text-ink-500">{{ $capability['body'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="border-y border-ink-950/10 bg-paper-2/40">
        <div class="container-x py-16 lg:py-20">
            <div class="grid items-start gap-8 lg:grid-cols-[1fr_1.2fr]">
                <div>
                    <h2 class="text-xl font-semibold tracking-tight">{{ __('ui.enterprise.identity_title') }}</h2>
                    <p class="mt-2 max-w-md text-sm leading-7 text-ink-500">{{ __('ui.enterprise.identity_body') }}</p>
                </div>
                <ul class="grid gap-3 sm:grid-cols-3">
                    @foreach (__('ui.enterprise.identity') as $provider)
                        <li class="flex items-center gap-2.5 rounded-2xl border border-ink-950/10 bg-white px-4 py-3.5">
                            <x-icon name="shield" class="size-4 text-lime-dark" />
                            <span class="text-sm font-medium text-ink-950">{{ $provider }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="container-x py-16 lg:py-20">
        <h2 class="text-xl font-semibold tracking-tight">{{ __('ui.enterprise.deploy_title') }}</h2>
        <div class="mt-6 grid gap-4 md:grid-cols-3">
            @foreach (__('ui.enterprise.deploy') as $option)
                <article class="rounded-3xl border border-ink-950/10 bg-white p-6">
                    <h3 class="text-base font-semibold text-ink-950">{{ $option['title'] }}</h3>
                    <p class="mt-2 text-sm leading-7 text-ink-500">{{ $option['body'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="container-x pb-20">
        <div class="relative overflow-hidden rounded-[2rem] mesh-dark px-6 py-14 text-paper sm:px-12 lg:px-16">
            <div class="pointer-events-none absolute inset-0 grid-lines opacity-[0.12]"></div>
            <div class="relative flex flex-col items-start justify-between gap-8 lg:flex-row lg:items-center">
                <div class="max-w-2xl">
                    <h2 class="text-balance text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('ui.enterprise.cta_title') }}</h2>
                    <p class="mt-3 text-sm leading-7 text-paper/55">{{ __('ui.enterprise.cta_body') }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ localized_route('contact') }}" class="btn btn-lime h-12 px-6">
                        {{ __('ui.enterprise.cta') }}
                        <x-icon name="arrow-up-right" class="size-4" />
                    </a>
                    <a href="{{ localized_route('products') }}" class="btn h-12 border border-white/15 px-6 text-paper hover:bg-white/5">
                        {{ __('ui.enterprise.see_products') }}
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
