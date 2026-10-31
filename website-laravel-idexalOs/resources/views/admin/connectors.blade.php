@extends('layouts.admin')

@section('title', __('ui.admin.connectors.title'))
@section('page', __('ui.admin.nav.connectors'))

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.connectors.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.connectors.subtitle') }}</p>
        </div>

        @if ($organizations->isNotEmpty())
            <form method="GET" action="{{ route('admin.connectors') }}" class="flex items-end gap-2">
                <div>
                    <label class="label" for="organization">{{ __('ui.admin.connectors.organization') }}</label>
                    <select id="organization" name="organization" class="select h-10 min-w-56" onchange="this.form.submit()">
                        @foreach ($organizations as $option)
                            <option value="{{ $option->getKey() }}" @selected($organization?->getKey() === $option->getKey())>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
                <noscript><button type="submit" class="btn btn-ghost h-10">{{ __('ui.common.filter') }}</button></noscript>
            </form>
        @endif
    </div>

    @error('message')
        <p class="mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $message }}</p>
    @enderror

    @if ($organization instanceof \App\Models\Organization && $requests->isNotEmpty())
        @php($connectorNames = collect($connectors)->keyBy('slug'))
        <section class="card card-pad mt-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-semibold">{{ __('ui.admin.connectors.requests_title') }}</h2>
                <span class="badge badge-amber">{{ __('ui.admin.connectors.requests_count', ['count' => $requests->count()]) }}</span>
            </div>
            <p class="mt-1 max-w-2xl text-[11px] leading-5 text-ink-500">{{ __('ui.admin.connectors.requests_hint') }}</p>

            <ul class="mt-3 divide-y divide-ink-950/5">
                @foreach ($requests as $ask)
                    @php($entry = $connectorNames->get($ask->connector_slug))
                    <li class="flex flex-wrap items-start justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium">
                                {{ $ask->user?->name ?? $ask->user?->email ?? __('ui.admin.connectors.request_unknown_member') }}
                                <span class="text-ink-400">→</span>
                                {{ $entry['name'] ?? $ask->connector_slug }}
                            </p>
                            <p class="mt-0.5 break-all font-mono text-[10px] text-ink-300" dir="ltr">{{ $ask->connector_slug }}</p>
                            <p class="mt-1 text-[11px] leading-5 text-ink-500">
                                {{ __('ui.admin.connectors.request_asked', ['when' => $ask->updated_at?->translatedFormat('d M Y H:i')]) }}
                                @foreach ((array) $ask->scopes as $scope)
                                    · {{ \App\Support\ConnectorScope::parse($scope)?->label() ?? $scope }}
                                @endforeach
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a
                                href="{{ route('admin.connectors', ['organization' => $organization->getKey(), 'approve' => $ask->connector_slug]) }}#connector_{{ $ask->connector_slug }}"
                                class="btn btn-primary h-9"
                            >
                                {{ __('ui.admin.connectors.request_review') }}
                            </a>
                            <form method="POST" action="{{ route('admin.connectors.requests.dismiss') }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="organization" value="{{ $organization->getKey() }}">
                                <input type="hidden" name="request" value="{{ $ask->getKey() }}">
                                <button type="submit" class="btn btn-ghost h-9">{{ __('ui.admin.connectors.request_dismiss') }}</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (! $organization instanceof \App\Models\Organization)
        <section class="card card-pad mt-4">
            <p class="text-sm text-ink-500">{{ __('ui.admin.connectors.needs_organization') }}</p>
        </section>
    @elseif ($connectors === [])
        <section class="card card-pad mt-4">
            <p class="text-sm text-ink-500">{{ __('ui.admin.connectors.empty') }}</p>
        </section>
    @else
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            @foreach ($connectors as $connector)
                @php($approvedValues = collect($connector['scopes'])->where('approved', true)->pluck('value')->all())
                @php($submitted = old('scopes.'.$connector['slug']))
                @php($prefillApprove = request('approve') === $connector['slug'])
                @php($checkedValues = is_array($submitted)
                    ? $submitted
                    : ($prefillApprove ? collect($connector['scopes'])->pluck('value')->all() : $approvedValues))
                <section id="connector_{{ $connector['slug'] }}" class="card card-pad">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="font-semibold">{{ $connector['name'] }}</h2>
                            <p class="mt-0.5 font-mono text-[10px] text-ink-300" dir="ltr">{{ $connector['slug'] }}</p>
                        </div>
                        @if ($connector['status'] === \App\Models\ConnectorGrant::STATUS_REVOKED)
                            <span class="badge badge-red">{{ __('ui.admin.connectors.status_revoked') }}</span>
                        @elseif ($connector['expired'])
                            <span class="badge badge-amber">{{ __('ui.admin.connectors.status_expired') }}</span>
                        @elseif ($connector['status'] === \App\Models\ConnectorGrant::STATUS_ACTIVE)
                            <span class="badge badge-lime">{{ __('ui.admin.connectors.status_active') }}</span>
                        @else
                            <span class="badge badge-gray">{{ __('ui.admin.connectors.status_none') }}</span>
                        @endif
                    </div>

                    <p class="mt-1.5 text-[11px] leading-5 text-ink-500">{{ $connector['description'] }}</p>

                    <form method="POST" action="{{ route('admin.connectors.update') }}" class="mt-3">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="organization" value="{{ $organization->getKey() }}">
                        <input type="hidden" name="slug" value="{{ $connector['slug'] }}">

                        <p class="label">{{ __('ui.admin.connectors.scopes_title') }}</p>
                        <div class="mt-1 space-y-2">
                            @foreach ($connector['scopes'] as $scope)
                                <label class="flex items-start gap-2 rounded-xl border border-ink-950/10 p-2.5">
                                    <input
                                        type="checkbox"
                                        name="scopes[{{ $connector['slug'] }}][]"
                                        value="{{ $scope['value'] }}"
                                        class="mt-0.5"
                                        @checked(in_array($scope['value'], $checkedValues, true))
                                    >
                                    <span class="min-w-0">
                                        <span class="block text-xs font-medium">{{ $scope['label'] }}</span>
                                        <span class="block break-all font-mono text-[10px] text-ink-400" dir="ltr">{{ $scope['value'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <div class="mt-3">
                            <label class="label" for="reason_{{ $connector['slug'] }}">{{ __('ui.admin.connectors.reason') }}</label>
                            <input
                                id="reason_{{ $connector['slug'] }}"
                                name="reason"
                                type="text"
                                class="input mt-1 h-9 text-xs"
                                maxlength="500"
                                placeholder="{{ __('ui.admin.connectors.reason_placeholder') }}"
                                value="{{ old('reason') }}"
                            >
                            <p class="mt-1 text-[10px] leading-4 text-ink-400">{{ __('ui.admin.connectors.reason_hint') }}</p>
                        </div>

                        <div class="mt-3">
                            <label class="label" for="expires_in_{{ $connector['slug'] }}">{{ __('ui.admin.connectors.expiry') }}</label>
                            @php($expiryChoice = (string) old('expires_in', $connector['expiryChoice']))
                            <select id="expires_in_{{ $connector['slug'] }}" name="expires_in" class="select mt-1 h-9 text-xs">
                                <option value="{{ \App\Support\ConnectorGrantPolicy::EXPIRY_NEVER }}" @selected($expiryChoice === \App\Support\ConnectorGrantPolicy::EXPIRY_NEVER)>
                                    {{ __('ui.admin.connectors.expiry_never') }}
                                </option>
                                @foreach (\App\Support\ConnectorGrantPolicy::EXPIRY_DAYS as $days)
                                    <option value="{{ $days }}" @selected($expiryChoice === (string) $days)>
                                        {{ __('ui.admin.connectors.expiry_days', ['days' => $days]) }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[10px] leading-4 text-ink-400">{{ __('ui.admin.connectors.expiry_hint') }}</p>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <button type="submit" name="action" value="grant" class="btn btn-primary h-10">
                                {{ __('ui.admin.connectors.approve') }}
                            </button>
                            <button type="submit" name="action" value="revoke" class="btn btn-ghost h-10">
                                {{ __('ui.admin.connectors.revoke') }}
                            </button>
                        </div>
                    </form>

                    <p class="mt-3 border-t border-ink-950/5 pt-2 text-[10px] leading-4 text-ink-400">
                        @if ($connector['revision'] !== null)
                            {{ __('ui.admin.connectors.revision', ['revision' => $connector['revision'], 'when' => $connector['updatedAt']?->translatedFormat('d M Y H:i')]) }}
                        @else
                            {{ __('ui.admin.connectors.never') }}
                        @endif
                        @if ($connector['status'] === \App\Models\ConnectorGrant::STATUS_REVOKED && $connector['reason'])
                            · {{ __('ui.admin.connectors.reason_was', ['reason' => $connector['reason']]) }}
                        @endif
                        @if ($connector['expiresAt'] instanceof \Illuminate\Support\Carbon)
                            · {{ $connector['expired']
                                ? __('ui.admin.connectors.expired_on', ['date' => $connector['expiresAt']->translatedFormat('d M Y H:i')])
                                : __('ui.admin.connectors.expires_on', ['date' => $connector['expiresAt']->translatedFormat('d M Y H:i')]) }}
                        @endif
                    </p>
                </section>
            @endforeach
        </div>

        <p class="mt-4 max-w-3xl text-[11px] leading-5 text-ink-400">{{ __('ui.admin.connectors.footer') }}</p>
    @endif
@endsection
