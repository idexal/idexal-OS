@extends('layouts.admin')

@section('title', __('ui.admin.referrals.title'))
@section('page', __('ui.admin.nav.referrals'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.referrals.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.referrals.subtitle') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.social-login') }}" class="btn btn-outline btn-sm h-10 px-4">
                <x-icon name="users" class="size-4" />
                {{ __('ui.admin.nav.social_login') }}
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
                <p class="text-sm font-medium">{{ __('ui.admin.referrals.fix_errors') }}</p>
                <ul class="mt-1 list-inside list-disc text-xs">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
        <form method="POST" action="{{ route('admin.referrals.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.referrals.settings_title') }}</h2>

                <label class="mt-4 flex items-start gap-3 rounded-xl border border-ink-950/10 p-3">
                    <input type="checkbox" name="referral_enabled" value="1" class="checkbox mt-0.5" @checked(old('referral_enabled', $values['referral_enabled']))>
                    <span>
                        <span class="block text-sm font-medium">{{ __('ui.admin.referrals.enabled') }}</span>
                    </span>
                </label>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="referral_reward_amount">{{ __('ui.admin.referrals.reward_amount') }}</label>
                        <input id="referral_reward_amount" type="number" step="0.01" min="0" name="referral_reward_amount" value="{{ old('referral_reward_amount', $values['referral_reward_amount']) }}" class="input h-10 @error('referral_reward_amount') input-error @enderror" dir="ltr">
                        @error('referral_reward_amount')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="referral_reward_currency">{{ __('ui.admin.referrals.reward_currency') }}</label>
                        <input id="referral_reward_currency" type="text" maxlength="3" name="referral_reward_currency" value="{{ old('referral_reward_currency', $values['referral_reward_currency']) }}" class="input h-10 font-mono text-xs uppercase @error('referral_reward_currency') input-error @enderror" dir="ltr">
                        @error('referral_reward_currency')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="referral_referee_bonus">{{ __('ui.admin.referrals.referee_bonus') }}</label>
                        <input id="referral_referee_bonus" type="number" step="0.01" min="0" name="referral_referee_bonus" value="{{ old('referral_referee_bonus', $values['referral_referee_bonus']) }}" class="input h-10 @error('referral_referee_bonus') input-error @enderror" dir="ltr">
                        <p class="mt-1.5 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.referrals.referee_bonus_hint') }}</p>
                    </div>
                    <div>
                        <label class="label" for="referral_cookie_days">{{ __('ui.admin.referrals.cookie_days') }}</label>
                        <input id="referral_cookie_days" type="number" min="1" max="365" name="referral_cookie_days" value="{{ old('referral_cookie_days', $values['referral_cookie_days']) }}" class="input h-10 @error('referral_cookie_days') input-error @enderror" dir="ltr">
                        @error('referral_cookie_days')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <label class="mt-4 flex items-start gap-3 rounded-xl border border-ink-950/10 p-3">
                    <input type="checkbox" name="referral_auto_reward" value="1" class="checkbox mt-0.5" @checked(old('referral_auto_reward', $values['referral_auto_reward']))>
                    <span>
                        <span class="block text-sm font-medium">{{ __('ui.admin.referrals.auto_reward') }}</span>
                        <span class="mt-0.5 block text-[11px] leading-5 text-ink-400">{{ __('ui.admin.referrals.auto_reward_hint') }}</span>
                    </span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary h-11 w-full sm:w-auto sm:px-8">{{ __('ui.admin.referrals.save') }}</button>
        </form>

        <div class="space-y-4">
            <div class="card p-5">
                <h2 class="font-semibold">{{ __('ui.admin.referrals.stats_title') }}</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    @foreach ([
                        ['label' => __('ui.admin.referrals.stat_total'), 'value' => $totals['total']],
                        ['label' => __('ui.admin.referrals.stat_pending'), 'value' => $totals['pending']],
                        ['label' => __('ui.admin.referrals.stat_qualified'), 'value' => $totals['qualified']],
                        ['label' => __('ui.admin.referrals.stat_rewarded'), 'value' => $totals['rewarded']],
                        ['label' => __('ui.admin.referrals.stat_credited'), 'value' => money($totals['credited'], $values['referral_reward_currency'])],
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
                    <x-icon name="shield" class="mt-0.5 size-5 text-lime-dark" />
                    <div>
                        <p class="text-xs font-semibold">{{ __('ui.admin.referrals.note_title') }}</p>
                        <p class="mt-1 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.referrals.note_body') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-ink-950/10 p-5 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold">{{ __('ui.admin.referrals.list_title') }}</h2>
            <form method="GET" action="{{ route('admin.referrals') }}" class="flex items-center gap-2">
                <select name="status" class="select h-10 w-44">
                    <option value="">{{ __('ui.common.all') }} — {{ __('ui.common.status') }}</option>
                    @foreach ($statuses as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ __('ui.referrals.statuses.'.$option) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-outline btn-sm h-10 px-4">{{ __('ui.common.filter') }}</button>
            </form>
        </div>

        @if ($referrals->isEmpty())
            <div class="p-12 text-center text-sm text-ink-400">{{ __('ui.admin.referrals.empty') }}</div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.admin.referrals.table.referrer') }}</th>
                            <th>{{ __('ui.admin.referrals.table.friend') }}</th>
                            <th>{{ __('ui.admin.referrals.table.status') }}</th>
                            <th>{{ __('ui.admin.referrals.table.reward') }}</th>
                            <th>{{ __('ui.admin.referrals.table.created') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($referrals as $referral)
                            <tr>
                                <td>
                                    <p class="text-sm font-medium">{{ $referral->referrer?->name ?? '—' }}</p>
                                    <p class="truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $referral->referrer?->email }}</p>
                                </td>
                                <td>
                                    <p class="text-sm font-medium">{{ $referral->referred?->name ?? '—' }}</p>
                                    <p class="truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $referral->referred?->email }}</p>
                                </td>
                                <td><x-referral-status :status="$referral->status" /></td>
                                <td class="whitespace-nowrap font-mono text-xs">
                                    {{ money((float) $referral->reward_amount, $referral->reward_currency ?: $values['referral_reward_currency']) }}
                                </td>
                                <td class="whitespace-nowrap text-xs text-ink-400">{{ $referral->created_at?->translatedFormat('d M Y') }}</td>
                                <td class="text-end">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        @if ($referral->isPending())
                                            <form method="POST" action="{{ route('admin.referrals.update-referral', $referral) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="action" value="qualify">
                                                <input type="hidden" name="status" value="{{ $status }}">
                                                <button type="submit" class="btn btn-ghost btn-sm">{{ __('ui.admin.referrals.approve') }}</button>
                                            </form>
                                        @endif

                                        @unless ($referral->isRewarded() || $referral->status === 'rejected')
                                            <form method="POST" action="{{ route('admin.referrals.update-referral', $referral) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="action" value="reward">
                                                <input type="hidden" name="status" value="{{ $status }}">
                                                <button type="submit" class="btn btn-outline btn-sm">{{ __('ui.admin.referrals.mark_rewarded') }}</button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.referrals.update-referral', $referral) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="status" value="{{ $status }}">
                                                <button type="submit" class="btn btn-ghost btn-sm text-red-600">{{ __('ui.admin.referrals.reject') }}</button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $referrals])
        @endif
    </div>
@endsection
