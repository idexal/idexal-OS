@extends('layouts.auth')

@section('title', __('ui.auth.verify_title').' — '.config('idexal.brand.name'))
@section('heading', __('ui.auth.verify_title'))
@section('subheading', __('ui.auth.verify_body'))

@section('content')
    <div class="rounded-3xl border border-ink-950/10 bg-white p-6 shadow-card">
        <span class="grid size-12 place-items-center rounded-2xl bg-lime/20 text-ink-950">
            <x-icon name="mail" class="size-6" />
        </span>
        <p class="mt-4 text-sm leading-7 text-ink-600">
            {{ __('ui.auth.verify_sent_to') }}
            <span class="font-mono text-xs font-semibold text-ink-950" dir="ltr">{{ auth()->user()?->email }}</span>
        </p>

        <form method="POST" action="{{ route('verification.send') }}" class="mt-5">
            @csrf
            <button type="submit" class="btn btn-primary h-11 w-full">
                <x-icon name="send" class="size-4" />
                {{ __('ui.auth.resend') }}
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-2">
            @csrf
            <button type="submit" class="btn btn-ghost h-10 w-full">{{ __('ui.auth.logout') }}</button>
        </form>
    </div>
@endsection
