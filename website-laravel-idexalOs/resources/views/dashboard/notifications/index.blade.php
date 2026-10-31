@extends('layouts.dashboard')

@section('title', __('ui.notifications.title'))
@section('page', __('ui.dashboard.nav.notifications'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.notifications.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.notifications.subtitle') }}</p>
        </div>
        @if ($notifications->whereNull('read_at')->isNotEmpty())
            <form method="POST" action="{{ route('dashboard.notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm h-10 px-4">
                    <x-icon name="check-circle" class="size-4" />
                    {{ __('ui.dashboard.mark_all_read') }}
                </button>
            </form>
        @endif
    </div>

    <div class="card overflow-hidden">
        @if ($notifications->isEmpty())
            <div class="p-12 text-center">
                <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-paper text-ink-300"><x-icon name="bell" class="size-6" /></span>
                <p class="mt-3 text-sm font-semibold">{{ __('ui.notifications.empty') }}</p>
                <p class="mt-1 text-xs text-ink-400">{{ __('ui.notifications.subtitle') }}</p>
                <a href="{{ route('dashboard.index') }}" class="btn btn-outline btn-sm mt-5">{{ __('ui.nav.dashboard') }}</a>
            </div>
        @else
            @foreach ($notifications as $notification)
                <form method="POST" action="{{ route('dashboard.notifications.read', $notification) }}" class="group block border-b border-ink-950/[0.06] last:border-0">
                    @csrf
                    <button type="submit" class="flex w-full items-start gap-4 p-5 text-start transition hover:bg-paper/60">
                        <span class="mt-0.5 grid size-10 shrink-0 place-items-center rounded-xl {{ $notification->read_at ? 'bg-paper text-ink-400' : 'bg-lime/20 text-ink-950' }}">
                            <x-icon :name="$notification->read_at ? 'bell' : 'bell-ring'" class="size-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold {{ $notification->read_at ? 'text-ink-600' : 'text-ink-950' }}">{{ $notification->title }}</span>
                                @if (! $notification->read_at)
                                    <span class="size-1.5 rounded-full bg-lime-dark"></span>
                                @endif
                            </span>
                            <span class="mt-1 block text-xs leading-6 text-ink-500">{{ $notification->body }}</span>
                            <span class="mt-1.5 block text-[10px] text-ink-300">{{ $notification->created_at?->diffForHumans() }} · {{ $notification->type }}</span>
                        </span>
                        @if ($notification->url)
                            <x-icon name="arrow-left" class="mt-3 size-4 shrink-0 text-ink-300 transition group-hover:text-ink-950 rtl:rotate-180" />
                        @endif
                    </button>
                </form>
            @endforeach
            @include('partials.pagination', ['paginator' => $notifications])
        @endif
    </div>
@endsection
