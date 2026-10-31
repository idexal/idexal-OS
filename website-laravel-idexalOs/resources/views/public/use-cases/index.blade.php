@extends('layouts.public')

@php($accents = [
    'lime' => 'bg-lime-dark',
    'sky' => 'bg-sky-500',
    'ember' => 'bg-orange-500',
    'gold' => 'bg-amber-500',
    'ink' => 'bg-ink-950',
    'teal' => 'bg-teal-600',
    'rose' => 'bg-rose-500',
])

@section('title', __('ui.use_cases.title').' — '.config('idexal.brand.name'))
@section('description', __('ui.use_cases.subtitle'))

@section('content')
    <section class="relative overflow-hidden border-b border-ink-950/10">
        <div class="pointer-events-none absolute inset-0 grid-lines opacity-50 [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>
        <div class="container-x relative py-14 lg:py-20">
            <span class="eyebrow">
                <span class="dot bg-lime-dark"></span>
                {{ __('ui.use_cases.eyebrow') }}
            </span>
            <h1 class="mt-5 max-w-3xl text-balance text-4xl font-semibold tracking-[-0.03em] sm:text-5xl">{{ __('ui.use_cases.title') }}</h1>
            <p class="mt-4 max-w-2xl text-base leading-8 text-ink-500">{{ __('ui.use_cases.subtitle') }}</p>
            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a href="{{ localized_route('download') }}" class="btn btn-primary h-10 px-4">
                    {{ __('ui.use_cases.try_it') }}
                    <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
                </a>
                <a href="{{ localized_route('products') }}" class="btn btn-outline h-10 px-4">{{ __('ui.nav.products') }}</a>
            </div>
        </div>
    </section>

    @foreach (['roles', 'tasks'] as $group)
        @if (($groups[$group] ?? []) !== [])
            <section class="container-x {{ $group === 'roles' ? 'pt-16 lg:pt-20' : 'pt-16 lg:pt-20' }} py-4">
                <h2 class="text-xl font-semibold tracking-tight">{{ __("ui.use_cases.{$group}") }}</h2>

                <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($groups[$group] as $slug => $useCase)
                        <article class="flex flex-col rounded-3xl border border-ink-950/10 bg-white p-6 transition hover:border-ink-950/25 hover:shadow-card">
                            <h3 class="flex items-center gap-2.5 text-base font-semibold tracking-tight">
                                <span class="dot {{ $accents[$useCase['accent'] ?? 'lime'] ?? 'bg-lime-dark' }}"></span>
                                {{ $useCase['role'] }}
                            </h3>
                            <p class="mt-1 text-sm font-medium text-ink-500">{{ $useCase['tagline'] }}</p>
                            <p class="mt-3 flex-1 text-sm leading-7 text-ink-500">{{ $useCase['summary'] }}</p>

                            <ul class="mt-4 space-y-1.5 border-t border-ink-950/5 pt-4">
                                @foreach ($useCase['tasks'] as $task)
                                    <li class="flex items-start gap-2 text-xs leading-6 text-ink-600">
                                        <x-icon name="check" class="mt-1 size-3 shrink-0 text-lime-dark" />
                                        {{ $task['title'] }}
                                    </li>
                                @endforeach
                            </ul>

                            <a href="{{ localized_route('use-cases.show', ['slug' => $slug]) }}" class="mt-5 inline-flex items-center gap-1.5 text-xs font-semibold text-ink-950 underline decoration-ink-950/20 underline-offset-4 transition hover:decoration-ink-950">
                                {{ __('ui.use_cases.learn_more') }}
                                <x-icon name="arrow-left" class="size-3.5 rtl:rotate-180" />
                            </a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach

    <section class="container-x pb-20 pt-12">
        <div class="rounded-3xl border border-ink-950/10 bg-paper-2/60 p-6 lg:p-8">
            <div class="flex flex-wrap items-center justify-between gap-5">
                <div>
                    <p class="text-lg font-semibold">{{ __('ui.use_cases.browse') }}</p>
                    <p class="mt-1 max-w-xl text-sm leading-7 text-ink-500">{{ __('ui.use_cases.subtitle') }}</p>
                </div>
                <a href="{{ localized_route('products') }}" class="btn btn-primary h-10 px-4">
                    {{ __('ui.nav.products') }}
                    <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
                </a>
            </div>
        </div>
    </section>
@endsection
