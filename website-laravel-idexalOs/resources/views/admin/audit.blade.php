@extends('layouts.admin')

@section('title', __('ui.admin.audit.title'))
@section('page', __('ui.admin.nav.audit'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.audit.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.audit.subtitle') }}</p>
        </div>
        <span class="badge badge-gray">{{ $logs->total() }}</span>
    </div>

    <form method="GET" action="{{ route('admin.audit') }}" class="flex flex-wrap items-center gap-2 rounded-2xl border border-ink-950/10 bg-white p-3">
        <div class="relative min-w-[220px] flex-1">
            <x-icon name="search" class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-400" />
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('ui.admin.audit.search_hint') }}" class="input h-10 ps-10">
        </div>
        <select name="action" class="select h-10 max-w-[280px]">
            <option value="">{{ __('ui.common.all') }} — {{ __('ui.admin.audit.action') }}</option>
            @foreach ($actions as $option => $count)
                <option value="{{ $option }}" @selected($action === $option)>{{ $option }} ({{ $count }})</option>
            @endforeach
        </select>
        <label class="flex items-center gap-1.5 text-[11px] text-ink-400">
            <span class="whitespace-nowrap">{{ __('ui.admin.audit.from_date') }}</span>
            <input type="date" name="from" value="{{ $from }}" class="input h-10 w-[150px] text-xs">
        </label>
        <label class="flex items-center gap-1.5 text-[11px] text-ink-400">
            <span class="whitespace-nowrap">{{ __('ui.admin.audit.to_date') }}</span>
            <input type="date" name="to" value="{{ $to }}" class="input h-10 w-[150px] text-xs">
        </label>
        <button type="submit" class="btn btn-primary btn-sm h-10 px-4">{{ __('ui.common.filter') }}</button>
        @if ($search !== '' || $action !== '' || $from !== '' || $to !== '')
            <a href="{{ route('admin.audit') }}" class="text-xs font-medium text-ink-400 hover:text-ink-950">{{ __('ui.common.reset') }}</a>
        @endif
    </form>

    @if ($from !== '' && $to !== '' && $from > $to)
        <p class="text-xs text-ink-400">{{ __('ui.admin.audit.range_reversed') }}</p>
    @endif

    <div class="card overflow-hidden">
        @if ($logs->isEmpty())
            <div class="p-12 text-center text-sm text-ink-400">{{ __('ui.common.no_results') }}</div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.developer.time') }}</th>
                            <th>{{ __('ui.admin.audit.action') }}</th>
                            <th>{{ __('ui.admin.audit.actor') }}</th>
                            <th>{{ __('ui.admin.organizations.title') }}</th>
                            <th>{{ __('ui.common.description') }}</th>
                            <th>IP</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr x-data="{ open: false }">
                                <td class="whitespace-nowrap font-mono text-[10px] text-ink-400">{{ $log->created_at?->translatedFormat('d M Y H:i:s') }}</td>
                                <td>
                                    <code class="rounded-md bg-paper px-1.5 py-0.5 font-mono text-[10px] text-ink-600">{{ $log->action }}</code>
                                </td>
                                <td>
                                    @if ($log->user)
                                        <p class="text-xs font-medium">{{ $log->user->name }}</p>
                                        <p class="max-w-[160px] truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $log->user->email }}</p>
                                    @else
                                        <span class="text-xs text-ink-400">{{ __('ui.admin.audit.system') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($log->organization)
                                        <a href="{{ route('admin.organizations.show', $log->organization) }}" class="block max-w-[150px] truncate text-xs hover:underline">{{ $log->organization->name }}</a>
                                    @else
                                        <span class="text-xs text-ink-300">—</span>
                                    @endif
                                </td>
                                <td class="max-w-[280px]">
                                    <p class="truncate text-xs text-ink-500">{{ $log->description ?: '—' }}</p>
                                    @if ($log->subject_type)
                                        <p class="truncate font-mono text-[10px] text-ink-300" dir="ltr">{{ class_basename($log->subject_type) }} · {{ $log->subject_id }}</p>
                                    @endif
                                </td>
                                <td class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $log->ip ?: '—' }}</td>
                                <td class="text-end">
                                    @if ($log->meta)
                                        <button type="button" class="btn btn-ghost btn-sm" @click="open = ! open">{{ __('ui.common.details') }}</button>
                                    @endif
                                </td>
                            </tr>
                            @if ($log->meta)
                                <tr x-show="open" x-cloak class="!bg-paper/50">
                                    <td colspan="7" class="!p-4">
                                        @include('admin.partials.audit-meta', ['log' => $log])
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $logs])
        @endif
    </div>
@endsection
