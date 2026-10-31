@extends('layouts.dashboard')

@section('title', __('ui.api_keys.title'))
@section('page', __('ui.dashboard.nav.api_keys'))

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.api_keys.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.api_keys.subtitle') }}</p>
        </div>
        @if ($canManage)
            <a href="#new-key" class="btn btn-primary btn-sm h-10 px-4">
                <x-icon name="plus" class="size-4" />
                {{ __('ui.api_keys.create') }}
            </a>
        @endif
    </div>

    @if ($newKey)
        <div class="overflow-hidden rounded-3xl border border-lime-dark/30 bg-lime/10">
            <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-ink-950 text-lime"><x-icon name="key" class="size-5" /></span>
                    <div>
                        <p class="text-sm font-semibold text-ink-950">{{ __('ui.api_keys.created_title') }}</p>
                        <p class="mt-0.5 text-xs text-ink-600">{{ __('ui.api_keys.created_warning') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 rounded-xl border border-ink-950/10 bg-white p-2" x-data="copyButton(@js($newKey))">
                    <code class="max-w-[280px] truncate font-mono text-xs" dir="ltr">{{ $newKey }}</code>
                    <button type="button" class="btn btn-primary btn-sm" @click="copy()">
                        <x-icon name="check" class="size-3.5" x-show="copied" x-cloak />
                        <x-icon name="copy" class="size-3.5" x-show="!copied" />
                        <span x-text="copied ? @js(__('ui.common.copied')) : @js(__('ui.common.copy'))"></span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="flex items-center justify-between rounded-2xl border border-ink-950/10 bg-white px-5 py-4">
        <div>
            <p class="text-xs font-semibold text-ink-950">{{ __('ui.common.api_keys') }}</p>
            <p class="mt-0.5 text-[11px] text-ink-400">{{ __('ui.api_keys.subtitle') }}</p>
        </div>
        <div class="text-end">
            <p class="font-mono text-sm font-semibold">{{ $keys->whereNull('revoked_at')->count() }} / {{ $limit === null ? '∞' : $limit }}</p>
            <a href="{{ route('dashboard.billing') }}" class="text-[10px] font-medium text-ink-400 hover:text-ink-950">{{ __('ui.dashboard.manage_plan') }}</a>
        </div>
    </div>

    @if ($keys->isEmpty())
        <div class="card p-10 text-center">
            <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-paper text-ink-300"><x-icon name="key" class="size-6" /></span>
            <p class="mt-3 text-sm font-semibold">{{ __('ui.api_keys.no_keys') }}</p>
            <p class="mt-1 text-xs text-ink-400">{{ __('ui.api_keys.subtitle') }}</p>
            @if ($canManage)
                <a href="#new-key" class="btn btn-primary btn-sm mt-5">{{ __('ui.api_keys.create') }}</a>
            @endif
        </div>
    @else
        <div class="card overflow-hidden">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.api_keys.name') }}</th>
                            <th>{{ __('ui.api_keys.prefix') }}</th>
                            <th>{{ __('ui.api_keys.scopes') }}</th>
                            <th>{{ __('ui.common.created_at') }}</th>
                            <th>{{ __('ui.common.last_used') }}</th>
                            <th>{{ __('ui.common.status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($keys as $key)
                            <tr>
                                <td>
                                    <p class="text-sm font-semibold text-ink-950">{{ $key->name }}</p>
                                    <p class="text-[10px] text-ink-400">{{ $key->user?->name }}</p>
                                </td>
                                <td><code class="rounded bg-paper-2 px-2 py-1 font-mono text-[11px]" dir="ltr">{{ $key->maskedPrefix() }}</code></td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($key->abilities ?? [] as $ability)
                                            <span class="badge badge-gray">
                                                {{ $ability === '*' ? __('ui.common.all') : __('ui.api_keys.scope_'.match ($ability) {
                                                    'gateway:chat' => 'chat',
                                                    'gateway:models' => 'models',
                                                    'desktop' => 'desktop',
                                                    default => 'chat',
                                                }) }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="whitespace-nowrap text-xs">{{ $key->created_at?->translatedFormat('d M Y') }}</td>
                                <td class="whitespace-nowrap text-xs text-ink-400">{{ $key->last_used_at?->diffForHumans() ?? __('ui.common.never') }}</td>
                                <td>
                                    @if ($key->revoked_at)
                                        <span class="badge badge-red">{{ __('ui.statuses.revoked') }}</span>
                                    @elseif ($key->expires_at?->isPast())
                                        <span class="badge badge-gray">{{ __('ui.statuses.expired') }}</span>
                                    @else
                                        <span class="badge badge-green">{{ __('ui.statuses.active') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($canManage && $key->revoked_at === null)
                                        <form
                                            method="POST"
                                            action="{{ route('dashboard.api-keys.destroy', $key) }}"
                                            x-data
                                            @submit="if (! confirm(@js(__('ui.api_keys.confirm_revoke')))) { $event.preventDefault(); }"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm text-red-600 hover:bg-red-50">{{ __('ui.api_keys.revoke') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[1.2fr_1fr]">
        @if ($canManage)
            <div id="new-key" class="card card-pad scroll-mt-24">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-ink-950 text-lime"><x-icon name="key" class="size-5" /></span>
                    <div>
                        <h2 class="font-semibold">{{ __('ui.api_keys.create') }}</h2>
                        <p class="mt-1 text-xs leading-6 text-ink-400">{{ __('ui.api_keys.subtitle') }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('dashboard.api-keys.store') }}" class="mt-6 space-y-5">
                    @csrf
                    <div>
                        <label class="label" for="name">{{ __('ui.api_keys.name') }}</label>
                        <input id="name" type="text" name="name" value="{{ old('name') }}" required maxlength="120" class="input h-11 @error('name') input-error @enderror" placeholder="{{ __('ui.api_keys.name_placeholder') }}">
                        @error('name')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <p class="label">{{ __('ui.api_keys.scopes') }}</p>
                        <div class="space-y-2">
                            @foreach ([
                                ['gateway:chat', __('ui.api_keys.scope_chat')],
                                ['gateway:models', __('ui.api_keys.scope_models')],
                                ['desktop', __('ui.api_keys.scope_desktop')],
                            ] as [$ability, $label])
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ink-950/10 p-3.5 text-sm transition hover:border-ink-950/25">
                                    <input type="checkbox" name="abilities[]" value="{{ $ability }}" class="checkbox" @checked(in_array($ability, old('abilities', ['gateway:chat', 'gateway:models']), true))>
                                    <span>{{ $label }}</span>
                                    <code class="ms-auto font-mono text-[10px] text-ink-300" dir="ltr">{{ $ability }}</code>
                                </label>
                            @endforeach
                        </div>
                        @error('abilities')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary h-11 w-full">
                        <x-icon name="plus" class="size-4" />
                        {{ __('ui.api_keys.create') }}
                    </button>
                </form>
            </div>
        @endif

        <div class="space-y-4">
            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5">
                <div class="flex items-start gap-3">
                    <x-icon name="alert" class="mt-0.5 size-5 text-amber-600" />
                    <div>
                        <p class="text-sm font-semibold text-amber-900">{{ __('ui.api_keys.warning_title') }}</p>
                        <p class="mt-1 text-xs leading-6 text-amber-800">{{ __('ui.api_keys.warning_body') }}</p>
                    </div>
                </div>
            </div>

            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">{{ __('ui.developer.quickstart') }}</h2>
                    <a href="{{ route('docs') }}" class="text-xs font-semibold text-ink-500 hover:text-ink-950">{{ __('ui.dashboard.nav.docs') }}</a>
                </div>
                <div class="mt-4 rounded-2xl bg-ink-950 p-4">
                    <pre class="overflow-x-auto font-mono text-[11px] leading-6 text-lime-soft" dir="ltr">curl {{ url('/v1/chat/completions') }} \
  -H "Authorization: Bearer ixo_live_…" \
  -H "Content-Type: application/json" \
  -d '{"model":"gpt-4o-mini",
       "messages":[{"role":"user",
       "content":"Hello"}]}'</pre>
                </div>
                <p class="mt-3 text-[11px] leading-5 text-ink-400">{{ __('ui.api_keys.created_warning') }}</p>
            </div>
        </div>
    </div>
@endsection
