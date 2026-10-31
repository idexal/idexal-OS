@extends('layouts.dashboard')

@section('title', __('ui.developer.title'))
@section('page', __('ui.dashboard.nav.developer'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.developer.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.developer.subtitle') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('docs') }}" class="btn btn-outline btn-sm h-10 px-4">
                <x-icon name="book" class="size-4" />
                {{ __('ui.dashboard.nav.docs') }}
            </a>
            <a href="#new-webhook" class="btn btn-primary btn-sm h-10 px-4">
                <x-icon name="plus" class="size-4" />
                {{ __('ui.developer.add_webhook') }}
            </a>
        </div>
    </div>

    @if ($newSecret)
        <div class="rounded-3xl border border-lime-dark/30 bg-lime/10 p-5">
            <div class="flex items-start gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-ink-950 text-lime"><x-icon name="key" class="size-5" /></span>
                <div class="min-w-0 flex-1" x-data="copyButton(@js($newSecret))">
                    <p class="text-sm font-semibold text-ink-950">{{ __('ui.developer.secret_title') }}</p>
                    <p class="mt-0.5 text-xs text-ink-600">{{ __('ui.developer.secret_warning') }}</p>
                    <div class="mt-3 flex items-center gap-2 rounded-xl border border-ink-950/10 bg-white p-2">
                        <code class="min-w-0 flex-1 truncate font-mono text-xs" dir="ltr">{{ $newSecret }}</code>
                        <button type="button" class="btn btn-primary btn-sm" @click="copy()">
                            <x-icon name="check" class="size-3.5" x-show="copied" x-cloak />
                            <x-icon name="copy" class="size-3.5" x-show="!copied" />
                            <span x-text="copied ? @js(__('ui.common.copied')) : @js(__('ui.common.copy'))"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[1.45fr_1fr]">
        <div class="card overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-ink-950/10 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-semibold">{{ __('ui.developer.request_logs') }}</h2>
                    <p class="mt-0.5 text-xs text-ink-400">{{ __('ui.developer.request_logs_subtitle') }}</p>
                </div>
                <form method="GET" action="{{ route('dashboard.developer') }}" class="flex flex-wrap items-center gap-2">
                    <input type="number" name="status" value="{{ $filterStatus ?: '' }}" min="100" max="599" placeholder="200" class="input h-9 w-20 font-mono text-xs" dir="ltr">
                    <input type="text" name="model" value="{{ $filterModel }}" placeholder="{{ __('ui.common.model') }}" class="input h-9 w-36 text-xs" dir="ltr">
                    <button type="submit" class="btn btn-outline btn-sm h-9">{{ __('ui.common.filter') }}</button>
                    @if ($filterStatus || $filterModel !== '')
                        <a href="{{ route('dashboard.developer') }}" class="text-[11px] font-medium text-ink-400 hover:text-ink-950">{{ __('ui.common.reset') }}</a>
                    @endif
                </form>
            </div>

            @if ($requests->isEmpty())
                <div class="p-10 text-center">
                    <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-paper text-ink-300"><x-icon name="terminal" class="size-6" /></span>
                    <p class="mt-3 text-sm font-semibold">{{ __('ui.developer.no_logs') }}</p>
                    <a href="{{ route('docs') }}" class="btn btn-outline btn-sm mt-4">{{ __('ui.docs.quickstart') }}</a>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('ui.developer.time') }}</th>
                                <th>{{ __('ui.common.model') }}</th>
                                <th>{{ __('ui.developer.endpoint') }}</th>
                                <th>{{ __('ui.developer.status_code') }}</th>
                                <th>{{ __('ui.common.tokens') }}</th>
                                <th class="text-end">{{ __('ui.common.latency') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requests as $requestRow)
                                <tr>
                                    <td class="whitespace-nowrap text-[11px] text-ink-400">{{ $requestRow->created_at?->translatedFormat('d M H:i:s') }}</td>
                                    <td>
                                        <p class="text-xs font-medium text-ink-950">{{ $requestRow->aiModel?->display_name ?? $requestRow->model }}</p>
                                        <p class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $requestRow->provider?->name ?? '—' }}</p>
                                    </td>
                                    <td><code class="font-mono text-[10px] text-ink-500">{{ $requestRow->endpoint }}</code></td>
                                    <td><span class="badge {{ $requestRow->status_code < 400 ? 'badge-green' : 'badge-red' }} font-mono">{{ $requestRow->status_code }}</span></td>
                                    <td class="font-mono text-[11px]">{{ compact_number($requestRow->total_tokens) }}</td>
                                    <td class="text-end font-mono text-[11px] text-ink-500">{{ $requestRow->latency_ms }}ms</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @include('partials.pagination', ['paginator' => $requests])
            @endif
        </div>

        <div class="space-y-4">
            <div class="rounded-3xl bg-ink-950 p-5 text-paper">
                <div class="flex items-center justify-between">
                    <x-icon name="terminal" class="size-5 text-lime" />
                    <a href="{{ route('docs') }}" class="text-[10px] font-semibold text-lime">{{ __('ui.developer.quickstart') }}</a>
                </div>
                <p class="mt-3 text-sm font-semibold">OpenAI-compatible API</p>
                <p class="mt-1 text-[11px] leading-5 text-paper/45">{{ __('ui.developer.subtitle') }}</p>
                <div class="mt-4 rounded-xl bg-white/5 p-3">
                    <pre class="overflow-x-auto font-mono text-[10px] leading-5 text-lime-soft" dir="ltr">base_url = "{{ url('/v1') }}"</pre>
                </div>
            </div>

            <div class="card p-5">
                <h2 class="font-semibold">{{ __('ui.developer.webhook_events') }}</h2>
                <div class="mt-3 space-y-2">
                    @foreach ($eventOptions as $event => $label)
                        <div class="flex items-start gap-2 text-[11px]">
                            <span class="mt-1 size-1.5 shrink-0 rounded-full bg-lime-dark"></span>
                            <div>
                                <code class="font-mono text-[10px] text-ink-950" dir="ltr">{{ $event }}</code>
                                <p class="mt-0.5 leading-4 text-ink-400">{{ $label }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('docs') }}#webhooks" class="mt-4 flex items-center gap-1.5 text-xs font-semibold text-ink-950">
                    {{ __('ui.developer.read_webhook_docs') }}
                    <x-icon name="arrow-left" class="size-3.5 rtl:rotate-180" />
                </a>
            </div>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-[1.45fr_1fr]">
        <div class="card overflow-hidden">
            <div class="border-b border-ink-950/10 p-5">
                <h2 class="font-semibold">{{ __('ui.developer.webhooks') }}</h2>
                <p class="mt-0.5 text-xs text-ink-400">{{ __('ui.developer.webhooks_subtitle') }}</p>
            </div>

            @if ($webhooks->isEmpty())
                <div class="p-10 text-center text-xs text-ink-400">{{ __('ui.developer.no_webhooks') }}</div>
            @else
                <div class="divide-y divide-ink-950/[0.06]">
                    @foreach ($webhooks as $webhook)
                        <div x-data="{ editing: false }">
                            <div class="flex flex-wrap items-center gap-3 p-4">
                                <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-paper text-ink-500"><x-icon name="webhook" class="size-4" /></span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-mono text-xs font-medium" dir="ltr">{{ $webhook->url }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                        @foreach ($webhook->events ?? [] as $event)
                                            <span class="badge badge-gray">{{ $event === '*' ? __('ui.common.all') : $event }}</span>
                                        @endforeach
                                        <span class="text-[10px] text-ink-300">· {{ $webhook->deliveries_count }} {{ __('ui.developer.deliveries') }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="badge {{ $webhook->is_active ? 'badge-green' : 'badge-gray' }}">
                                        {{ $webhook->is_active ? __('ui.common.active') : __('ui.common.inactive') }}
                                    </span>
                                    <button type="button" class="btn btn-ghost btn-sm" @click="editing = ! editing">
                                        {{ __('ui.common.edit') }}
                                    </button>
                                </div>
                            </div>

                            <div x-show="editing" x-cloak class="border-t border-ink-950/[0.06] bg-paper/50 p-4">
                                <form method="POST" action="{{ route('dashboard.developer.webhooks.update', $webhook) }}" class="space-y-4">
                                    @csrf
                                    @method('PATCH')
                                    <div>
                                        <label class="label" for="url-{{ $webhook->id }}">{{ __('ui.developer.webhook_url') }}</label>
                                        <input id="url-{{ $webhook->id }}" type="url" name="url" value="{{ $webhook->url }}" required maxlength="2048" class="input h-10 font-mono text-xs" dir="ltr">
                                    </div>
                                    <div>
                                        <p class="label">{{ __('ui.developer.webhook_events') }}</p>
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            @foreach ($eventOptions as $event => $label)
                                                <label class="flex items-center gap-2 text-[11px]">
                                                    <input type="checkbox" name="events[]" value="{{ $event }}" class="checkbox" @checked(in_array($event, $webhook->events ?? [], true))>
                                                    <span>{{ $label }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                    <label class="flex items-center gap-2 text-xs">
                                        <input type="checkbox" name="is_active" value="1" class="checkbox" @checked($webhook->is_active)>
                                        {{ __('ui.common.active') }}
                                    </label>
                                    <button type="submit" class="btn btn-primary btn-sm">{{ __('ui.common.save') }}</button>
                                </form>

                                <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-ink-950/10 pt-4">
                                    <a href="{{ route('dashboard.developer.webhooks.deliveries', $webhook) }}" class="btn btn-outline btn-sm">
                                        <x-icon name="list" class="size-3.5" />
                                        {{ __('ui.developer.view_deliveries') }}
                                    </a>
                                    <form method="POST" action="{{ route('dashboard.developer.webhooks.rotate', $webhook) }}" x-data @submit="if (! confirm(@js(__('ui.developer.confirm_rotate')))) { $event.preventDefault(); }">
                                        @csrf
                                        <button type="submit" class="btn btn-outline btn-sm">{{ __('ui.developer.rotate_secret') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('dashboard.developer.webhooks.destroy', $webhook) }}" x-data @submit="if (! confirm(@js(__('ui.developer.confirm_delete_webhook')))) { $event.preventDefault(); }">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm text-red-600">{{ __('ui.common.delete') }}</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div id="new-webhook" class="card card-pad scroll-mt-24">
            <div class="flex items-start gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-ink-950 text-lime"><x-icon name="webhook" class="size-5" /></span>
                <div>
                    <h2 class="font-semibold">{{ __('ui.developer.add_webhook') }}</h2>
                    <p class="mt-1 text-xs leading-6 text-ink-400">{{ __('ui.developer.add_webhook_subtitle') }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('dashboard.developer.webhooks.store') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label class="label" for="new-webhook-url">{{ __('ui.developer.webhook_url') }}</label>
                    <input id="new-webhook-url" type="url" name="url" value="{{ old('url') }}" required maxlength="2048" class="input h-10 font-mono text-xs @error('url') input-error @enderror" dir="ltr" placeholder="https://example.com/webhooks/idexal">
                    @error('url')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <p class="label">{{ __('ui.developer.webhook_events') }}</p>
                    <div class="space-y-2">
                        @foreach ($eventOptions as $event => $label)
                            <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-ink-950/10 p-2.5 text-xs transition hover:border-ink-950/25">
                                <input type="checkbox" name="events[]" value="{{ $event }}" class="checkbox mt-0.5" @checked(in_array($event, old('events', ['invoice.paid']), true))>
                                <span>
                                    <code class="font-mono text-[10px]" dir="ltr">{{ $event }}</code>
                                    <span class="mt-0.5 block text-[10px] text-ink-400">{{ $label }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('events')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary h-10 w-full">
                    <x-icon name="plus" class="size-4" />
                    {{ __('ui.developer.add_webhook') }}
                </button>
            </form>
        </div>
    </div>
@endsection
