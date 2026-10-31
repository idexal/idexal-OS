@extends('layouts.public')

@section('title', __('ui.meta.title'))
@section('description', __('ui.meta.description'))

@push('schema')
    @php
        $softwareApplication = [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => config('idexal.brand.name'),
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Windows, macOS, Linux',
            'url' => localized_route('download'),
            'downloadUrl' => localized_route('download'),
            'softwareVersion' => config('idexal.version'),
            'description' => __('ui.meta.description'),
            'inLanguage' => str_replace('_', '-', app()->getLocale()),
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => strtoupper((string) config('idexal.billing.currency', 'USD')),
            ],
        ];

        $faqPage = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'inLanguage' => str_replace('_', '-', app()->getLocale()),
            'mainEntity' => collect(['free', 'desktop', 'models', 'billing', 'data', 'languages', 'availability'])
                ->map(fn (string $key): array => [
                    '@type' => 'Question',
                    'name' => __('ui.landing.faq.'.$key.'.q'),
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => __('ui.landing.faq.'.$key.'.a'),
                    ],
                ])
                ->values()
                ->all(),
        ];
    @endphp
    <script type="application/ld+json">{!! json_ld($softwareApplication) !!}</script>
    <script type="application/ld+json">{!! json_ld($faqPage) !!}</script>
@endpush

