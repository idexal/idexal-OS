@extends('layouts.dashboard')

@section('title', $ticket->subject)
@section('page', __('ui.dashboard.nav.support'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a href="{{ route('dashboard.support.index') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-400 transition hover:text-ink-950">
                <x-icon name="arrow-right" class="size-3.5 rtl:rotate-180" />
                {{ __('ui.support.back') }}
            </a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ $ticket->subject }}</h1>
                <x-ticket-status :status="$ticket->status" />
                <x-ticket-priority :priority="$ticket->priority" />
            </div>
            <p class="mt-1.5 text-sm text-ink-500">
                <span class="font-mono" dir="ltr">{{ $ticket->reference }}</span>
                · {{ __('ui.support.categories.'.$ticket->category) }}
                · {{ $ticket->created_at?->translatedFormat('d M Y H:i') }}
            </p>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-[1.7fr_1fr]">
        <div class="space-y-4">
            <div class="card p-6 sm:p-8">
                <div class="flex items-start gap-4 border-b border-ink-950/10 pb-5">
                    <span class="grid size-11 shrink-0 place-items-center rounded-full bg-ink-950 font-mono text-sm font-semibold text-lime">{{ mb_substr((string) $ticket->requester_name, 0, 1) }}</span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold">{{ $ticket->requester_name }}</p>
                        <p class="font-mono text-[11px] text-ink-400" dir="ltr">{{ $ticket->requester_email }}</p>
                    </div>
                </div>
                <div class="mt-5 whitespace-pre-line text-sm leading-8 text-ink-700">{{ $ticket->body }}</div>
            </div>

            @foreach ($replies as $reply)
                <div class="card p-6 sm:p-8">
                    <div class="flex items-start gap-4 border-b border-ink-950/10 pb-4">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full {{ $reply->author_type === 'customer' ? 'bg-ink-950 text-lime' : 'bg-lime text-ink-950' }} font-mono text-xs font-semibold">
                            {{ mb_substr((string) ($reply->author_name ?: 'S'), 0, 1) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold">{{ $reply->author_name ?: __('ui.support.staff') }}</p>
                            <p class="text-[11px] text-ink-400">{{ $reply->created_at?->translatedFormat('d M Y H:i') }}</p>
                        </div>
                    </div>
                    <div class="mt-4 whitespace-pre-line text-sm leading-8 text-ink-700">{{ $reply->body }}</div>
                </div>
            @endforeach

            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.support.reply') }}</h2>
                <form method="POST" action="{{ route('dashboard.support.reply', $ticket) }}" class="mt-3 space-y-3">
                    @csrf
                    <textarea name="body" rows="5" required maxlength="5000" class="input min-h-32 @error('body') input-error @enderror" placeholder="{{ __('ui.support.reply_placeholder') }}">{{ old('body') }}</textarea>
                    @error('body')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <button type="submit" class="btn btn-primary h-11 gap-2 px-6">
                        <x-icon name="send" class="size-4" />
                        {{ __('ui.support.send') }}
                    </button>
                </form>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card p-5">
                <h2 class="font-semibold">{{ __('ui.common.details') }}</h2>
                <div class="mt-3 space-y-3 text-xs">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.support.reference') }}</span>
                        <span class="font-mono" dir="ltr">{{ $ticket->reference }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.support.category') }}</span>
                        <span>{{ __('ui.support.categories.'.$ticket->category) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.support.status') }}</span>
                        <x-ticket-status :status="$ticket->status" />
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.support.created_at') }}</span>
                        <span>{{ $ticket->created_at?->translatedFormat('d M Y H:i') }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-ink-950/10 bg-white p-5">
                <div class="flex items-start gap-3">
                    <x-icon name="clock" class="mt-0.5 size-5 text-ink-300" />
                    <div>
                        <p class="text-xs font-semibold">{{ $ticket->hasFirstResponse() ? __('ui.support.answered') : __('ui.support.awaiting_reply') }}</p>
                        <p class="mt-1 text-[11px] leading-5 text-ink-400">{{ __('ui.support.subtitle') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
