@extends('layouts.auth')

@section('title', __('ui.desktop.complete_title').' — '.config('idexal.brand.name'))
@section('heading', __('ui.desktop.complete_title'))
@section('subheading', __('ui.desktop.complete_body'))

@section('content')
    @if ($autoRedirect)
        {{-- The desktop opened this page in its own window: it is watching for
             the app's own scheme, so hand back without asking for a click.
             The link below stays as the fallback. --}}
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                window.location.href = @js($deepLink);
            });
        </script>
    @endif
    <div class="card overflow-hidden" x-data="copyButton(@js($deepLink))">
        <div class="mesh-dark px-6 py-8 text-center text-paper">
            <span class="mx-auto grid size-14 place-items-center rounded-full bg-lime text-ink-950">
                <x-icon name="check" class="size-7" />
            </span>
            <p class="mt-4 text-sm font-semibold">{{ __('ui.desktop.connected') }}</p>
            <p class="mt-1 text-xs text-paper/45">{{ __('ui.desktop.complete_body') }}</p>
        </div>

        <div class="space-y-4 p-6">
            <a href="{{ $deepLink }}" class="btn btn-lime h-12 w-full">
                <x-icon name="monitor" class="size-4" />
                {{ __('ui.desktop.open_app') }}
            </a>

            <div class="rounded-2xl border border-ink-950/10 bg-paper/70 p-3">
                <p class="break-all font-mono text-[10px] leading-5 text-ink-500" dir="ltr">{{ $deepLink }}</p>
            </div>

            <button type="button" class="btn btn-outline h-10 w-full" @click="copy()">
                <x-icon name="check" class="size-4 text-lime-dark" x-show="copied" x-cloak />
                <x-icon name="copy" class="size-4" x-show="!copied" />
                <span x-show="!copied">{{ __('ui.desktop.copy_link') }}</span>
                <span x-show="copied" x-cloak>{{ __('ui.common.copied') }}</span>
            </button>

            <div class="flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-[11px] leading-5 text-amber-800">
                <x-icon name="clock" class="mt-0.5 size-3.5" />
                <p>{{ __('ui.desktop.link_expires', ['minutes' => $handoffMinutes]) }}</p>
            </div>

            <p class="text-center text-[11px] leading-5 text-ink-400">{{ __('ui.desktop.manual_note') }}</p>
        </div>
    </div>

    <div class="mt-5 rounded-2xl border border-ink-950/10 bg-white p-4">
        <p class="text-xs font-semibold">{{ __('ui.desktop.how_it_works') }}</p>
        <ol class="mt-3 space-y-2 text-[11px] leading-5 text-ink-500">
            <li class="flex gap-2"><span class="font-mono text-ink-300">1.</span>{{ __('ui.desktop.step1') }}</li>
            <li class="flex gap-2"><span class="font-mono text-ink-300">2.</span>{{ __('ui.desktop.step2') }}</li>
            <li class="flex gap-2"><span class="font-mono text-ink-300">3.</span>{{ __('ui.desktop.step3') }}</li>
        </ol>
    </div>

    <a href="{{ route('dashboard.index') }}" class="btn btn-ghost mt-3 h-10 w-full">{{ __('ui.nav.dashboard') }}</a>
@endsection
