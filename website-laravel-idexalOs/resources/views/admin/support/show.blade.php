@extends('layouts.admin')

@section('title', $ticket->subject)
@section('page', __('ui.admin.nav.support'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a href="{{ route('admin.support.index') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-400 transition hover:text-ink-950">
                <x-icon name="arrow-right" class="size-3.5 rtl:rotate-180" />
                {{ __('ui.admin.support.list_title') }}
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
        <div class="flex flex-wrap gap-2">
            @if ($aiEnabled)
                <form method="POST" action="{{ route('admin.support.ai-draft', $ticket) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm h-10 gap-2 px-4">
                        <x-icon name="sparkles" class="size-4" />
                        {{ __('ui.admin.support.generate_draft') }}
                    </button>
                </form>
            @endif
            @if ($ticket->contactMessage)
                <span class="badge badge-gray">{{ __('ui.admin.support.from_contact') }}</span>
            @endif
        </div>
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
                @if ($reply->isInternal())
                    <div class="rounded-3xl border border-amber-300 bg-amber-50 p-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="badge badge-amber">{{ $reply->isAiGenerated() ? __('ui.admin.support.ai_badge') : __('ui.admin.support.note_badge') }}</span>
                                <span class="text-xs text-ink-500">{{ $reply->created_at?->translatedFormat('d M Y H:i') }}</span>
                            </div>
                            <form method="POST" action="{{ route('admin.support.replies.send', [$ticket, $reply]) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm gap-2">
                                    <x-icon name="send" class="size-3.5" />
                                    {{ __('ui.admin.support.send_reply') }}
                                </button>
                            </form>
                        </div>
                        <div class="mt-4 whitespace-pre-line text-sm leading-8 text-ink-700">{{ $reply->body }}</div>
                    </div>
                @else
                    <div class="card p-6 sm:p-8">
                        <div class="flex items-start gap-4 border-b border-ink-950/10 pb-4">
                            <span class="grid size-9 shrink-0 place-items-center rounded-full {{ $reply->author_type === 'customer' ? 'bg-ink-950 text-lime' : 'bg-lime text-ink-950' }} font-mono text-xs font-semibold">
                                {{ mb_substr((string) ($reply->author_name ?: 'S'), 0, 1) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold">{{ $reply->author_name ?: $reply->author_type }}</p>
                                <p class="text-[11px] text-ink-400">{{ $reply->created_at?->translatedFormat('d M Y H:i') }}</p>
                            </div>
                            @if ($reply->isAiGenerated())
                                <span class="badge badge-blue">{{ __('ui.admin.support.ai_badge') }}</span>
                            @endif
                        </div>
                        <div class="mt-4 whitespace-pre-line text-sm leading-8 text-ink-700">{{ $reply->body }}</div>
                    </div>
                @endif
            @endforeach

            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.support.reply_title') }}</h2>
                <form method="POST" action="{{ route('admin.support.reply', $ticket) }}" class="mt-3 space-y-3">
                    @csrf
                    <textarea name="body" rows="5" required maxlength="5000" class="input min-h-32 @error('body') input-error @enderror" placeholder="{{ __('ui.admin.support.reply_placeholder') }}">{{ old('body') }}</textarea>
                    <label class="flex items-center gap-2 text-xs text-ink-500">
                        <input type="checkbox" name="internal" value="1" class="checkbox" @checked(old('internal'))>
                        {{ __('ui.admin.support.internal_note') }}
                    </label>
                    <button type="submit" class="btn btn-primary h-11 gap-2 px-6">
                        <x-icon name="send" class="size-4" />
                        {{ __('ui.admin.support.send_reply') }}
                    </button>
                </form>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.support.detail_status_title') }}</h2>
                <form method="POST" action="{{ route('admin.support.ticket.update', $ticket) }}" class="mt-3 space-y-3">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label class="label" for="status">{{ __('ui.common.status') }}</label>
                        <select id="status" name="status" class="select h-10">
                            @foreach ($statuses as $option)
                                <option value="{{ $option }}" @selected($ticket->status === $option)>{{ __('ui.support.statuses.'.$option) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="priority">{{ __('ui.support.priority') }}</label>
                        <select id="priority" name="priority" class="select h-10">
                            @foreach ($priorities as $option)
                                <option value="{{ $option }}" @selected($ticket->priority === $option)>{{ __('ui.support.priorities.'.$option) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="category">{{ __('ui.support.category') }}</label>
                        <select id="category" name="category" class="select h-10">
                            @foreach ($categories as $option)
                                <option value="{{ $option }}" @selected($ticket->category === $option)>{{ __('ui.support.categories.'.$option) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="assigned_to">{{ __('ui.admin.support.assigned_to') }}</label>
                        <select id="assigned_to" name="assigned_to" class="select h-10">
                            <option value="">{{ __('ui.admin.support.unassigned') }}</option>
                            @foreach ($staff as $member)
                                <option value="{{ $member->getKey() }}" @selected($ticket->assigned_to === $member->getKey())>{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary h-10 w-full">{{ __('ui.admin.support.save_ticket') }}</button>
                </form>
            </div>

            <div class="card p-5">
                <h2 class="font-semibold">{{ __('ui.common.details') }}</h2>
                <div class="mt-3 space-y-3 text-xs">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.support.reference') }}</span>
                        <span class="font-mono" dir="ltr">{{ $ticket->reference }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.common.name') }}</span>
                        <span>{{ $ticket->requester_name }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.common.email') }}</span>
                        <a href="mailto:{{ $ticket->requester_email }}" class="max-w-[180px] truncate font-mono text-[10px] hover:underline" dir="ltr">{{ $ticket->requester_email }}</a>
                    </div>
                    @if ($ticket->user)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-ink-400">{{ __('ui.admin.support.customer') }}</span>
                            <span>{{ $ticket->user->name }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.support.created_at') }}</span>
                        <span>{{ $ticket->created_at?->translatedFormat('d M Y H:i') }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-ink-400">{{ __('ui.admin.support.table.updated') }}</span>
                        <span>{{ ($ticket->last_reply_at ?? $ticket->created_at)?->translatedFormat('d M Y H:i') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
