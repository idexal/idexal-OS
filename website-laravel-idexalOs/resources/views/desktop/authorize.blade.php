@extends('layouts.auth')

@section('title', __('ui.desktop.authorize_title').' — '.config('idexal.brand.name'))
@section('heading', __('ui.desktop.authorize_title'))
@section('subheading', __('ui.desktop.authorize_subtitle'))

@section('content')
    <form method="POST" action="{{ route('desktop.authorize.store') }}" class="space-y-5">
        @csrf

        <div>
            <p class="label">{{ __('ui.desktop.choose_org') }}</p>
            <div class="space-y-2">
                @foreach ($memberships as $membership)
                    <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-ink-950/10 bg-white p-4 transition has-[:checked]:border-ink-950 has-[:checked]:ring-2 has-[:checked]:ring-lime/60">
                        <input
                            type="radio"
                            name="organization_id"
                            value="{{ $membership->organization_id }}"
                            class="checkbox"
                            @checked((string) $selectedOrganization === (string) $membership->organization_id)
                        >
                        <span class="grid size-9 place-items-center rounded-xl bg-ink-950 font-mono text-xs font-semibold text-lime">
                            {{ mb_substr((string) $membership->organization?->name, 0, 1) }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold">{{ $membership->organization?->name }}</span>
                            <span class="block font-mono text-[10px] text-ink-400" dir="ltr">{{ $membership->organization?->slug }}</span>
                        </span>
                        <span class="badge badge-gray">{{ __('ui.roles.'.$membership->role) }}</span>
                    </label>
                @endforeach
            </div>
            @error('organization_id')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="label" for="device_name">{{ __('ui.desktop.device_name') }} <span class="font-normal text-ink-400">({{ __('ui.common.optional') }})</span></label>
            <input
                id="device_name"
                type="text"
                name="device_name"
                value="{{ old('device_name') }}"
                maxlength="120"
                class="input h-11"
                placeholder="{{ __('ui.desktop.device_placeholder') }}"
            >
        </div>

        <div class="rounded-2xl border border-ink-950/10 bg-white p-4">
            <p class="text-xs font-semibold text-ink-950">{{ __('ui.desktop.permissions') }}</p>
            <ul class="mt-3 space-y-2.5 text-xs text-ink-500">
                @foreach (['permission_profile', 'permission_gateway', 'permission_revoke'] as $permission)
                    <li class="flex items-start gap-2">
                        <x-icon name="check-circle" class="mt-0.5 size-3.5 text-lime-dark" />
                        {{ __('ui.desktop.'.$permission) }}
                    </li>
                @endforeach
            </ul>
        </div>

        <button type="submit" class="btn btn-primary h-11 w-full">
            <x-icon name="link" class="size-4" />
            {{ __('ui.desktop.authorize_button') }}
        </button>

        <p class="text-center text-[11px] leading-5 text-ink-400">
            {{ __('ui.desktop.authorize_note', ['minutes' => $handoffMinutes]) }}
        </p>

        <a href="{{ route('dashboard.index') }}" class="btn btn-ghost h-10 w-full">{{ __('ui.common.cancel') }}</a>
    </form>
@endsection
