@extends('layouts.dashboard')

@section('title', __('ui.billing.title'))
@section('page', __('ui.dashboard.nav.billing'))

@php
    $planName = $plan
        ? (trans()->has('ui.plans.'.$plan->slug.'.name') ? __('ui.plans.'.$plan->slug.'.name') : $plan->name)
        : __('ui.billing.no_plan');
    $stripeUsable = $stripeEnabled && $canManage;
    $seatCount = $organization->memberships()->where('status', \App\Models\Membership::STATUS_ACTIVE)->count();
    $keyCount = $organization->apiKeys()->whereNull('revoked_at')->count();
@endphp

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.billing.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.billing.subtitle') }}</p>
        </div>
        @if ($stripeUsable)
            <a href="{{ route('dashboard.billing.portal') }}" class="btn btn-outline btn-sm h-10 px-4">
                <x-icon name="external" class="size-4" />
                {{ __('ui.billing.manage_stripe') }}
            </a>
        @endif
    </div>

    @if (! $billingEnabled)
        <div class="alert alert-warning">
            <x-icon name="alert" class="mt-0.5 size-4" />
            <p>{{ __('ui.billing.manual_note') }}</p>
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
        <div class="card overflow-hidden">
            <div class="mesh-dark p-6 text-paper sm:p-7">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-paper/40">{{ __('ui.billing.current_plan') }}</p>
                        <div class="mt-2 flex items-center gap-3">
                            <h2 class="text-2xl font-semibold">{{ $planName }}</h2>
                            @if ($subscription)
                                <x-status :status="$subscription->status" class="!border-white/10 !bg-white/5 !text-paper/70" />
                            @else
                                <span class="badge border-white/10 bg-white/5 text-paper/60">{{ __('ui.billing.free') }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-paper/45">{{ $plan?->description }}</p>
                    </div>

                    <div class="text-end">
                        <p class="font-mono text-2xl font-medium">
                            {{ $plan ? $plan->formattedPrice($subscription?->billing_cycle ?? 'monthly') : money(0) }}
                        </p>
                        <p class="text-[10px] text-paper/40">/ {{ $subscription?->billing_cycle === 'yearly' ? __('ui.common.yearly') : __('ui.common.monthly') }}</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 border-t border-white/10 pt-5 sm:grid-cols-3">
                    <div>
                        <p class="text-[10px] text-paper/40">{{ __('ui.billing.next_renewal') }}</p>
                        <p class="mt-1 text-xs font-medium">{{ $subscription?->current_period_end?->translatedFormat('d M Y') ?? __('ui.billing.no_renewal') }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-paper/40">{{ __('ui.billing.seat_count') }}</p>
                        <p class="mt-1 font-mono text-xs">{{ $seatCount }} / {{ $summary['limits']['seats'] === null ? '∞' : $summary['limits']['seats'] }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-paper/40">{{ __('ui.billing.api_key_count') }}</p>
                        <p class="mt-1 font-mono text-xs">{{ $keyCount }} / {{ $summary['limits']['api_keys'] === null ? '∞' : $summary['limits']['api_keys'] }}</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-5 p-6 sm:grid-cols-2 sm:p-7">
                <div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-ink-500">{{ __('ui.common.requests') }}</span>
                        <span class="font-mono text-[11px]">{{ compact_number($summary['requests']) }} / {{ $summary['limits']['requests'] === null ? '∞' : compact_number($summary['limits']['requests']) }}</span>
                    </div>
                    <div class="progress mt-2"><span style="width: {{ $summary['limits']['requests'] === null ? 2 : max(2, $summary['percentages']['requests']) }}%"></span></div>
                </div>
                <div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-ink-500">{{ __('ui.common.tokens') }}</span>
                        <span class="font-mono text-[11px]">{{ compact_number($summary['total_tokens']) }} / {{ $summary['limits']['tokens'] === null ? '∞' : compact_number($summary['limits']['tokens']) }}</span>
                    </div>
                    <div class="progress mt-2"><span style="width: {{ $summary['limits']['tokens'] === null ? 2 : max(2, $summary['percentages']['tokens']) }}%"></span></div>
                </div>
            </div>

            @if ($canManage && $subscription && ! $subscription->canceled_at)
                <div class="border-t border-ink-950/10 px-6 py-4 sm:px-7">
                    <form method="POST" action="{{ route('dashboard.billing.cancel') }}" x-data @submit="if (! confirm(@js(__('ui.billing.confirm_cancel')))) { $event.preventDefault(); }">
                        @csrf
                        <button type="submit" class="text-xs font-medium text-red-600 hover:text-red-700">{{ __('ui.billing.cancel') }}</button>
                    </form>
                </div>
            @endif
        </div>

        <div class="card p-6">
            <div class="flex items-start justify-between">
                <div>
                    <p class="stat-label">{{ __('ui.billing.credit_balance') }}</p>
                    <p class="mt-2 font-mono text-3xl font-medium tracking-tight" dir="ltr">{{ money($organization->creditBalance(), $organization->currency) }}</p>
                </div>
                <span class="grid size-10 place-items-center rounded-xl bg-lime/20 text-ink-950"><x-icon name="sparkles" class="size-5" /></span>
            </div>
            <p class="mt-2 text-[11px] leading-5 text-ink-400">{{ __('ui.billing.top_up_note') }}</p>

            @if ($canManage)
                <form method="POST" action="{{ route('dashboard.billing.top-up') }}" class="mt-4 flex gap-2">
                    @csrf
                    <div class="relative flex-1">
                        <input type="number" name="amount" min="5" max="10000" step="1" value="{{ old('amount', 25) }}" required class="input h-10 pe-12 font-mono text-sm" dir="ltr">
                        <span class="absolute end-3 top-1/2 -translate-y-1/2 font-mono text-[10px] text-ink-400">{{ $organization->currency }}</span>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm h-10 px-4">{{ __('ui.billing.top_up') }}</button>
                </form>
                @error('amount')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            @endif

            <div class="mt-5 space-y-2.5">
                @forelse ($creditTransactions as $transaction)
                    <div class="flex items-center justify-between gap-3 text-xs">
                        <div class="min-w-0">
                            <p class="truncate text-ink-600">{{ $transaction->description }}</p>
                            <p class="text-[10px] text-ink-300">{{ $transaction->created_at?->translatedFormat('d M Y') }}</p>
                        </div>
                        <span class="shrink-0 font-mono text-[11px] {{ $transaction->type === 'credit' ? 'text-emerald-600' : 'text-ink-500' }}" dir="ltr">
                            {{ $transaction->type === 'credit' ? '+' : '−' }}{{ money(abs((float) $transaction->amount), $organization->currency) }}
                        </span>
                    </div>
                @empty
                    <p class="text-xs text-ink-400">{{ __('ui.billing.no_credit_history') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    <div>
        <div class="flex items-end justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">{{ __('ui.billing.change_plan') }}</h2>
                <p class="mt-1 text-xs text-ink-500">{{ __('ui.pricing.subtitle') }}</p>
            </div>
            <a href="{{ route('pricing') }}" class="text-xs font-semibold text-ink-500 hover:text-ink-950">{{ __('ui.nav.pricing') }}</a>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($plans as $availablePlan)
                @php
                    $isCurrent = $plan?->getKey() === $availablePlan->getKey();
                    $availableName = trans()->has('ui.plans.'.$availablePlan->slug.'.name') ? __('ui.plans.'.$availablePlan->slug.'.name') : $availablePlan->name;
                @endphp
                <div class="relative flex flex-col rounded-3xl border p-5 {{ $isCurrent ? 'border-ink-950 bg-ink-950 text-paper' : 'border-ink-950/10 bg-white' }}">
                    @if ($isCurrent)
                        <span class="absolute -top-2.5 start-5 badge bg-lime text-ink-950">{{ __('ui.pricing.current_plan') }}</span>
                    @endif
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold">{{ $availableName }}</h3>
                        @if ($availablePlan->isFree())
                            <span class="badge {{ $isCurrent ? 'border-white/10 bg-white/5 text-paper/60' : 'badge-gray' }}">{{ __('ui.billing.free') }}</span>
                        @endif
                    </div>
                    <p class="mt-1 text-[11px] leading-5 {{ $isCurrent ? 'text-paper/45' : 'text-ink-400' }}">{{ $availablePlan->description }}</p>
                    <p class="mt-4 font-mono text-2xl font-medium">{{ $availablePlan->formattedPrice('monthly') }}</p>
                    <p class="text-[10px] {{ $isCurrent ? 'text-paper/40' : 'text-ink-400' }}">/ {{ __('ui.common.monthly') }}</p>

                    <ul class="mt-4 flex-1 space-y-2 text-[11px] {{ $isCurrent ? 'text-paper/55' : 'text-ink-500' }}">
                        <li class="flex justify-between gap-2"><span>{{ __('ui.common.requests') }}</span><span class="font-mono">{{ $availablePlan->limit(\App\Models\Plan::LIMIT_REQUESTS) === null ? '∞' : compact_number($availablePlan->limit(\App\Models\Plan::LIMIT_REQUESTS)) }}</span></li>
                        <li class="flex justify-between gap-2"><span>{{ __('ui.common.tokens') }}</span><span class="font-mono">{{ $availablePlan->limit(\App\Models\Plan::LIMIT_TOKENS) === null ? '∞' : compact_number($availablePlan->limit(\App\Models\Plan::LIMIT_TOKENS)) }}</span></li>
                        <li class="flex justify-between gap-2"><span>{{ __('ui.common.seats') }}</span><span class="font-mono">{{ $availablePlan->limit(\App\Models\Plan::LIMIT_SEATS) ?? '∞' }}</span></li>
                    </ul>

                    @if ($canManage && ! $isCurrent)
                        <form method="POST" action="{{ route('dashboard.billing.plan') }}" class="mt-4 space-y-2">
                            @csrf
                            <input type="hidden" name="plan" value="{{ $availablePlan->slug }}">
                            <div class="grid grid-cols-2 gap-2">
                                <button type="submit" name="billing_cycle" value="monthly" class="btn btn-outline btn-sm h-9">{{ __('ui.common.monthly') }}</button>
                                <button type="submit" name="billing_cycle" value="yearly" class="btn btn-primary btn-sm h-9">{{ __('ui.common.yearly') }}</button>
                            </div>
                        </form>
                    @elseif ($isCurrent)
                        <div class="mt-4 rounded-lg bg-white/10 px-3 py-2 text-center text-[10px] font-medium text-paper/60">{{ __('ui.pricing.current_plan') }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-[1.35fr_1fr]">
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-ink-950/10 p-5">
                <div>
                    <h2 class="font-semibold">{{ __('ui.billing.invoices') }}</h2>
                    <p class="mt-0.5 text-xs text-ink-400">{{ __('ui.billing.invoice_details') }}</p>
                </div>
                <a href="{{ route('dashboard.invoices.index') }}" class="text-xs font-semibold text-ink-500 hover:text-ink-950">{{ __('ui.dashboard.view_all') }}</a>
            </div>

            @if ($invoices->isEmpty())
                <div class="p-10 text-center text-xs text-ink-400">{{ __('ui.billing.no_invoices') }}</div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('ui.common.invoice') }}</th>
                                <th>{{ __('ui.billing.issued') }}</th>
                                <th>{{ __('ui.common.status') }}</th>
                                <th>{{ __('ui.common.total') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $invoice)
                                <tr>
                                    <td class="font-mono text-xs font-semibold text-ink-950">{{ $invoice->number }}</td>
                                    <td class="text-xs">{{ $invoice->issued_at?->translatedFormat('d M Y') ?? '—' }}</td>
                                    <td><x-status :status="$invoice->status" /></td>
                                    <td class="font-mono text-xs">{{ $invoice->formattedTotal() }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('dashboard.invoices.show', $invoice) }}" class="btn btn-ghost btn-sm">{{ __('ui.common.view') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-ink-950/10 p-5">
                    <h2 class="font-semibold">{{ __('ui.billing.payment_methods') }}</h2>
                    @if ($canManage)
                        <button type="button" class="text-xs font-semibold text-ink-500 hover:text-ink-950" @click="$refs.methodForm.classList.toggle('hidden')">{{ __('ui.billing.add_method') }}</button>
                    @endif
                </div>

                <div class="divide-y divide-ink-950/[0.06]">
                    @forelse ($paymentMethods as $method)
                        <div class="flex items-center gap-3 p-4">
                            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-paper text-ink-500">
                                <x-icon :name="$method->type === 'bank_transfer' ? 'building' : 'card'" class="size-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $method->label() }}</p>
                                <p class="text-[10px] text-ink-400">{{ $method->holder_name }} @if ($method->is_default) · <span class="text-lime-dark">{{ __('ui.billing.default') }}</span> @endif</p>
                                @if ($method->type === 'bank_transfer' && isset($method->metadata['iban']))
                                    <p class="mt-0.5 font-mono text-[10px] text-ink-400" dir="ltr">{{ $method->metadata['iban'] }}</p>
                                @endif
                            </div>
                            @if ($canManage)
                                <div class="flex items-center gap-1">
                                    @if (! $method->is_default)
                                        <form method="POST" action="{{ route('dashboard.billing.payment-methods.default', $method) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-ghost btn-sm text-[10px]">{{ __('ui.billing.make_default') }}</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('dashboard.billing.payment-methods.destroy', $method) }}" x-data @submit="if (! confirm(@js(__('ui.billing.confirm_remove')))) { $event.preventDefault(); }">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-3.5" /></button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="p-8 text-center text-xs text-ink-400">{{ __('ui.billing.no_methods') }}</p>
                    @endforelse
                </div>

                @if ($canManage)
                    <div class="hidden border-t border-ink-950/10 p-5" x-ref="methodForm">
                        <form method="POST" action="{{ route('dashboard.billing.payment-methods.store') }}" class="space-y-3">
                            @csrf
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="label" for="method-type">{{ __('ui.billing.method_type') }}</label>
                                    <select id="method-type" name="type" class="select h-10" x-on:change="$refs.cardFields.classList.toggle('hidden', $event.target.value !== 'card'); $refs.bankFields.classList.toggle('hidden', $event.target.value !== 'bank_transfer')">
                                        <option value="bank_transfer">{{ __('ui.billing.bank_transfer') }}</option>
                                        <option value="card">{{ __('ui.billing.card') }}</option>
                                        <option value="cash">{{ __('ui.billing.cash') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label" for="holder-name">{{ __('ui.billing.holder') }}</label>
                                    <input id="holder-name" type="text" name="holder_name" required maxlength="120" class="input h-10" value="{{ old('holder_name') }}">
                                </div>
                            </div>

                            <div x-ref="bankFields">
                                <label class="label" for="iban">{{ __('ui.billing.iban') }}</label>
                                <input id="iban" type="text" name="iban" maxlength="64" class="input h-10" dir="ltr" value="{{ old('iban') }}">
                            </div>

                            <div x-ref="cardFields" class="hidden grid grid-cols-3 gap-2">
                                <div>
                                    <label class="label" for="brand">{{ __('ui.billing.brand') }}</label>
                                    <input id="brand" type="text" name="brand" maxlength="40" class="input h-10" value="{{ old('brand') }}">
                                </div>
                                <div>
                                    <label class="label" for="last4">{{ __('ui.billing.last4') }}</label>
                                    <input id="last4" type="text" name="last4" maxlength="4" class="input h-10 font-mono" dir="ltr" value="{{ old('last4') }}">
                                </div>
                                <div>
                                    <label class="label" for="expiry">{{ __('ui.billing.expiry') }}</label>
                                    <div class="grid grid-cols-2 gap-1">
                                        <input type="number" name="exp_month" min="1" max="12" placeholder="MM" class="input h-10 px-2 font-mono text-xs" dir="ltr" value="{{ old('exp_month') }}">
                                        <input type="number" name="exp_year" min="{{ now()->year }}" max="2100" placeholder="YYYY" class="input h-10 px-2 font-mono text-xs" dir="ltr" value="{{ old('exp_year') }}">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm h-9 w-full">{{ __('ui.billing.add_method') }}</button>
                        </form>
                    </div>
                @endif
            </div>

            <div class="rounded-3xl border border-ink-950/10 bg-white p-5">
                <div class="flex items-start gap-3">
                    <x-icon name="shield" class="mt-0.5 size-5 text-lime-dark" />
                    <div>
                        <p class="text-xs font-semibold">{{ __('ui.billing.payment_options') }}</p>
                        <p class="mt-1 text-[11px] leading-5 text-ink-400">{{ __('ui.billing.payment_options_hint') }}</p>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ([
                                \App\Models\Payment::GATEWAY_STRIPE => 'Stripe',
                                \App\Models\Payment::GATEWAY_PAYPAL => 'PayPal',
                                \App\Models\Payment::GATEWAY_BANK_TRANSFER => __('ui.billing.bank_transfer'),
                            ] as $gateway => $label)
                                <span class="badge {{ in_array($gateway, $availableGateways, true) ? 'badge-green' : 'badge-gray' }}">{{ $label }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
