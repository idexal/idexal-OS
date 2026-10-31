@extends('layouts.admin')

@section('title', __('ui.admin.support.title'))
@section('page', __('ui.admin.nav.support'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.support.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.support.subtitle') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.providers.index') }}" class="btn btn-outline btn-sm h-10 px-4">
                <x-icon name="server" class="size-4" />
                {{ __('ui.admin.nav.providers') }}
            </a>
            <a href="{{ route('admin.settings') }}" class="btn btn-outline btn-sm h-10 px-4">
                <x-icon name="settings" class="size-4" />
                {{ __('ui.admin.nav.settings') }}
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">
            <x-icon name="shield" class="mt-0.5 size-4" />
            <div>
                <p class="text-sm font-medium">{{ __('ui.admin.support.fix_errors') }}</p>
                <ul class="mt-1 list-inside list-disc text-xs">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
        <form method="POST" action="{{ route('admin.support.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.support.settings_title') }}</h2>

                <label class="mt-4 flex items-start gap-3 rounded-xl border border-ink-950/10 p-3">
                    <input type="checkbox" name="support_enabled" value="1" class="checkbox mt-0.5" @checked(old('support_enabled', $values['support_enabled']))>
                    <span>
                        <span class="block text-sm font-medium">{{ __('ui.admin.support.enabled') }}</span>
                        <span class="mt-0.5 block text-[11px] leading-5 text-ink-400">{{ __('ui.admin.support.enabled_hint') }}</span>
                    </span>
                </label>

                <div class="mt-4">
                    <label class="label" for="support_auto_close_days">{{ __('ui.admin.support.auto_close_days') }}</label>
                    <input id="support_auto_close_days" type="number" min="0" max="365" name="support_auto_close_days" value="{{ old('support_auto_close_days', $values['support_auto_close_days']) }}" class="input h-10 max-w-40 @error('support_auto_close_days') input-error @enderror" dir="ltr">
                    <p class="mt-1.5 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.support.auto_close_hint') }}</p>
                </div>
            </div>

            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.support.ai_title') }}</h2>

                <label class="mt-4 flex items-start gap-3 rounded-xl border border-ink-950/10 p-3">
                    <input type="checkbox" name="support_ai_enabled" value="1" class="checkbox mt-0.5" @checked(old('support_ai_enabled', $values['support_ai_enabled']))>
                    <span>
                        <span class="block text-sm font-medium">{{ __('ui.admin.support.ai_enabled') }}</span>
                        <span class="mt-0.5 block text-[11px] leading-5 text-ink-400">{{ __('ui.admin.support.ai_enabled_hint') }}</span>
                    </span>
                </label>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="support_ai_provider_id">{{ __('ui.admin.support.ai_provider') }}</label>
                        <select id="support_ai_provider_id" name="support_ai_provider_id" class="select h-10">
                            <option value="">{{ __('ui.admin.support.ai_none') }}</option>
                            @foreach ($providers as $provider)
                                <option value="{{ $provider->getKey() }}" @selected(old('support_ai_provider_id', $values['support_ai_provider_id']) === $provider->getKey())>{{ $provider->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="support_ai_model_id">{{ __('ui.admin.support.ai_model') }}</label>
                        <select id="support_ai_model_id" name="support_ai_model_id" class="select h-10">
                            <option value="">{{ __('ui.admin.support.ai_none') }}</option>
                            @foreach ($providers as $provider)
                                @if ($provider->models->isNotEmpty())
                                    <optgroup label="{{ $provider->name }}">
                                        @foreach ($provider->models as $model)
                                            <option value="{{ $model->getKey() }}" @selected(old('support_ai_model_id', $values['support_ai_model_id']) === $model->getKey())>{{ $model->display_name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>

                <label class="mt-4 flex items-start gap-3 rounded-xl border border-ink-950/10 p-3">
                    <input type="checkbox" name="support_ai_auto_reply" value="1" class="checkbox mt-0.5" @checked(old('support_ai_auto_reply', $values['support_ai_auto_reply']))>
                    <span>
                        <span class="block text-sm font-medium">{{ __('ui.admin.support.ai_auto_reply') }}</span>
                        <span class="mt-0.5 block text-[11px] leading-5 text-ink-400">{{ __('ui.admin.support.ai_auto_reply_hint') }}</span>
                    </span>
                </label>

                <div class="mt-4">
                    <label class="label" for="support_ai_instructions">{{ __('ui.admin.support.ai_instructions') }}</label>
                    <textarea id="support_ai_instructions" name="support_ai_instructions" rows="4" maxlength="2000" class="input min-h-28">{{ old('support_ai_instructions', $values['support_ai_instructions']) }}</textarea>
                    <p class="mt-1.5 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.support.ai_instructions_hint') }}</p>
                </div>
            </div>

            <button type="submit" class="btn btn-primary h-11 w-full sm:w-auto sm:px-8">{{ __('ui.admin.support.save') }}</button>
        </form>

        <div class="space-y-4">
            <div class="card p-5">
                <h2 class="font-semibold">{{ __('ui.admin.support.stats_title') }}</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    @foreach ([
                        ['label' => __('ui.admin.support.stat_total'), 'value' => $stats['total']],
                        ['label' => __('ui.admin.support.stat_open'), 'value' => $stats['open']],
                        ['label' => __('ui.admin.support.stat_unassigned'), 'value' => $stats['unassigned']],
                        ['label' => __('ui.admin.support.stat_awaiting'), 'value' => $stats['awaiting']],
                        ['label' => __('ui.admin.support.stat_resolved'), 'value' => $stats['resolved']],
                    ] as $row)
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-ink-950/10 px-3 py-2">
                            <dt class="text-xs text-ink-500">{{ $row['label'] }}</dt>
                            <dd class="font-mono text-xs font-semibold">{{ $row['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <div class="rounded-3xl border border-ink-950/10 bg-white p-5">
                <div class="flex items-start gap-3">
                    <x-icon name="sparkles" class="mt-0.5 size-5 text-lime-dark" />
                    <div>
                        <p class="text-xs font-semibold">{{ __('ui.admin.support.ai_note_title') }}</p>
                        <p class="mt-1 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.support.ai_note_body') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-ink-950/10 p-5 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold">{{ __('ui.admin.support.list_title') }}</h2>
            <form method="GET" action="{{ route('admin.support.index') }}" class="flex flex-wrap items-center gap-2">
                <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('ui.admin.support.search') }}" class="input h-10 w-48" dir="auto">
                <select name="status" class="select h-10 w-40">
                    <option value="">{{ __('ui.common.all') }} — {{ __('ui.common.status') }}</option>
                    @foreach ($statuses as $option)
                        <option value="{{ $option }}" @selected($filters['status'] === $option)>{{ __('ui.support.statuses.'.$option) }}</option>
                    @endforeach
                </select>
                <select name="priority" class="select h-10 w-36">
                    <option value="">{{ __('ui.common.all') }} — {{ __('ui.support.priority') }}</option>
                    @foreach ($priorities as $option)
                        <option value="{{ $option }}" @selected($filters['priority'] === $option)>{{ __('ui.support.priorities.'.$option) }}</option>
                    @endforeach
                </select>
                <select name="category" class="select h-10 w-40">
                    <option value="">{{ __('ui.common.all') }} — {{ __('ui.support.category') }}</option>
                    @foreach ($categories as $option)
                        <option value="{{ $option }}" @selected($filters['category'] === $option)>{{ __('ui.support.categories.'.$option) }}</option>
                    @endforeach
                </select>
                <select name="assigned" class="select h-10 w-44">
                    <option value="">{{ __('ui.common.all') }} — {{ __('ui.admin.support.assigned_to') }}</option>
                    <option value="none" @selected($filters['assigned'] === 'none')>{{ __('ui.admin.support.unassigned') }}</option>
                    @foreach ($staff as $member)
                        <option value="{{ $member->getKey() }}" @selected($filters['assigned'] === $member->getKey())>{{ $member->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-outline btn-sm h-10 px-4">{{ __('ui.admin.support.filter') }}</button>
            </form>
        </div>

        @if ($tickets->isEmpty())
            <div class="p-12 text-center text-sm text-ink-400">{{ __('ui.admin.support.empty') }}</div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.admin.support.table.ticket') }}</th>
                            <th>{{ __('ui.admin.support.table.requester') }}</th>
                            <th>{{ __('ui.admin.support.table.status') }}</th>
                            <th>{{ __('ui.admin.support.table.priority') }}</th>
                            <th>{{ __('ui.admin.support.table.assigned') }}</th>
                            <th>{{ __('ui.admin.support.table.updated') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tickets as $ticket)
                            <tr>
                                <td class="max-w-[320px]">
                                    <a href="{{ route('admin.support.show', $ticket) }}" class="block truncate text-sm font-medium hover:underline">{{ $ticket->subject }}</a>
                                    <p class="mt-0.5 font-mono text-[10px] text-ink-400" dir="ltr">{{ $ticket->reference }} · {{ $ticket->category }}</p>
                                </td>
                                <td>
                                    <p class="text-sm">{{ $ticket->requester_name }}</p>
                                    <p class="truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $ticket->requester_email }}</p>
                                </td>
                                <td><x-ticket-status :status="$ticket->status" /></td>
                                <td><x-ticket-priority :priority="$ticket->priority" /></td>
                                <td class="whitespace-nowrap text-xs text-ink-500">{{ $ticket->assignee?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs text-ink-400">{{ ($ticket->last_reply_at ?? $ticket->created_at)?->translatedFormat('d M Y') }}</td>
                                <td class="text-end"><a href="{{ route('admin.support.show', $ticket) }}" class="btn btn-ghost btn-sm">{{ __('ui.common.view') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $tickets])
        @endif
    </div>
@endsection
