@extends('layouts.admin')

@section('title', __('ui.admin.providers.keys_title'))
@section('page', __('ui.admin.nav.providers'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a href="{{ route('admin.providers.edit', $provider) }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-400 transition hover:text-ink-950">
                <x-icon name="arrow-right" class="size-3.5 rtl:rotate-180" />
                {{ __('ui.admin.providers.edit') }}
            </a>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.providers.keys_title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">
                {{ __('ui.admin.providers.keys_subtitle') }}
                <span class="font-mono text-xs text-ink-950" dir="ltr">{{ $provider->slug }}</span>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.provider-key-management.rotate-key', $provider) }}" x-data @submit="if (! confirm(@js(__('ui.admin.providers.rotate_confirm')))) { $event.preventDefault(); }">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm h-10 px-4">
                    <x-icon name="refresh" class="size-4" />
                    {{ __('ui.admin.providers.rotate_now') }}
                </button>
            </form>
            <a href="{{ route('admin.providers.edit', $provider) }}" class="btn btn-primary btn-sm h-10 px-4">
                <x-icon name="arrow-left" class="size-4 rtl:rotate-180" />
                {{ __('ui.admin.providers.edit') }}
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            <x-icon name="check-circle" class="mt-0.5 size-4" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error">
            <x-icon name="alert" class="mt-0.5 size-4" />
            <div>
                <p class="text-sm font-medium">{{ __('ui.admin.providers.fix_errors') }}</p>
                <ul class="mt-1 list-inside list-disc text-xs">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if ($expiringKeys->isNotEmpty())
        <div class="alert alert-warning">
            <x-icon name="clock" class="mt-0.5 size-4" />
            <div>
                <p class="text-sm font-medium">{{ __('ui.admin.providers.expiring_title') }}</p>
                <ul class="mt-1 list-inside list-disc text-xs">
                    @foreach ($expiringKeys as $key)
                        <li>{{ $key['name'] }} — {{ $key['days_until_expiry'] }} {{ __('ui.admin.providers.days') }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[1.35fr_1fr]">
        <div class="space-y-4">
            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.providers.add_key') }}</h2>
                <p class="mt-1 text-xs text-ink-400">{{ __('ui.admin.providers.add_key_hint') }}</p>

                <form method="POST" action="{{ route('admin.provider-key-management.store-key', $provider) }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="label" for="api_key">{{ __('ui.admin.providers.key_value') }}</label>
                            <input id="api_key" type="password" name="api_key" required maxlength="1000" autocomplete="new-password" class="input h-10 font-mono text-xs @error('api_key') input-error @enderror" dir="ltr" placeholder="sk-...">
                            @error('api_key')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="label" for="key_name">{{ __('ui.admin.providers.key_name') }}</label>
                            <input id="key_name" type="text" name="key_name" maxlength="80" class="input h-10" placeholder="backup-1">
                            <p class="mt-1.5 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.providers.key_name_hint') }}</p>
                        </div>
                        <div>
                            <label class="label" for="key_type">{{ __('ui.admin.providers.key_type') }}</label>
                            <select id="key_type" name="key_type" class="select h-10">
                                <option value="primary" @selected(old('key_type') === 'primary')>{{ __('ui.admin.providers.key_type_primary') }}</option>
                                <option value="backup" @selected(old('key_type', 'backup') === 'backup')>{{ __('ui.admin.providers.key_type_backup') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label" for="expires_at">{{ __('ui.admin.providers.key_expires') }}</label>
                            <input id="expires_at" type="datetime-local" name="expires_at" class="input h-10 font-mono text-xs" dir="ltr">
                            <p class="mt-1.5 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.providers.key_expires_hint') }}</p>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary h-10 sm:px-8">
                        <x-icon name="plus" class="size-4" />
                        {{ __('ui.admin.providers.add_key') }}
                    </button>
                </form>
            </div>

            <div class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-ink-950/10 p-5">
                    <h2 class="font-semibold">{{ __('ui.admin.providers.keys_list') }}</h2>
                    <span class="badge badge-gray">{{ $keys->count() }} {{ __('ui.admin.providers.keys_total') }}</span>
                </div>

                @if ($provider->api_key)
                    <div class="flex flex-wrap items-center gap-3 border-b border-ink-950/10 bg-paper/40 px-5 py-3">
                        <span class="badge badge-blue">{{ __('ui.admin.providers.default_key') }}</span>
                        <span class="font-mono text-xs" dir="ltr">{{ $provider->maskedKey() }}</span>
                        <span class="text-[11px] text-ink-400">{{ __('ui.admin.providers.default_key_hint') }}</span>
                    </div>
                @endif

                @if ($keys->isEmpty())
                    <p class="p-10 text-center text-sm text-ink-400">{{ __('ui.admin.providers.keys_empty') }}</p>
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('ui.admin.providers.key_name') }}</th>
                                    <th>{{ __('ui.admin.providers.key_type') }}</th>
                                    <th>{{ __('ui.admin.providers.key_last_used') }}</th>
                                    <th>{{ __('ui.admin.providers.key_expires') }}</th>
                                    <th>{{ __('ui.common.status') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($keys as $key)
                                    @php
                                        $lastUsed = $key['last_used_at'] ? \Illuminate\Support\Carbon::parse($key['last_used_at']) : null;
                                        $expires = $key['expires_at'] ? \Illuminate\Support\Carbon::parse($key['expires_at']) : null;
                                    @endphp
                                    <tr>
                                        <td>
                                            <p class="text-sm font-medium">{{ $key['name'] }}</p>
                                            <p class="font-mono text-[10px] text-ink-400" dir="ltr">{{ $key['masked'] }}</p>
                                        </td>
                                        <td>
                                            <span class="badge {{ $key['type'] === 'primary' ? 'badge-green' : 'badge-gray' }}">
                                                {{ $key['type'] === 'primary' ? __('ui.admin.providers.key_type_primary_short') : __('ui.admin.providers.key_type_backup_short') }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap text-xs text-ink-500">{{ $lastUsed ? $lastUsed->diffForHumans() : __('ui.admin.providers.key_never') }}</td>
                                        <td class="whitespace-nowrap text-xs text-ink-500">{{ $expires ? $expires->translatedFormat('d M Y') : '∞' }}</td>
                                        <td>
                                            @if (! $key['is_active'])
                                                <span class="badge badge-gray">{{ __('ui.admin.providers.key_inactive') }}</span>
                                            @elseif ($key['expired'])
                                                <span class="badge badge-red">{{ __('ui.admin.providers.key_expired') }}</span>
                                            @else
                                                <span class="badge badge-green">{{ __('ui.common.active') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if ($key['is_active'])
                                                <form method="POST" action="{{ route('admin.provider-key-management.deactivate-key', [$provider, $key['id']]) }}" x-data @submit="if (! confirm(@js(__('ui.admin.providers.revoke_confirm')))) { $event.preventDefault(); }">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-ghost btn-sm text-red-600">{{ __('ui.admin.providers.revoke') }}</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-3xl bg-ink-950 p-5 text-paper">
                <x-icon name="key" class="size-5 text-lime" />
                <h2 class="mt-3 font-semibold">{{ __('ui.admin.providers.rotation_title') }}</h2>
                <ul class="mt-3 space-y-2 text-[11px] leading-5 text-paper/60">
                    <li class="flex items-start gap-2"><span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-lime"></span>{{ __('ui.admin.providers.rotation_1') }}</li>
                    <li class="flex items-start gap-2"><span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-lime"></span>{{ __('ui.admin.providers.rotation_2') }}</li>
                    <li class="flex items-start gap-2"><span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-lime"></span>{{ __('ui.admin.providers.rotation_3') }}</li>
                    <li class="flex items-start gap-2"><span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-lime"></span>{{ __('ui.admin.providers.rotation_4') }}</li>
                </ul>
            </div>

            <div class="card p-5">
                <div class="flex items-start gap-3">
                    <x-icon name="shield" class="mt-0.5 size-5 text-lime-dark" />
                    <div>
                        <p class="text-xs font-semibold">{{ __('ui.admin.providers.security') }}</p>
                        <p class="mt-1 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.providers.security_1') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
