@extends('layouts.public')

@section('title', __('ui.contact.title').' — '.config('idexal.brand.name'))
@section('description', __('ui.contact.subtitle'))

@section('content')
    <section class="relative overflow-hidden border-b border-ink-950/10">
        <div class="pointer-events-none absolute inset-0 grid-lines opacity-50 [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>
        <div class="container-x relative py-14 text-center lg:py-20">
            <span class="eyebrow">
                <span class="dot bg-lime-dark"></span>
                {{ __('ui.nav.contact') }}
            </span>
            <h1 class="mx-auto mt-5 max-w-3xl text-balance text-4xl font-semibold tracking-tight sm:text-5xl">{{ __('ui.contact.title') }}</h1>
            <p class="mx-auto mt-4 max-w-xl text-base leading-8 text-ink-500">{{ __('ui.contact.subtitle') }}</p>
        </div>
    </section>

    <section class="container-x py-12 lg:py-20">
        <div class="grid gap-8 lg:grid-cols-[1fr_360px]">
            <div class="card card-pad sm:p-8">
                <h2 class="text-xl font-semibold">{{ __('ui.contact.send') }}</h2>
                <p class="mt-1 text-sm text-ink-500">{{ __('ui.contact.response_time') }}</p>

                <form method="POST" action="{{ route('contact.store') }}" class="mt-6 space-y-5">
                    @csrf
                    <div class="hidden" aria-hidden="true">
                        <label for="website">Website</label>
                        <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="label" for="name">{{ __('ui.contact.name') }}</label>
                            <input id="name" type="text" name="name" value="{{ old('name', auth()->user()?->name) }}" required maxlength="120" class="input @error('name') input-error @enderror" autocomplete="name">
                            @error('name')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="label" for="email">{{ __('ui.contact.email') }}</label>
                            <input id="email" type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" required maxlength="190" class="input @error('email') input-error @enderror" autocomplete="email" dir="ltr">
                            @error('email')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label class="label" for="subject">{{ __('ui.contact.subject') }}</label>
                        <input id="subject" type="text" name="subject" value="{{ old('subject') }}" required maxlength="150" class="input @error('subject') input-error @enderror">
                        @error('subject')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="label" for="message">{{ __('ui.contact.message') }}</label>
                        <textarea id="message" name="message" rows="7" required maxlength="5000" class="input resize-y @error('message') input-error @enderror">{{ old('message') }}</textarea>
                        @error('message')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <p class="text-[11px] leading-5 text-ink-400">{{ __('ui.contact.response_time') }}</p>
                        <button type="submit" class="btn btn-primary h-11 px-6">
                            <x-icon name="send" class="size-4" />
                            {{ __('ui.contact.send') }}
                        </button>
                    </div>
                </form>
            </div>

            <aside class="space-y-4">
                <div class="card p-6">
                    <span class="inline-flex size-10 items-center justify-center rounded-xl bg-lime/20 text-ink-950">
                        <x-icon name="shield" class="size-5" />
                    </span>
                    <h3 class="mt-4 font-semibold">{{ __('ui.contact.support') }}</h3>
                    <p class="mt-1 text-xs leading-6 text-ink-500">{{ __('ui.contact.response_time') }}</p>
                    <a href="mailto:{{ config('idexal.brand.support_email') }}" class="mt-3 flex items-center gap-2 font-mono text-xs font-semibold text-ink-950" dir="ltr">
                        {{ config('idexal.brand.support_email') }}
                        <x-icon name="arrow-up-right" class="size-3.5" />
                    </a>
                </div>

                <div class="card p-6">
                    <span class="inline-flex size-10 items-center justify-center rounded-xl bg-ink-950 text-lime">
                        <x-icon name="building" class="size-5" />
                    </span>
                    <h3 class="mt-4 font-semibold">{{ __('ui.contact.sales') }}</h3>
                    <p class="mt-1 text-xs leading-6 text-ink-500">{{ __('ui.landing.features.billing.body') }}</p>
                    <a href="mailto:{{ config('idexal.brand.info_email') }}" class="mt-3 flex items-center gap-2 font-mono text-xs font-semibold text-ink-950" dir="ltr">
                        {{ config('idexal.brand.info_email') }}
                        <x-icon name="arrow-up-right" class="size-3.5" />
                    </a>
                </div>

                <div class="rounded-3xl bg-ink-950 p-6 text-paper">
                    <x-icon name="book" class="size-5 text-lime" />
                    <h3 class="mt-4 font-semibold">{{ __('ui.docs.title') }}</h3>
                    <p class="mt-1 text-xs leading-6 text-paper/45">{{ __('ui.docs.subtitle') }}</p>
                    <a href="{{ localized_route('docs') }}" class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-lime">
                        {{ __('ui.docs.quickstart') }}
                        <x-icon name="arrow-left" class="size-3.5 rtl:rotate-180" />
                    </a>
                </div>
            </aside>
        </div>
    </section>
@endsection