@section('content')
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 grid-lines opacity-60 [mask-image:linear-gradient(to_bottom,black,transparent_80%)]"></div>
        <div class="pointer-events-none absolute inset-0 hero-glow"></div>

        <div class="container-x relative grid items-center gap-14 pb-16 pt-14 lg:grid-cols-[1.05fr_0.95fr] lg:gap-10 lg:pb-24 lg:pt-20">
            <div class="reveal min-w-0">
                <span class="eyebrow">
                    <span class="dot bg-lime-dark"></span>
                    {{ __('ui.landing.eyebrow') }}
                </span>

                <h1 class="mt-6 max-w-2xl text-balance text-[2.6rem] font-semibold leading-[1.18] tracking-[-0.03em] sm:text-6xl lg:text-[4.15rem]">
                    {{ __('ui.landing.hero_title') }}
                </h1>

                <p class="mt-6 max-w-xl text-pretty text-base leading-8 text-ink-500 sm:text-lg sm:leading-9">
                    {{ __('ui.landing.hero_subtitle') }}
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a href="{{ localized_route('register') }}" class="btn btn-primary h-12 px-6 text-[15px]">
                        {{ __('ui.landing.hero_primary') }}
                        <x-icon name="arrow-up-right" class="size-4" />
                    </a>
                    <a href="{{ localized_route('docs') }}" class="btn btn-outline h-12 px-6 text-[15px]">
                        <x-icon name="book" class="size-4" />
                        {{ __('ui.landing.hero_secondary') }}
                    </a>
                </div>

                <p class="mt-5 flex items-start gap-2 text-xs leading-6 text-ink-400">
                    <x-icon name="check-circle" class="mt-1 size-4 text-lime-dark" />
                    {{ __('ui.landing.hero_note') }}
                </p>

                <div class="mt-10 grid max-w-xl grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-4">
                    @foreach ([
                        ['50+', __('ui.landing.stat_models')],
                        ['2', __('ui.landing.stat_gateway')],
                        ['∞', __('ui.landing.stat_teams')],
                        ['100%', __('ui.landing.stat_invoices')],
                    ] as [$value, $label])
                        <div class="border-s border-ink-950/10 ps-4 first:border-s-0 first:ps-0">
                            <p class="font-mono text-2xl font-medium tracking-tight text-ink-950" dir="ltr">{{ $value }}</p>
                            <p class="mt-0.5 text-[11px] leading-5 text-ink-400">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="reveal relative min-w-0">
                <div class="absolute -inset-4 rounded-[2.5rem] bg-lime/10 blur-2xl"></div>
                <div class="relative overflow-hidden rounded-[2rem] border border-ink-950/10 bg-ink-950 p-2 shadow-float">
                    <div class="flex items-center justify-between rounded-t-[1.4rem] border-b border-white/10 px-4 py-3">
                        <div class="flex items-center gap-1.5">
                            <span class="size-2.5 rounded-full bg-red-400/70"></span>
                            <span class="size-2.5 rounded-full bg-amber-300/70"></span>
                            <span class="size-2.5 rounded-full bg-lime/80"></span>
                        </div>
                        <span class="rounded-md border border-white/10 bg-white/5 px-2 py-1 font-mono text-[10px] text-paper/45" dir="ltr">{{ parse_url(url('/'), PHP_URL_HOST) }}/v1</span>
                    </div>

                    <div class="px-4 py-5 sm:px-5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 text-[11px] font-medium text-paper/50">
                                <span class="grid size-7 place-items-center rounded-lg bg-lime text-ink-950">
                                    <x-icon name="terminal" class="size-4" />
                                </span>
                                {{ __('ui.docs.chat') }}
                            </div>
                            <span class="badge border-lime/20 bg-lime/10 text-lime">200 OK</span>
                        </div>

                        <pre class="code-block mt-4 !border-0 !bg-transparent !p-0 !text-[11.5px] !leading-6"><span class="text-paper/35">$</span> curl {{ url('/v1/chat/completions') }} \
  <span class="text-paper/35">-H</span> <span class="text-lime-soft">"Authorization: Bearer ixo_live_…"</span> \
  <span class="text-paper/35">-H</span> <span class="text-lime-soft">"Content-Type: application/json"</span> \
  <span class="text-paper/35">-d</span> '{
    <span class="text-paper/35">"model"</span>: <span class="text-lime-soft">"gpt-4o-mini"</span>,
    <span class="text-paper/35">"messages"</span>: [
      { <span class="text-paper/35">"role"</span>: <span class="text-lime-soft">"user"</span>, <span class="text-paper/35">"content"</span>: <span class="text-lime-soft">"مرحبًا"</span> }
    ]
  }'</pre>

                        <div class="mt-4 rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                            <div class="flex items-center justify-between text-[10px] font-semibold uppercase tracking-[0.14em] text-paper/35">
                                <span>{{ __('ui.docs.response') }}</span>
                                <span class="font-mono normal-case tracking-normal text-lime">142 ms</span>
                            </div>
                            <pre class="mt-3 whitespace-pre-wrap font-mono text-[11.5px] leading-6 text-paper/75" dir="ltr">{
  "id": "chatcmpl_ixo_…",
  "model": "gpt-4o-mini",
  "choices": [{ "message": { "role": "assistant",
    "content": "أهلًا بك في idexal Os." } }],
  "usage": { "total_tokens": 42 }
}</pre>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-2">
                            @foreach ([
                                ['zap', __('ui.dashboard.stats.requests'), '1,284'],
                                ['chart', __('ui.dashboard.stats.tokens'), '84.2k'],
                                ['clock', __('ui.common.latency'), '142ms'],
                            ] as [$icon, $label, $value])
                                <div class="rounded-xl border border-white/10 bg-white/[0.03] p-3">
                                    <x-icon :name="$icon" class="size-3.5 text-lime" />
                                    <p class="mt-2 font-mono text-sm text-paper" dir="ltr">{{ $value }}</p>
                                    <p class="mt-0.5 text-[9px] text-paper/35">{{ $label }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="absolute -bottom-5 -start-5 hidden rounded-2xl border border-ink-950/10 bg-white p-3.5 shadow-float sm:block">
                    <div class="flex items-center gap-2.5">
                        <span class="grid size-9 place-items-center rounded-xl bg-lime/25 text-ink-950">
                            <x-icon name="shield" class="size-5" />
                        </span>
                        <div>
                            <p class="text-[11px] font-semibold">{{ __('ui.landing.features.security.title') }}</p>
                            <p class="text-[10px] text-ink-400">{{ __('ui.landing.features.security.body') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-y border-ink-950/10 bg-white/50">
            <div class="container-x flex flex-col items-start justify-between gap-4 py-5 sm:flex-row sm:items-center">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-400">{{ __('ui.landing.trusted') }}</p>
                <div class="flex flex-wrap items-center gap-x-8 gap-y-3 font-mono text-sm font-semibold text-ink-500" dir="ltr">
                    <span>OpenAI</span>
                    <span>Anthropic</span>
                    <span>Gemini</span>
                    <span>OpenRouter</span>
                    <span>Groq</span>
                    <span>DeepSeek</span>
                </div>
            </div>
        </div>
    </section>

    {{-- The gated, unified platform: one signed-in account across desktop, web, and gateway, with an operator-controlled catalog. --}}
<section id="platform" class="container-x scroll-mt-24 py-20 lg:py-28">
    <div class="max-w-3xl">
        <span class="eyebrow">
            <span class="dot bg-lime-dark"></span>
            {{ __('ui.landing.unified.eyebrow') }}
        </span>
        <h2 class="mt-5 text-balance text-3xl font-semibold tracking-tight sm:text-4xl lg:text-[2.75rem] lg:leading-[1.2]">{{ __('ui.landing.unified.title') }}</h2>
        <p class="mt-4 max-w-2xl text-base leading-8 text-ink-500">{{ __('ui.landing.unified.body') }}</p>
    </div>

    <div class="mt-12 grid gap-4 md:grid-cols-3">
        @foreach ([
            ['lock', 'gated'],
            ['layers', 'controlled'],
            ['user', 'connected'],
        ] as [$icon, $feature])
            <article class="group reveal card card-pad transition duration-300 hover:-translate-y-0.5 hover:shadow-float">
                <span class="inline-flex size-11 items-center justify-center rounded-2xl bg-ink-950 text-lime transition group-hover:bg-lime group-hover:text-ink-950">
                    <x-icon :name="$icon" class="size-5" />
                </span>
                <h3 class="mt-5 text-lg font-semibold">{{ __('ui.landing.unified.'.$feature.'.title') }}</h3>
                <p class="mt-2 text-sm leading-7 text-ink-500">{{ __('ui.landing.unified.'.$feature.'.body') }}</p>
            </article>
        @endforeach
    </div>
</section>

<section id="product" class="container-x scroll-mt-24 py-20 lg:py-28">
        <div class="max-w-3xl">
            <span class="eyebrow">{{ __('ui.landing.features_title') }}</span>
            <h2 class="mt-5 text-balance text-3xl font-semibold tracking-tight sm:text-4xl lg:text-[2.75rem] lg:leading-[1.2]">{{ __('ui.landing.features_subtitle') }}</h2>
        </div>

        <div class="mt-12 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ([
                ['desktop', 'monitor'],
                ['gateway', 'zap'],
                ['teams', 'users'],
                ['billing', 'card'],
                ['security', 'shield'],
                ['arabic', 'globe'],
            ] as $index => [$feature, $icon])
                <article class="group reveal card card-pad transition duration-300 hover:-translate-y-0.5 hover:shadow-float {{ $index === 0 ? 'md:col-span-2 xl:col-span-1' : '' }}">
                    <span class="inline-flex size-11 items-center justify-center rounded-2xl bg-ink-950 text-lime transition group-hover:bg-lime group-hover:text-ink-950">
                        <x-icon :name="$icon" class="size-5" />
                    </span>
                    <h3 class="mt-5 text-lg font-semibold">{{ __('ui.landing.features.'.$feature.'.title') }}</h3>
                    <p class="mt-2 text-sm leading-7 text-ink-500">{{ __('ui.landing.features.'.$feature.'.body') }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="bg-paper-2/60 py-16 lg:py-20">
        <div class="container-x">
            <div class="max-w-3xl">
                <span class="eyebrow">{{ __('ui.landing.global_eyebrow') }}</span>
                <h2 class="mt-5 text-balance text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('ui.landing.global_title') }}</h2>
                <p class="mt-4 text-base leading-8 text-ink-500">{{ __('ui.landing.global_body') }}</p>
            </div>

            <dl class="mt-10 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach (['languages', 'platforms', 'deployment', 'data'] as $item)
                    <div class="rounded-3xl border border-ink-950/10 bg-white p-6">
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __('ui.landing.global.'.$item.'.label') }}</dt>
                        <dd class="mt-3 text-lg font-semibold text-ink-950">{{ __('ui.landing.global.'.$item.'.value') }}</dd>
                        <dd class="mt-1.5 text-xs leading-6 text-ink-500">{{ __('ui.landing.global.'.$item.'.body') }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    <section class="container-x py-16 lg:py-20">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="max-w-2xl">
                <span class="eyebrow">{{ __('ui.landing.explore_eyebrow') }}</span>
                <h2 class="mt-5 text-balance text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('ui.landing.explore_title') }}</h2>
                <p class="mt-3 text-base leading-8 text-ink-500">{{ __('ui.landing.explore_body') }}</p>
            </div>
            <a href="{{ localized_route('resources') }}" class="btn btn-outline h-11 px-6">{{ __('ui.landing.explore_all') }}</a>
        </div>

        <div class="mt-10 grid gap-4 md:grid-cols-3">
            @foreach ([
                ['route' => 'use-cases', 'icon' => 'users', 'title' => __('ui.landing.explore_use_cases_title'), 'body' => __('ui.landing.explore_use_cases_body'), 'link' => __('ui.landing.explore_use_cases_link')],
                ['route' => 'integrations', 'icon' => 'layers', 'title' => __('ui.landing.explore_catalogue_title'), 'body' => __('ui.landing.explore_catalogue_body'), 'link' => __('ui.landing.explore_catalogue_link')],
                ['route' => 'docs', 'icon' => 'book', 'title' => __('ui.landing.explore_docs_title'), 'body' => __('ui.landing.explore_docs_body'), 'link' => __('ui.landing.explore_docs_link')],
            ] as $card)
                <a href="{{ localized_route($card['route']) }}" class="card card-pad group flex flex-col">
                    <span class="inline-flex size-10 items-center justify-center rounded-xl bg-lime/20 text-ink-950">
                        <x-icon :name="$card['icon']" class="size-5" />
                    </span>
                    <h3 class="mt-4 font-semibold">{{ $card['title'] }}</h3>
                    <p class="mt-1.5 flex-1 text-sm leading-7 text-ink-500">{{ $card['body'] }}</p>
                    <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-950">
                        {{ $card['link'] }}
                        <x-icon name="arrow-right" class="size-4 transition group-hover:translate-x-0.5" />
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    <section id="gateway" class="scroll-mt-24 bg-ink-950 py-20 text-paper lg:py-28">
        <div class="container-x grid items-center gap-14 lg:grid-cols-2">
            <div class="reveal min-w-0">
                <span class="eyebrow border-white/15 bg-white/5 text-paper/60">
                    <span class="dot bg-lime"></span>
                    {{ __('ui.nav.gateway') }}
                </span>
                <h2 class="mt-5 text-balance text-3xl font-semibold tracking-tight sm:text-4xl lg:text-[2.75rem] lg:leading-[1.2]">{{ __('ui.landing.gateway_title') }}</h2>
                <p class="mt-5 text-base leading-8 text-paper/55">{{ __('ui.landing.gateway_subtitle') }}</p>

                <div class="mt-8 space-y-4">
                    @foreach ([
                        __('ui.docs.chat'),
                        __('ui.docs.messages'),
                        __('ui.docs.models'),
                        __('ui.docs.streaming'),
                    ] as $item)
                        <div class="flex items-center gap-3 border-b border-white/10 pb-4 text-sm text-paper/65">
                            <span class="grid size-6 place-items-center rounded-full bg-lime/15 text-lime">
                                <x-icon name="check" class="size-3.5" />
                            </span>
                            {{ $item }}
                        </div>
                    @endforeach
                </div>

                <p class="mt-6 text-xs leading-6 text-paper/35">{{ __('ui.landing.gateway_note') }}</p>

                <a href="{{ localized_route('providers') }}" class="btn mt-7 bg-lime text-ink-950 hover:bg-lime-bright">
                    {{ __('ui.providers.see_models') }}
                    <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
                </a>
            </div>

            <div class="reveal space-y-3 min-w-0">
                <div class="overflow-hidden rounded-3xl border border-white/10 bg-white/[0.03]">
                    <div class="flex items-center justify-between border-b border-white/10 px-5 py-4">
                        <span class="font-mono text-xs text-paper/45" dir="ltr">POST /v1/chat/completions</span>
                        <span class="badge border-white/10 bg-white/5 text-paper/60">OpenAI</span>
                    </div>
                    <pre class="code-block !rounded-none !border-0 !bg-transparent"><span class="text-paper/35">from</span> openai <span class="text-paper/35">import</span> OpenAI

client = OpenAI(
  base_url=<span class="text-lime-soft">"{{ url('/v1') }}"</span>,
  api_key=<span class="text-lime-soft">"ixo_live_…"</span>,
)

stream = client.chat.completions.create(
  model=<span class="text-lime-soft">"claude-sonnet-4"</span>,
  messages=[{<span class="text-paper/35">"role"</span>: <span class="text-lime-soft">"user"</span>,
             <span class="text-paper/35">"content"</span>: <span class="text-lime-soft">"اكتب خطة إطلاق"</span>}],
  stream=<span class="text-lime">True</span>,
)</pre>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                        <x-icon name="layers" class="size-5 text-lime" />
                        <p class="mt-3 text-sm font-semibold">Anthropic</p>
                        <p class="mt-1 font-mono text-[11px] text-paper/40" dir="ltr">POST /v1/messages</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                        <x-icon name="sparkles" class="size-5 text-lime" />
                        <p class="mt-3 text-sm font-semibold">Streaming</p>
                        <p class="mt-1 font-mono text-[11px] text-paper/40" dir="ltr">text/event-stream</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="container-x py-20 lg:py-28">
        <div class="max-w-2xl">
            <span class="eyebrow">{{ __('ui.landing.how_title') }}</span>
            <h2 class="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('ui.landing.how_subtitle') }}</h2>
        </div>

        <div class="mt-12 grid gap-4 md:grid-cols-3">
            @foreach ([
                ['account', '01'],
                ['key', '02'],
                ['call', '03'],
            ] as [$step, $number])
                <div class="reveal card card-pad">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-3xl font-medium text-ink-200" dir="ltr">{{ $number }}</span>
                        <span class="grid size-9 place-items-center rounded-xl bg-lime/20 text-ink-950">
                            <x-icon :name="['account' => 'user', 'key' => 'key', 'call' => 'terminal'][$step]" class="size-4" />
                        </span>
                    </div>
                    <h3 class="mt-6 text-lg font-semibold">{{ __('ui.landing.steps.'.$step.'.title') }}</h3>
                    <p class="mt-2 text-sm leading-7 text-ink-500">{{ __('ui.landing.steps.'.$step.'.body') }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="container-x py-10 lg:py-14">
        <div class="grid items-center gap-10 rounded-[2rem] border border-ink-950/10 bg-white p-6 shadow-card sm:p-10 lg:grid-cols-[0.9fr_1.1fr]">
            <div class="reveal min-w-0">
                <span class="eyebrow">
                    <span class="dot bg-lime-dark"></span>
                    {{ __('ui.landing.demo_eyebrow') }}
                </span>
                <h2 class="mt-5 text-balance text-3xl font-semibold tracking-tight sm:text-4xl">
                    {{ __('ui.landing.demo_title') }}
                </h2>
                <p class="mt-4 text-pretty text-sm leading-7 text-ink-500 sm:text-base sm:leading-8">
                    {{ __('ui.landing.demo_body') }}
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ localized_route('download') }}" class="btn btn-primary h-11 px-5">
                        <x-icon name="download" class="size-4" />
                        {{ __('ui.nav.download') }}
                    </a>
                    <a href="{{ localized_route('changelog') }}" class="btn btn-outline h-11 px-5">
                        {{ __('ui.nav.changelog') }}
                        <x-icon name="arrow-up-right" class="size-4" />
                    </a>
                </div>
            </div>

            <figure class="reveal min-w-0">
                <video
                    class="w-full rounded-2xl border border-ink-950/10 shadow-float"
                    width="800"
                    height="555"
                    poster="{{ asset('brand/app-demo-poster.jpg') }}"
                    muted
                    loop
                    playsinline
                    preload="none"
                    data-autoplay-loop
                    aria-label="{{ __('ui.landing.demo_alt') }}"
                >
                    <source src="{{ asset('brand/app-demo.webm') }}" type="video/webm">
                    <source src="{{ asset('brand/app-demo.mp4') }}" type="video/mp4">
                </video>
                <figcaption class="mt-3 text-xs leading-6 text-ink-400">{{ __('ui.landing.demo_note') }}</figcaption>
            </figure>
        </div>
    </section>

    <section class="bg-paper-2/60 py-20 lg:py-28">
        <div class="container-x">
            <div class="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
                <div class="max-w-2xl">
                    <span class="eyebrow">{{ __('ui.landing.pricing_title') }}</span>
                    <h2 class="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('ui.landing.pricing_subtitle') }}</h2>
                </div>
                <a href="{{ localized_route('pricing') }}" class="btn btn-outline">
                    {{ __('ui.nav.pricing') }}
                    <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
                </a>
            </div>

            <div class="mt-10 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @forelse ($plans as $plan)
                    <article class="relative flex flex-col rounded-3xl border {{ $plan->slug === 'pro' ? 'border-ink-950 bg-ink-950 text-paper' : 'border-ink-950/10 bg-white text-ink-950' }} p-6">
                        @if ($plan->slug === 'pro')
                            <span class="absolute -top-3 start-6 badge bg-lime text-ink-950">{{ __('ui.pricing.popular') }}</span>
                        @endif
                        <h3 class="text-lg font-semibold">{{ $plan->name }}</h3>
                        <p class="mt-1 min-h-10 text-xs leading-6 {{ $plan->slug === 'pro' ? 'text-paper/50' : 'text-ink-400' }}">{{ $plan->description }}</p>
                        <p class="mt-6 flex items-baseline gap-1.5">
                            <span class="font-mono text-3xl font-medium tracking-tight">{{ $plan->formattedPrice('monthly') }}</span>
                            @if (! $plan->isFree())
                                <span class="text-xs {{ $plan->slug === 'pro' ? 'text-paper/40' : 'text-ink-400' }}">/ {{ __('ui.common.monthly') }}</span>
                            @endif
                        </p>

                        <div class="my-6 h-px {{ $plan->slug === 'pro' ? 'bg-white/10' : 'bg-ink-950/10' }}"></div>

                        <ul class="flex-1 space-y-3 text-sm {{ $plan->slug === 'pro' ? 'text-paper/65' : 'text-ink-600' }}">
                            @foreach ([
                                [__('ui.common.requests'), $plan->limit(\App\Models\Plan::LIMIT_REQUESTS)],
                                [__('ui.common.tokens'), $plan->limit(\App\Models\Plan::LIMIT_TOKENS)],
                                [__('ui.common.seats'), $plan->limit(\App\Models\Plan::LIMIT_SEATS)],
                                [__('ui.common.api_keys'), $plan->limit(\App\Models\Plan::LIMIT_API_KEYS)],
                            ] as [$label, $limit])
                                <li class="flex items-center justify-between gap-3">
                                    <span>{{ $label }}</span>
                                    <span class="font-mono text-xs">{{ $limit === null ? __('ui.common.unlimited') : compact_number($limit) }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <a href="{{ auth()->check() ? localized_route('dashboard.billing') : localized_route('register') }}" class="btn mt-6 w-full {{ $plan->slug === 'pro' ? 'bg-lime text-ink-950 hover:bg-lime-bright' : 'btn-outline' }}">
                            {{ $plan->isFree() ? __('ui.common.get_started') : __('ui.pricing.choose_plan') }}
                        </a>
                    </article>
                @empty
                    <p class="col-span-full rounded-2xl border border-dashed border-ink-300 p-8 text-center text-sm text-ink-400">{{ __('ui.pricing.no_plans') }}</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="container-x py-20 lg:py-28">
        <div class="grid gap-12 lg:grid-cols-[0.8fr_1.2fr]">
            <div class="min-w-0">
                <span class="eyebrow">{{ __('ui.landing.faq_eyebrow') }}</span>
                <h2 class="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('ui.landing.faq_title') }}</h2>
                <p class="mt-4 text-sm leading-7 text-ink-500">{{ __('ui.contact.response_time') }}</p>
                <a href="{{ localized_route('contact') }}" class="btn btn-outline mt-6">
                    <x-icon name="message" class="size-4" />
                    {{ __('ui.nav.contact') }}
                </a>
            </div>

            <div class="divide-y divide-ink-950/10 border-y border-ink-950/10 min-w-0">
                @foreach (['free', 'desktop', 'models', 'billing', 'data', 'languages', 'availability'] as $faq)
                    <details class="group py-5">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-base font-semibold">
                            {{ __('ui.landing.faq.'.$faq.'.q') }}
                            <span class="grid size-7 shrink-0 place-items-center rounded-full border border-ink-950/10 text-ink-400 transition group-open:rotate-45 group-open:border-ink-950 group-open:bg-ink-950 group-open:text-paper">
                                <x-icon name="plus" class="size-3.5" />
                            </span>
                        </summary>
                        <p class="mt-3 max-w-2xl text-sm leading-7 text-ink-500">{{ __('ui.landing.faq.'.$faq.'.a') }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <section class="container-x pb-8">
        <div class="relative overflow-hidden rounded-[2rem] mesh-dark px-6 py-14 text-paper sm:px-12 lg:px-16 lg:py-20">
            <div class="pointer-events-none absolute inset-0 grid-lines opacity-[0.12]"></div>
            <div class="relative flex flex-col items-start justify-between gap-8 lg:flex-row lg:items-center">
                <div class="max-w-2xl">
                    <h2 class="text-balance text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('ui.landing.cta_title') }}</h2>
                    <p class="mt-3 text-sm leading-7 text-paper/55">{{ __('ui.landing.cta_subtitle') }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ localized_route('register') }}" class="btn btn-lime h-12 px-6">
                        {{ __('ui.landing.hero_primary') }}
                        <x-icon name="arrow-up-right" class="size-4" />
                    </a>
                    <a href="{{ localized_route('contact') }}" class="btn h-12 border border-white/15 px-6 text-paper hover:bg-white/5">
                        {{ __('ui.nav.contact') }}
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
