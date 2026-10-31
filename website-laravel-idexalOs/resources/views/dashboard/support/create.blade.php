@extends('layouts.dashboard')

@section('title', __('ui.support.new_ticket'))
@section('page', __('ui.dashboard.nav.support'))

@section('content')
    <div>
        <a href="{{ route('dashboard.support.index') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-400 transition hover:text-ink-950">
            <x-icon name="arrow-right" class="size-3.5 rtl:rotate-180" />
            {{ __('ui.support.back') }}
        </a>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.support.new_ticket') }}</h1>
        <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.support.subtitle') }}</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">
            <x-icon name="alert" class="mt-0.5 size-4" />
            <ul class="list-inside list-disc text-xs">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('dashboard.support.store') }}" class="card card-pad max-w-3xl space-y-4">
        @csrf

        <div>
            <label class="label" for="subject">{{ __('ui.support.subject') }}</label>
            <input id="subject" type="text" name="subject" value="{{ old('subject') }}" required maxlength="150" class="input h-11 @error('subject') input-error @enderror">
            @error('subject')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="category">{{ __('ui.support.category') }}</label>
                <select id="category" name="category" class="select h-11">
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(old('category', 'general') === $category)>{{ __('ui.support.categories.'.$category) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="priority">{{ __('ui.support.priority') }}</label>
                <select id="priority" name="priority" class="select h-11">
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority }}" @selected(old('priority', 'normal') === $priority)>{{ __('ui.support.priorities.'.$priority) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="label" for="body">{{ __('ui.support.message') }}</label>
            <textarea id="body" name="body" rows="7" required maxlength="5000" class="input min-h-40 @error('body') input-error @enderror" placeholder="{{ __('ui.support.message_placeholder') }}">{{ old('body') }}</textarea>
            @error('body')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary h-11 w-full sm:w-auto sm:px-8">{{ __('ui.support.send') }}</button>
    </form>
@endsection
