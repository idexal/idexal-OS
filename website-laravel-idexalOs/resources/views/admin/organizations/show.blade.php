@extends('layouts.admin')

@section('title', $organization->name)
@section('page', __('ui.admin.nav.organizations'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a href="{{ route('admin.organizations.index') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-400 transition hover:text-ink-950">
                <x-icon name="arrow-right" class="size-3.5 rtl:rotate-180" />
                {{ __('ui.admin.organizations.title') }}
            </a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ $organization->name }}</h1>
                <span class="badge {{ $organization->status === 'active' ? 'badge-green' : 'badge-red' }}">{{ __('ui.statuses.'.$organization->status) }}</span>
                <span class="badge badge-gray">{{ $organization->isPersonal() ? __('ui.settings.personal') : __('ui.settings.team') }}</span>
            </div>
            <p class="mt-1.5 font-mono text-xs text-ink-400" dir="ltr">{{ $organization->slug }} · {{ $organization->owner?->email }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($organization->status === 'active')
                <form method="POST" action="{{ route('admin.organizations.status', $organization) }}" x-data @submit="if (! confirm(@js(__('ui.admin.organizations.confirm_suspend')))) { $event.preventDefault(); }">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="suspended">
                    <button type="submit" class="btn btn-outline btn-sm h-10 px-4 text-red-600">{{ __('ui.admin.organizations.suspend') }}</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.organizations.status', $organization) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="active">
                    <button type="submit" class="btn btn-primary btn-sm h-10 px-4">{{ __('ui.admin.organizations.activate') }}</button>
                </form>
            @endif
            <a href="{{ route('admin.invoices.create', ['organization' => $organization->slug]) }}" class="btn btn-outline btn-sm h-10 px-4">{{ __('ui.admin.invoices.create') }}</a>
        </div>
    </div>

    @error('status')
        <div class="alert alert-error">{{ $message }}</div>
    @enderror

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-5">
            <p class="stat-label">{{ __('ui.billing.current_plan') }}</p>
            <p class="mt-2 text-lg font-semibold">{{ $plan?->name ?? __('ui.billing.no_plan') }}</p>
            <p class="mt-1 text-[10px] text-ink-400">{{ $subscription?->status ? __('ui.statuses.'.$subscription->status) : __('ui.billing.free') }}</p>
        </div>
        <div class="card p-5">
            <p class="stat-label">{{ __('ui.billing.credit_balance') }}</p>
            <p class="stat-value mt-2" dir="ltr">{{ money($organization->creditBalance(), $organization->currency) }}</p>
            <p class="mt-1 text-[10px] text-ink-400">{{ $organization->currency }}</p>
        </div>
        <div class="card p-5">
            <p class="stat-label">{{ __('ui.team.members') }}</p>
            <p class="stat-value mt-2">{{ $organization->memberships->count() }}</p>
            <p class="mt-1 text-[10px] text-ink-400">{{ $organization->apiKeys->count() }} {{ __('ui.common.api_keys') }}</p>
        </div>
        <div class="card p-5">
            <p class="stat-label">{{ __('ui.desktop.active_sessions') }}</p>
            <p class="stat-value mt-2">{{ $organization->desktopSessions->whereNull('revoked_at')->count() }}</p>
            <p class="mt-1 text-[10px] text-ink-400">{{ $organization->desktopSessions->count() }} {{ __('ui.desktop.total_sessions') }}</p>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-[1.3fr_1fr]">
        <div class="card p-5">
            <h2 class="font-semibold">{{ __('ui.admin.organizations.billing_profile') }}</h2>
            <div class="mt-4 grid gap-4 text-xs sm:grid-cols-2">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.14em] text-ink-400">{{ __('ui.settings.billing_email') }}</p>
                    <p class="mt-1 font-mono" dir="ltr">{{ $organization->billing_email ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-[0.14em] text-ink-400">{{ __('ui.settings.tax_id') }}</p>
                    <p class="mt-1 font-mono" dir="ltr">{{ $organization->tax_id ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-[0.14em] text-ink-400">{{ __('ui.settings.country') }} / {{ __('ui.settings.city') }}</p>
                    <p class="mt-1">{{ collect([$organization->city, $organization->country])->filter()->implode(', ') ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-[0.14em] text-ink-400">{{ __('ui.settings.created') }}</p>
                    <p class="mt-1">{{ $organization->created_at?->translatedFormat('d M Y') }}</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-[10px] uppercase tracking-[0.14em] text-ink-400">{{ __('ui.settings.address_line_1') }}</p>
                    <p class="mt-1 leading-5">{{ collect([$organization->address_line_1, $organization->address_line_2, $organization->postal_code])->filter()->implode('، ') ?: '—' }}</p>
                </div>
            </div>
        </div>

        <div class="card p-5">
            <h2 class="font-semibold">{{ __('ui.admin.organizations.add_credit') }}</h2>
            <p class="mt-1 text-xs text-ink-400">{{ __('ui.admin.organizations.add_credit_hint') }}</p>
            <form method="POST" action="{{ route('admin.organizations.credit', $organization) }}" class="mt-4 space-y-3">
                @csrf
                <div class="grid gap-3 sm:grid-cols-[1fr_120px]">
                    <div>
                        <label class="label" for="credit-amount">{{ __('ui.common.amount') }}</label>
                        <input id="credit-amount" type="number" name="amount" min="0.01" max="100000" step="0.01" value="{{ old('amount', 25) }}" required class="input h-10 font-mono @error('amount') input-error @enderror" dir="ltr">
                        @error('amount')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label">{{ __('ui.settings.currency') }}</label>
                        <div class="input flex h-10 items-center font-mono text-xs text-ink-500">{{ $organization->currency }}</div>
                    </div>
                </div>
                <div>
                    <label class="label" for="credit-description">{{ __('ui.common.description') }}</label>
                    <input id="credit-description" type="text" name="description" value="{{ old('description') }}" maxlength="190" class="input h-10" placeholder="{{ __('ui.admin.organizations.credit_added') }}">
                </div>
                <button type="submit" class="btn btn-primary h-10 w-full">
                    <x-icon name="plus" class="size-4" />
                    {{ __('ui.admin.organizations.add_credit') }}
                </button>
            </form>
        </div>
    </div>

    @php($byokKilled = (bool) ($organization->settings['byok_killed'] ?? false))
    <div class="card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="font-semibold">{{ __('ui.admin.organizations.byok_title') }}</h2>
                <p class="mt-1 max-w-2xl text-xs leading-5 text-ink-400">{{ __('ui.admin.organizations.byok_hint') }}</p>
            </div>
            <span class="badge {{ $byokKilled ? 'badge-amber' : 'badge-green' }}">
                {{ $byokKilled ? __('ui.admin.organizations.byok_killed_badge') : __('ui.admin.organizations.byok_allowed_badge') }}
            </span>
        </div>
        <form method="POST" action="{{ route('admin.organizations.byok', $organization) }}" class="mt-4 flex flex-wrap items-center gap-3">
            @csrf
            @method('PATCH')
            <input type="hidden" name="byok_killed" value="{{ $byokKilled ? '0' : '1' }}">
            <button type="submit" class="btn {{ $byokKilled ? 'btn-outline' : 'btn-outline' }} h-10 px-4">
                {{ $byokKilled ? __('ui.admin.organizations.byok_allow') : __('ui.admin.organizations.byok_kill') }}
            </button>
            <p class="text-[11px] text-ink-400">{{ __('ui.admin.organizations.byok_note') }}</p>
        </form>
    </div>

    <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-ink-950/10 p-5">
            <div>
                <h2 class="font-semibold">{{ __('ui.team.members') }}</h2>
                <p class="mt-0.5 text-xs text-ink-400">{{ $organization->memberships->count() }} {{ __('ui.team.seat_usage') }}</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('ui.common.name') }}</th>
                        <th>{{ __('ui.common.role') }}</th>
                        <th>{{ __('ui.common.status') }}</th>
                        <th>{{ __('ui.team.joined') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($organization->memberships as $member)
                        <tr>
                            <td>
                                <p class="text-xs font-semibold">{{ $member->user?->name }}</p>
                                <p class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $member->user?->email }}</p>
                            </td>
                            <td><span class="badge badge-gray">{{ __('ui.roles.'.$member->role) }}</span></td>
                            <td><span class="badge {{ $member->status === 'active' ? 'badge-green' : 'badge-gray' }}">{{ __('ui.statuses.'.$member->status) }}</span></td>
                            <td class="text-xs text-ink-400">{{ $member->joined_at?->translatedFormat('d M Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-xs text-ink-400">{{ __('ui.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-ink-950/10 p-5">
                <h2 class="font-semibold">{{ __('ui.admin.nav.subscriptions') }}</h2>
                <a href="{{ route('admin.subscriptions.index', ['q' => $organization->slug]) }}" class="text-xs font-semibold text-ink-500 hover:text-ink-950">{{ __('ui.common.view') }}</a>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.common.plan') }}</th>
                            <th>{{ __('ui.billing.cycle') }}</th>
                            <th>{{ __('ui.common.status') }}</th>
                            <th>{{ __('ui.billing.renews') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($organization->subscriptions as $item)
                            <tr>
                                <td class="text-xs font-medium">{{ $item->plan?->name ?? '—' }}</td>
                                <td class="text-xs">{{ $item->billing_cycle }}</td>
                                <td><x-status :status="$item->status" /></td>
                                <td class="text-xs text-ink-400">{{ $item->current_period_end?->translatedFormat('d M Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-xs text-ink-400">{{ __('ui.common.no_data') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-ink-950/10 p-5">
                <h2 class="font-semibold">{{ __('ui.billing.invoices') }}</h2>
                <a href="{{ route('admin.invoices.index', ['q' => $organization->name]) }}" class="text-xs font-semibold text-ink-500 hover:text-ink-950">{{ __('ui.common.view') }}</a>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.common.invoice') }}</th>
                            <th>{{ __('ui.common.status') }}</th>
                            <th class="text-end">{{ __('ui.common.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($organization->invoices as $invoice)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.invoices.show', $invoice) }}" class="font-mono text-xs font-semibold hover:underline">{{ $invoice->number }}</a>
                                    <p class="text-[10px] text-ink-400">{{ $invoice->created_at?->translatedFormat('d M Y') }}</p>
                                </td>
                                <td><x-status :status="$invoice->status" /></td>
                                <td class="text-end font-mono text-xs">{{ $invoice->formattedTotal() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-8 text-center text-xs text-ink-400">{{ __('ui.common.no_data') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="card overflow-hidden">
            <div class="border-b border-ink-950/10 p-5">
                <h2 class="font-semibold">{{ __('ui.common.api_keys') }}</h2>
            </div>
            <div class="divide-y divide-ink-950/[0.06]">
                @forelse ($organization->apiKeys as $key)
                    <div class="p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="truncate text-xs font-semibold">{{ $key->name }}</p>
                            <span class="badge {{ $key->revoked_at ? 'badge-red' : 'badge-green' }}">{{ $key->revoked_at ? __('ui.statuses.revoked') : __('ui.common.active') }}</span>
                        </div>
                        <p class="mt-1 font-mono text-[10px] text-ink-400" dir="ltr">{{ $key->maskedPrefix() }} · {{ $key->user?->name }}</p>
                    </div>
                @empty
                    <p class="p-8 text-center text-xs text-ink-400">{{ __('ui.api_keys.no_keys') }}</p>
                @endforelse
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="border-b border-ink-950/10 p-5">
                <h2 class="font-semibold">{{ __('ui.desktop.sessions') }}</h2>
            </div>
            <div class="divide-y divide-ink-950/[0.06]">
                @forelse ($organization->desktopSessions as $session)
                    <div class="p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="truncate text-xs font-semibold">{{ $session->device_name ?: __('ui.desktop.device') }}</p>
                            <span class="badge {{ $session->revoked_at ? 'badge-gray' : 'badge-green' }}">{{ $session->revoked_at ? __('ui.statuses.revoked') : __('ui.common.active') }}</span>
                        </div>
                        <p class="mt-1 text-[10px] text-ink-400">{{ $session->user?->name }} · {{ $session->last_seen_at?->diffForHumans() ?? '—' }}</p>
                    </div>
                @empty
                    <p class="p-8 text-center text-xs text-ink-400">{{ __('ui.desktop.no_sessions') }}</p>
                @endforelse
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="border-b border-ink-950/10 p-5">
                <h2 class="font-semibold">{{ __('ui.billing.credit_history') }}</h2>
            </div>
            <div class="divide-y divide-ink-950/[0.06]">
                @forelse ($organization->creditTransactions as $transaction)
                    <div class="flex items-start justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <p class="truncate text-xs">{{ $transaction->description }}</p>
                            <p class="mt-0.5 text-[10px] text-ink-400">{{ $transaction->user?->name }} · {{ $transaction->created_at?->translatedFormat('d M Y') }}</p>
                        </div>
                        <span class="shrink-0 font-mono text-[11px] {{ $transaction->type === 'credit' ? 'text-emerald-600' : 'text-ink-500' }}">
                            {{ $transaction->type === 'credit' ? '+' : '−' }}{{ money(abs((float) $transaction->amount), $organization->currency) }}
                        </span>
                    </div>
                @empty
                    <p class="p-8 text-center text-xs text-ink-400">{{ __('ui.billing.no_credit_history') }}</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
