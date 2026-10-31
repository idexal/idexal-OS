@extends('layouts.public')

@section('title', __('ui.providers.title').' — '.config('idexal.brand.name'))
@section('description', __('ui.providers.subtitle'))

@section('content')
    <section class="relative overflow-hidden border-b border-ink-950/10">
        <div class="pointer-events-none absolute inset-0 grid-lines opacity-50 [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>
        <div class="container-x relative py-14 lg:py-20">
            <span class="eyebrow">
                <span class="dot bg-lime-dark"></span>
                {{ __('ui.providers.eyebrow') }}
            </span>
            <h1 class="mt-5 max-w-3xl text-balance text-4xl font-semibold tracking-[-0.03em] sm:text-5xl">{{ __('ui.providers.title') }}</h1>
            <p class="mt-4 max-w-2xl text-base leading-8 text-ink-500">{{ __('ui.providers.subtitle') }}</p>

            @if ($groups !== [])
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2 rounded-xl border border-ink-950/10 bg-white px-3 py-2.5 shadow-sm">
                        <span class="font-mono text-lg font-medium text-ink-950">{{ $providerCount }}</span>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __('ui.providers.providers_label') }}</span>
                    </div>
                    <div class="flex items-center gap-2 rounded-xl border border-ink-950/10 bg-white px-3 py-2.5 shadow-sm">
                        <span class="font-mono text-lg font-medium text-ink-950">{{ $modelCount }}</span>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __('ui.providers.models_label') }}</span>
                    </div>
                    <a href="{{ localized_route('pricing') }}" class="btn btn-outline btn-sm h-10 px-4">
                        {{ __('ui.providers.see_plans') }}
                        <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
                    </a>
                </div>
            @endif
        </div>
    </section>

    <section class="container-x py-16 lg:py-24">
        @if ($groups === [])
            <div class="rounded-3xl border border-dashed border-ink-300 p-10 text-center">
                <p class="text-sm font-medium text-ink-950">{{ __('ui.providers.empty_title') }}</p>
                <p class="mt-2 text-sm leading-7 text-ink-500">{{ __('ui.providers.empty_body') }}</p>
            </div>
        @else
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($groups as $group)
                    <article class="flex flex-col rounded-3xl border border-ink-950/10 bg-white p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold">{{ $group['name'] }}</h2>
                                <p class="mt-0.5 font-mono text-[11px] text-ink-400" dir="ltr">{{ $group['slug'] }}</p>
                            </div>
                            <span class="badge badge-gray">{{ $group['driver'] }}</span>
                        </div>

                        @if ($group['availableOn'] !== [])
                            <div class="mt-4 flex flex-wrap items-center gap-1.5">
                                <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __('ui.providers.available_on') }}</span>
                                @foreach ($group['availableOn'] as $planName)
                                    <span class="badge bg-lime/15 text-ink-950">{{ $planName }}</span>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-5 flex-1 overflow-x-auto border-t border-ink-950/5 pt-4">
                            <table class="w-full min-w-[520px] border-collapse text-start text-xs">
                                <thead>
                                    <tr class="text-[10px] font-semibold uppercase tracking-[0.12em] text-ink-400">
                                        <th class="pb-2 pe-3 text-start font-semibold">{{ __('ui.providers.col_model') }}</th>
                                        <th class="pb-2 pe-3 text-start font-semibold">{{ __('ui.providers.col_context') }}</th>
                                        <th class="pb-2 pe-3 text-start font-semibold">{{ __('ui.providers.col_output') }}</th>
                                        <th class="pb-2 pe-3 text-start font-semibold">{{ __('ui.providers.col_price') }}</th>
                                        <th class="pb-2 text-start font-semibold">{{ __('ui.providers.col_capabilities') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($group['models'] as $model)
                                        <tr class="border-t border-ink-950/5 align-top">
                                            <td class="py-2.5 pe-3">
                                                <span class="font-medium text-ink-950">{{ $model['name'] }}</span>
                                                <span class="mt-0.5 block font-mono text-[10px] text-ink-400" dir="ltr">{{ $model['id'] }}</span>
                                            </td>
                                            <td class="py-2.5 pe-3 font-mono text-ink-600" dir="ltr">{{ $model['contextLabel'] ?? '—' }}</td>
                                            <td class="py-2.5 pe-3 font-mono text-ink-600" dir="ltr">{{ $model['maxOutputLabel'] ?? '—' }}</td>
                                            <td class="py-2.5 pe-3 font-mono text-ink-600" dir="ltr">
                                                @if ($model['inputPrice'] || $model['outputPrice'])
                                                    {{ $model['inputPrice'] ?? '—' }} / {{ $model['outputPrice'] ?? '—' }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="py-2.5">
                                                <span class="flex flex-wrap gap-1">
                                                    @forelse ($model['capabilities'] as $capability)
                                                        <span class="badge badge-gray">{{ __($capability) }}</span>
                                                    @empty
                                                        <span class="text-ink-400">—</span>
                                                    @endforelse
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <p class="mt-3 text-[10px] leading-5 text-ink-400">{{ __('ui.providers.price_note') }}</p>
                    </article>
                @endforeach
            </div>

            <div class="mt-10 rounded-3xl border border-ink-950/10 bg-paper-2/60 p-6 lg:p-8">
                <h2 class="text-lg font-semibold">{{ __('ui.providers.byok_title') }}</h2>
                <p class="mt-2 max-w-3xl text-sm leading-7 text-ink-500">{{ __('ui.providers.byok_body') }}</p>
                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <a href="{{ localized_route('register') }}" class="btn btn-primary btn-sm h-10 px-4">
                        {{ __('ui.providers.cta') }}
                        <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
                    </a>
                    <a href="{{ localized_route('docs') }}" class="btn btn-outline btn-sm h-10 px-4">{{ __('ui.nav.docs') }}</a>
                </div>
            </div>
        @endif
    </section>
@endsection
