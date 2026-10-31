@extends('layouts.dashboard')

@section('title', __('ui.developer.deliveries_title'))
@section('page', __('ui.dashboard.nav.developer'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a href="{{ route('dashboard.developer') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-400 transition hover:text-ink-950">
                <x-icon name="arrow-right" class="size-3.5 rtl:rotate-180" />
                {{ __('ui.developer.webhooks') }}
            </a>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.developer.deliveries_title') }}</h1>
            <p class="mt-1.5 truncate font-mono text-xs text-ink-500" dir="ltr">{{ $webhook->url }}</p>
        </div>
        <span class="badge {{ $webhook->is_active ? 'badge-green' : 'badge-gray' }}">
            {{ $webhook->is_active ? __('ui.common.active') : __('ui.common.inactive') }}
        </span>
    </div>

    <div class="card overflow-hidden">
        @if ($deliveries->isEmpty())
            <div class="p-12 text-center">
                <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-paper text-ink-300"><x-icon name="webhook" class="size-6" /></span>
                <p class="mt-3 text-sm font-semibold">{{ __('ui.developer.no_deliveries') }}</p>
                <p class="mt-1 text-xs text-ink-400">{{ __('ui.developer.deliveries_subtitle') }}</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.developer.event') }}</th>
                            <th>{{ __('ui.developer.response_status') }}</th>
                            <th>{{ __('ui.developer.attempts') }}</th>
                            <th>{{ __('ui.common.status') }}</th>
                            <th>{{ __('ui.developer.sent_at') }}</th>
                            <th>{{ __('ui.developer.delivered_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deliveries as $delivery)
                            <tr>
                                <td>
                                    <code class="font-mono text-[11px] font-medium text-ink-950">{{ $delivery->event }}</code>
                                    <p class="mt-0.5 font-mono text-[10px] text-ink-300" dir="ltr">{{ $delivery->id }}</p>
                                </td>
                                <td>
                                    @if ($delivery->response_status)
                                        <span class="badge {{ $delivery->response_status < 400 ? 'badge-green' : 'badge-red' }} font-mono">{{ $delivery->response_status }}</span>
                                    @else
                                        <span class="badge badge-gray">—</span>
                                    @endif
                                </td>
                                <td class="font-mono text-xs">{{ $delivery->attempts }}</td>
                                <td>
                                    @if ($delivery->delivered_at)
                                        <span class="badge badge-green">{{ __('ui.developer.delivered') }}</span>
                                    @elseif ($delivery->next_retry_at)
                                        <span class="badge badge-amber">{{ __('ui.developer.retrying') }}</span>
                                    @else
                                        <span class="badge badge-red">{{ __('ui.developer.failed') }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-xs text-ink-400">{{ $delivery->created_at?->translatedFormat('d M H:i:s') }}</td>
                                <td class="whitespace-nowrap text-xs text-ink-400">{{ $delivery->delivered_at?->translatedFormat('d M H:i:s') ?? '—' }}</td>
                            </tr>
                            @if ($delivery->response_body || ! $delivery->delivered_at)
                                <tr>
                                    <td colspan="6" class="!bg-paper/50 !py-3">
                                        <pre class="max-h-24 overflow-auto whitespace-pre-wrap break-all font-mono text-[10px] leading-5 text-ink-500" dir="ltr">{{ \Illuminate\Support\Str::limit((string) $delivery->response_body, 900) ?: __('ui.developer.no_response_body') }}</pre>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $deliveries])
        @endif
    </div>
@endsection
