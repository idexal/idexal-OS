@extends('layouts.public')

@section('title', __('ui.developers.title').' — '.config('idexal.brand.name'))
@section('description', __('ui.developers.subtitle'))

@section('content')
    @php($baseUrl = rtrim(url('/v1'), '/'))

    <section class="relative overflow-hidden border-b border-ink-950/10">
        <div class="pointer-events-none absolute inset-0 grid-lines opacity-50 [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>
        <div class="container-x relative py-16 lg:py-24">
            <span class="eyebrow">
                <span class="dot bg-lime-dark"></span>
                {{ __('ui.developers.eyebrow') }}
            </span>
            <h1 class="mt-6 max-w-3xl text-balance text-4xl font-semibold tracking-[-0.03em] sm:text-5xl lg:text-6xl">
                {{ __('ui.developers.title') }}
            </h1>
            <p class="mt-5 max-w-2xl text-pretty text-base leading-8 text-ink-500">
                {{ __('ui.developers.subtitle') }}
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ localized_route('dashboard.api-keys.index') }}" class="btn btn-primary h-11 px-6">
                    <x-icon name="key" class="size-4" />
                    {{ __('ui.developers.cta_keys') }}
                </a>
                <a href="{{ localized_route('api') }}" class="btn btn-outline h-11 px-6">
                    {{ __('ui.developers.cta_reference') }}
                    <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
                </a>
            </div>
        </div>
    </section>

    <section class="container-x py-16 lg:py-20">
        <div class="grid gap-10 lg:grid-cols-[1.1fr_1fr] lg:items-start">
            <div class="min-w-0">
                <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.developers.quickstart_title') }}</h2>
                <p class="mt-3 max-w-xl text-sm leading-7 text-ink-500">{{ __('ui.developers.quickstart_body') }}</p>

                <ol class="mt-8 space-y-5">
                    @foreach ([1, 2, 3] as $step)
                        <li class="flex gap-4">
                            <span class="mt-0.5 inline-flex size-8 shrink-0 items-center justify-center rounded-xl bg-lime/25 font-mono text-sm font-semibold text-ink-950">{{ $step }}</span>
                            <div>
                                <p class="text-sm font-semibold text-ink-950">{{ __('ui.developers.step_'.$step.'_title') }}</p>
                                <p class="mt-1 text-sm leading-7 text-ink-500">{{ __('ui.developers.step_'.$step.'_body') }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="min-w-0 overflow-hidden rounded-3xl border border-ink-950/10 bg-ink-950 shadow-float">
                <div class="flex items-center justify-between border-b border-white/10 px-5 py-3.5">
                    <span class="font-mono text-[11px] text-paper/45" dir="ltr">{{ $baseUrl }}/chat/completions</span>
                    <button
                        type="button"
                        class="text-paper/40 transition hover:text-paper"
                        x-data="copyButton(@js($baseUrl.'/chat/completions'))"
                        @click="copy()"
                        aria-label="{{ __('ui.docs.copy') }}"
                    >
                        <x-icon name="copy" class="size-4" />
                    </button>
                </div>
                <pre class="code-block !m-0 !rounded-none !border-0 !bg-transparent text-[12px]">curl <span class="text-lime-soft">{{ $baseUrl }}/chat/completions</span> \
  -H "Authorization: Bearer <span class="text-lime-soft">ixo_live_…</span>" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "gpt-4o-mini",
    "messages": [
      { "role": "user", "content": "Summarise this pull request" }
    ]
  }'</pre>
            </div>
        </div>
    </section>

    <section class="border-y border-ink-950/10 bg-white/60 py-16 lg:py-20">
        <div class="container-x">
            <div class="max-w-2xl">
                <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.developers.surface_title') }}</h2>
                <p class="mt-2 text-sm leading-7 text-ink-500">{{ __('ui.developers.surface_body') }}</p>
            </div>

            <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (__('ui.developers.cards') as $card)
                    <a href="{{ localized_route($card['route']) }}" class="card card-pad group flex flex-col">
                        <span class="inline-flex size-10 items-center justify-center rounded-xl bg-lime/20 text-ink-950">
                            <x-icon :name="$card['icon']" class="size-5" />
                        </span>
                        <h3 class="mt-4 font-semibold">{{ $card['title'] }}</h3>
                        <p class="mt-1.5 flex-1 text-sm leading-7 text-ink-500">{{ $card['body'] }}</p>
                        <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-950">
                            {{ $card['label'] }}
                            <x-icon name="arrow-right" class="size-4 transition group-hover:translate-x-0.5 rtl:rotate-180" />
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="container-x py-16 lg:py-24">
        <div class="rounded-3xl bg-ink-950 px-8 py-12 text-paper sm:px-12 lg:flex lg:items-center lg:justify-between lg:gap-12">
            <div class="max-w-2xl">
                <h2 class="text-balance text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.developers.closing_title') }}</h2>
                <p class="mt-3 text-sm leading-7 text-paper/55">{{ __('ui.developers.closing_body') }}</p>
            </div>
            <div class="mt-7 flex flex-wrap gap-3 lg:mt-0 lg:shrink-0">
                <a href="{{ localized_route('register') }}" class="btn bg-lime text-ink-950 hover:bg-lime-bright">{{ __('ui.developers.closing_cta') }}</a>
                <a href="{{ localized_route('contact') }}" class="btn border border-white/15 text-paper hover:bg-white/5">{{ __('ui.nav.contact') }}</a>
            </div>
        </div>
    </section>
@endsection
