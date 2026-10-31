@extends('layouts.public')

@section('title', $title.' — '.config('idexal.brand.name'))
@section('description', $intro)

@section('content')
    <section class="border-b border-ink-950/10 bg-white/50">
        <div class="container-x py-12 lg:py-16">
            <a href="{{ localized_route('home') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-400 transition hover:text-ink-950">
                <x-icon name="arrow-right" class="size-3.5 rtl:rotate-180" />
                {{ __('ui.common.back') }}
            </a>
            <h1 class="mt-4 text-balance text-4xl font-semibold tracking-tight sm:text-5xl">{{ $title }}</h1>
            <p class="mt-4 max-w-3xl text-sm leading-7 text-ink-500">{{ $intro }}</p>
            <p class="mt-4 text-[11px] text-ink-400">{{ __('ui.legal.updated') }}: {{ now()->translatedFormat('F Y') }}</p>
        </div>
    </section>

    <div class="container-x grid gap-12 py-12 lg:grid-cols-[240px_1fr] lg:py-16">
        <aside class="hidden lg:block min-w-0">
            <nav class="sticky top-28 space-y-0.5" aria-label="{{ __('ui.legal.terms_title') }}">
                @foreach ($sections as $index => $section)
                    <a href="#section-{{ $index + 1 }}" class="block border-s-2 border-ink-950/10 py-2 ps-4 text-sm text-ink-500 transition hover:border-ink-950 hover:text-ink-950">
                        {{ $section['title'] }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <article class="prose-body max-w-3xl min-w-0">
            @foreach ($sections as $index => $section)
                <section id="section-{{ $index + 1 }}" class="scroll-mt-28 {{ $index > 0 ? 'mt-10 border-t border-ink-950/10 pt-10' : '' }}">
                    <div class="flex items-baseline gap-3">
                        <span class="font-mono text-xs text-ink-300" dir="ltr">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <h2 class="!mt-0">{{ $section['title'] }}</h2>
                    </div>
                    <div class="mt-3 space-y-3">
                        @foreach (preg_split('/\r\n|\r|\n/', (string) $section['body']) as $paragraph)
                            @if (trim($paragraph) !== '')
                                <p>{{ $paragraph }}</p>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div class="mt-12 rounded-2xl border border-ink-950/10 bg-white p-6">
                <p class="text-sm font-semibold text-ink-950">{{ __('ui.contact.support') }}</p>
                <p class="mt-1 text-sm text-ink-500">{{ __('ui.contact.response_time') }}</p>
                <a href="mailto:{{ config('idexal.brand.support_email') }}" class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-ink-950">
                    <x-icon name="mail" class="size-4" />
                    <span dir="ltr">{{ config('idexal.brand.support_email') }}</span>
                </a>
            </div>
        </article>
    </div>
@endsection
