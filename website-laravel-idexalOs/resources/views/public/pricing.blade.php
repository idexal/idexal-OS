@extends('layouts.public')

@section('title', __('ui.pricing.title').' — '.config('idexal.brand.name'))
@section('description', __('ui.pricing.subtitle'))

@section('content')
    <section class="relative overflow-hidden border-b border-ink-950/10">
        <div class="pointer-events-none absolute inset-0 grid-lines opacity-50 [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>
        <div class="container-x relative py-16 text-center lg:py-24">
            <span class="eyebrow">
                <span class="dot bg-lime-dark"></span>
                {{ __('ui.nav.pricing') }}
            </span>
            <h1 class="mx-auto mt-6 max-w-3xl text-balance text-4xl font-semibold tracking-[-0.03em] sm:text-5xl lg:text-6xl">
                {{ __('ui.pricing.title') }}
            </h1>
            <p class="mx-auto mt-5 max-w-2xl text-pretty text-base leading-8 text-ink-500">
                {{ __('ui.pricing.subtitle') }}
            </p>

            <div class="mt-8 inline-flex rounded-2xl border border-ink-950/10 bg-white p-1 shadow-sm">
                <a
                    href="{{ localized_route('pricing') }}"
                    class="rounded-xl px-5 py-2.5 text-sm font-semibold transition {{ ! $yearly ? 'bg-ink-950 text-paper' : 'text-ink-500 hover:text-ink-950' }}"
                >
                    {{ __('ui.pricing.monthly') }}
                </a>
                <a
                    href="{{ localized_route('pricing', ['yearly' => 1]) }}"
                    class="flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold transition {{ $yearly ? 'bg-ink-950 text-paper' : 'text-ink-500 hover:text-ink-950' }}"
                >
                    {{ __('ui.pricing.yearly') }}
                    <span class="rounded-full bg-lime px-2 py-0.5 text-[10px] font-semibold text-ink-950">{{ __('ui.pricing.save') }}</span>
                </a>
            </div>
        </div>
    </section>

    <section class="container-x py-14 lg:py-20">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @forelse ($plans as $plan)
                @php
                    $highlight = $plan->slug === 'pro';
                    $price = $plan->formattedPrice($yearly ? 'yearly' : 'monthly');
                @endphp

                <article class="relative flex flex-col rounded-3xl border {{ $highlight ? 'border-ink-950 bg-ink-950 text-paper shadow-float xl:-mt-3 xl:mb-3' : 'border-ink-950/10 bg-white text-ink-950 shadow-card' }} p-6">
                    @if ($highlight)
                        <span class="absolute -top-3 start-6 badge bg-lime text-ink-950">{{ __('ui.pricing.popular') }}</span>
                    @endif

                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-xl font-semibold">{{ $plan->name }}</h2>
                        @if ($plan->isFree())
                            <span class="badge {{ $highlight ? 'border-white/10 bg-white/5 text-paper/60' : 'badge-gray' }}">{{ __('ui.pricing.free') }}</span>
                        @endif
                    </div>
                    <p class="mt-2 min-h-12 text-xs leading-6 {{ $highlight ? 'text-paper/50' : 'text-ink-400' }}">{{ $plan->description }}</p>

                    <div class="mt-6">
                        <p class="flex items-baseline gap-1.5">
                            <span class="font-mono text-4xl font-medium tracking-tight">{{ $price }}</span>
                            @if (! $plan->isFree())
                                <span class="text-xs {{ $highlight ? 'text-paper/40' : 'text-ink-400' }}">/ {{ $yearly ? __('ui.common.yearly') : __('ui.common.monthly') }}</span>
                            @endif
                        </p>
                        @if ($yearly && ! $plan->isFree())
                            <p class="mt-1 text-[11px] {{ $highlight ? 'text-lime' : 'text-lime-dark' }}">{{ __('ui.pricing.save') }}</p>
                        @endif
                    </div>

                    <a href="{{ auth()->check() ? localized_route('dashboard.billing') : localized_route('register') }}" class="btn mt-6 w-full {{ $highlight ? 'bg-lime text-ink-950 hover:bg-lime-bright' : 'btn-outline' }}">
                        {{ $plan->isFree() ? __('ui.common.get_started') : __('ui.pricing.choose_plan') }}
                    </a>

                    <div class="my-6 h-px {{ $highlight ? 'bg-white/10' : 'bg-ink-950/10' }}"></div>

                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] {{ $highlight ? 'text-paper/35' : 'text-ink-400' }}">{{ __('ui.pricing.limits') }}</p>
                    <ul class="mt-3 space-y-3 text-sm {{ $highlight ? 'text-paper/70' : 'text-ink-600' }}">
                        @foreach ([
                            [__('ui.pricing.limit_requests'), $plan->limit(\App\Models\Plan::LIMIT_REQUESTS)],
                            [__('ui.pricing.limit_tokens'), $plan->limit(\App\Models\Plan::LIMIT_TOKENS)],
                            [__('ui.pricing.limit_seats'), $plan->limit(\App\Models\Plan::LIMIT_SEATS)],
                            [__('ui.pricing.limit_keys'), $plan->limit(\App\Models\Plan::LIMIT_API_KEYS)],
                            [__('ui.pricing.limit_proxy_profiles'), $plan->limit(\App\Models\Plan::LIMIT_PROXY_PROFILES)],
                            [__('ui.pricing.limit_proxy_bandwidth'), $plan->limit(\App\Models\Plan::LIMIT_PROXY_BANDWIDTH_GB)],
                        ] as [$label, $limit])
                            <li class="flex items-center justify-between gap-3">
                                <span>{{ $label }}</span>
                                <span class="font-mono text-xs {{ $highlight ? 'text-lime' : 'text-ink-950' }}">
                                    {{ $limit === null ? __('ui.pricing.unlimited') : compact_number($limit) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-6 space-y-2.5 border-t pt-5 text-xs {{ $highlight ? 'border-white/10 text-paper/55' : 'border-ink-950/10 text-ink-500' }}">
                        @foreach ([
                            ['api' => $plan->hasFeature('api_access')],
                            ['team' => $plan->limit(\App\Models\Plan::LIMIT_SEATS) === null || $plan->limit(\App\Models\Plan::LIMIT_SEATS) > 1],
                            ['webhooks' => $plan->hasFeature('webhooks')],
                            ['support' => $plan->hasFeature('priority_support')],
                            ['sso' => $plan->hasFeature('sso')],
                            ['proxy' => $plan->hasFeature(\App\Models\Plan::FEATURE_PROXY)],
                            ['proxy_rotation' => $plan->hasFeature(\App\Models\Plan::FEATURE_PROXY_ROTATION)],
                            ['proxy_fingerprint' => $plan->hasFeature(\App\Models\Plan::FEATURE_PROXY_FINGERPRINT)],
                            ['browser_prewarm' => $plan->hasFeature(\App\Models\Plan::FEATURE_BROWSER_PREWARM)],
                            ['custom_providers' => $plan->hasFeature(\App\Models\Plan::FEATURE_CUSTOM_PROVIDERS)],
                            ['desktop_access' => $plan->hasFeature(\App\Models\Plan::FEATURE_DESKTOP_ACCESS)],
                        ] as $feature)
                            @foreach ($feature as $key => $enabled)
                                <div class="flex items-center gap-2 {{ $enabled ? '' : 'opacity-40' }}">
                                    <x-icon :name="$enabled ? 'check' : 'minus'" class="size-3.5" />
                                    <span>{{ __('ui.pricing.feature_'.$key) }}</span>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </article>
            @empty
                <p class="col-span-full rounded-3xl border border-dashed border-ink-300 bg-white p-10 text-center text-sm text-ink-400">
                    {{ __('ui.pricing.no_plans') }}
                </p>
            @endforelse
        </div>

        <p class="mt-8 text-center text-xs leading-6 text-ink-400">{{ __('ui.pricing.note') }}</p>
    </section>

    <section id="compare" class="border-t border-ink-950/10 bg-white/60 py-16 lg:py-20">
        <div class="container-x">
            <div class="mx-auto max-w-2xl text-center">
                <span class="eyebrow">
                    <span class="dot bg-lime-dark"></span>
                    {{ __('ui.pricing.compare_eyebrow') }}
                </span>
                <h2 class="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('ui.pricing.compare_title') }}</h2>
                <p class="mt-3 text-sm leading-7 text-ink-500">{{ __('ui.pricing.compare_subtitle') }}</p>
            </div>

            <div class="mt-10 overflow-x-auto rounded-3xl border border-ink-950/10 bg-white shadow-card">
                <table class="w-full min-w-[720px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-ink-950/10 bg-paper-2/60">
                            <th scope="col" class="sticky start-0 z-10 bg-paper-2/95 px-5 py-4 text-start text-xs font-semibold uppercase tracking-[0.14em] text-ink-400">
                                {{ __('ui.pricing.compare_capability') }}
                            </th>
                            @foreach ($plans as $plan)
                                <th scope="col" class="px-5 py-4 text-center">
                                    <span class="block text-base font-semibold">{{ $plan->name }}</span>
                                    <span class="mt-0.5 block font-mono text-[11px] text-ink-400">
                                        {{ $plan->isFree() ? __('ui.pricing.free') : $plan->formattedPrice('monthly') }}
                                    </span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($comparison as $row)
                            @if ($loop->first || $comparison[$loop->index - 1]['group'] !== $row['group'])
                                <tr class="bg-ink-950/[0.03]">
                                    <th scope="colgroup" colspan="{{ $plans->count() + 1 }}" class="px-5 py-2 text-start text-[10px] font-semibold uppercase tracking-[0.18em] text-ink-400">
                                        {{ $row['group'] === 'limits' ? __('ui.pricing.group_limits') : ($row['group'] === 'features' ? __('ui.pricing.group_features') : __('ui.pricing.group_price')) }}
                                    </th>
                                </tr>
                            @endif
                            <tr class="border-b border-ink-950/5 last:border-0 hover:bg-lime/[0.06]">
                                <th scope="row" class="sticky start-0 z-10 bg-white px-5 py-3.5 text-start font-medium text-ink-700">{{ $row['label'] }}</th>
                                @foreach ($row['cells'] as $cell)
                                    <td class="px-5 py-3.5 text-center">
                                        @if ($cell['kind'] === 'yes')
                                            <x-icon name="check" class="mx-auto size-4 text-lime-dark" />
                                        @elseif ($cell['kind'] === 'no')
                                            <x-icon name="minus" class="mx-auto size-4 text-ink-300" />
                                        @else
                                            <span class="font-mono text-xs text-ink-950">{{ $cell['value'] }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section id="models" class="container-x py-16 lg:py-20">
        <div class="mx-auto max-w-2xl text-center">
            <span class="eyebrow">
                <span class="dot bg-lime-dark"></span>
                {{ __('ui.pricing.models_eyebrow') }}
            </span>
            <h2 class="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('ui.pricing.models_title') }}</h2>
            <p class="mt-3 text-sm leading-7 text-ink-500">{{ __('ui.pricing.models_subtitle') }}</p>
        </div>

        <div class="mt-10 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($catalog as $entry)
                <div class="card card-pad">
                    <div class="flex items-baseline justify-between gap-2">
                        <h3 class="font-semibold">{{ $entry['plan'] }}</h3>
                        <span class="font-mono text-xs text-ink-400">{{ trans_choice('ui.pricing.models_count', $entry['modelCount'], ['count' => $entry['modelCount']]) }}</span>
                    </div>
                    <ul class="mt-4 space-y-2 text-sm text-ink-600">
                        @forelse ($entry['providers'] as $provider)
                            <li class="flex items-center justify-between gap-3">
                                <span>{{ $provider['name'] }}</span>
                                <span class="font-mono text-[11px] text-ink-400">{{ $provider['models'] }}</span>
                            </li>
                        @empty
                            <li class="text-ink-400">{{ __('ui.pricing.models_none') }}</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>

        <p class="mt-8 text-center text-xs leading-6 text-ink-400">
            {{ __('ui.pricing.models_note') }}
            <a href="{{ localized_route('providers') }}" class="font-semibold text-ink-950 underline decoration-lime decoration-2 underline-offset-4">{{ __('ui.pricing.models_link') }}</a>
        </p>
    </section>

    <section class="border-y border-ink-950/10 bg-white/50 py-16 lg:py-20">
        <div class="container-x">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('ui.pricing.need_more') }}</h2>
                <p class="mt-3 text-sm leading-7 text-ink-500">{{ __('ui.landing.cta_subtitle') }}</p>
            </div>

            <div class="mt-10 grid gap-4 md:grid-cols-3">
                @foreach (['billing', 'security', 'teams'] as $feature)
                    <div class="card card-pad">
                        <span class="inline-flex size-10 items-center justify-center rounded-xl bg-lime/20 text-ink-950">
                            <x-icon :name="['billing' => 'card', 'security' => 'shield', 'teams' => 'users'][$feature]" class="size-5" />
                        </span>
                        <h3 class="mt-4 font-semibold">{{ __('ui.landing.features.'.$feature.'.title') }}</h3>
                        <p class="mt-1.5 text-sm leading-7 text-ink-500">{{ __('ui.landing.features.'.$feature.'.body') }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-10 flex flex-wrap justify-center gap-3">
                <a href="{{ localized_route('contact') }}" class="btn btn-primary h-11 px-6">{{ __('ui.pricing.talk_sales') }}</a>
                <a href="{{ localized_route('docs') }}" class="btn btn-outline h-11 px-6">{{ __('ui.docs.quickstart') }}</a>
            </div>
        </div>
    </section>
@endsection
