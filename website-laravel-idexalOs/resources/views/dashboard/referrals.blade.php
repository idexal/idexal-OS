@extends('layouts.dashboard')

@section('title', __('ui.referrals.title'))
@section('page', __('ui.dashboard.nav.referrals'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.referrals.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.referrals.subtitle') }}</p>
        </div>
        <span class="badge badge-lime">{{ money($rewardAmount, $currency) }} {{ __('ui.referrals.per_invite') }}</span>
    </div>

    @if (! $enabled)
        <div class="alert alert-warning">
            <x-icon name="alert" class="mt-0.5 size-4" />
            <p class="text-sm">{{ __('ui.referrals.disabled') }}</p>
        </div>
    @else
        <div class="card card-pad">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0 flex-1">
                    <label class="label" for="referral-link">{{ __('ui.referrals.your_link') }}</label>
                    <div class="flex items-center gap-2" x-data="{ copied: false }">
                        <input
                            id="referral-link"
                            type="text"
                            readonly
                            value="{{ $link }}"
                            class="input h-11 flex-1 font-mono text-xs"
                            dir="ltr"
                            x-ref="link"
                            onfocus="this.select()"
                        >
                        <button
                            type="button"
                            class="btn btn-primary h-11 shrink-0 gap-2 px-4"
                            @click="navigator.clipboard.writeText($refs.link.value); copied = true; setTimeout(() => copied = false, 1500)"
                        >
                            <x-icon name="copy" class="size-4" />
                            <span x-text="copied ? '{{ __('ui.referrals.copied') }}' : '{{ __('ui.referrals.copy') }}'">{{ __('ui.referrals.copy') }}</span>
                        </button>
                    </div>
                    <p class="mt-2 text-[11px] leading-5 text-ink-400">{{ __('ui.referrals.share_hint') }}</p>
                </div>
                <div class="shrink-0">
                    <span class="label">{{ __('ui.referrals.your_code') }}</span>
                    <p class="font-mono text-lg font-semibold tracking-widest text-ink-950" dir="ltr">{{ $code }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            [__('ui.referrals.stats.invited'), $stats['total'], 'users'],
            [__('ui.referrals.stats.active'), $stats['rewarded'], 'check-circle'],
            [__('ui.referrals.stats.earned'), money($stats['rewarded'] * $rewardAmount, $currency), 'sparkles'],
        ] as [$label, $value, $icon])
            <div class="card p-5">
                <div class="flex items-start justify-between">
                    <span class="stat-label">{{ $label }}</span>
                    <span class="grid size-8 place-items-center rounded-lg bg-paper text-ink-500"><x-icon :name="$icon" class="size-4" /></span>
                </div>
                <p class="stat-value mt-3 {{ is_string($value) ? 'text-2xl' : '' }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="card overflow-hidden">
        <div class="border-b border-ink-950/10 p-5">
            <h2 class="font-semibold">{{ __('ui.referrals.list_title') }}</h2>
        </div>

        @if ($invites->isEmpty())
            <div class="p-12 text-center text-sm text-ink-400">{{ __('ui.referrals.empty') }}</div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.referrals.table.friend') }}</th>
                            <th>{{ __('ui.referrals.table.status') }}</th>
                            <th>{{ __('ui.referrals.table.joined') }}</th>
                            <th>{{ __('ui.referrals.table.reward') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invites as $invite)
                            <tr>
                                <td>
                                    <p class="text-sm font-medium">{{ $invite->referred?->name ?? '—' }}</p>
                                    <p class="truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $invite->referred?->email }}</p>
                                </td>
                                <td><x-referral-status :status="$invite->status" /></td>
                                <td class="whitespace-nowrap text-xs text-ink-400">{{ $invite->created_at?->translatedFormat('d M Y') }}</td>
                                <td class="whitespace-nowrap font-mono text-xs">
                                    {{ $invite->isRewarded() ? money((float) $invite->reward_amount, $invite->reward_currency ?: $currency) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
