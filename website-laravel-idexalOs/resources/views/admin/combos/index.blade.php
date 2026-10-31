@extends('layouts.admin')

@section('title', __('ui.admin.combos.title'))
@section('page', __('ui.admin.nav.combos'))

@php
    $roleOptions = ['general', 'coding', 'reasoning', 'vision', 'search', 'image'];

    /**
     * A short capability profile for the member row: search, coding, vision,
     * media, or general when the model advertises none of them.
     *
     * @return list<string>
     */
    $profileFor = function ($model): array {
        $caps = (array) ($model->capabilities ?? []);
        $badges = [];

        if ($caps['search'] ?? false) {
            $badges[] = 'search';
        }
        if ($caps['tools'] ?? false) {
            $badges[] = 'coding';
        }
        if (($caps['vision'] ?? false) || ($caps['image_input'] ?? false)) {
            $badges[] = 'vision';
        }
        if (($caps['image'] ?? false) || ($caps['audio'] ?? false) || ($caps['video'] ?? false) || ($caps['media'] ?? false)) {
            $badges[] = 'media';
        }
        if ($badges === []) {
            $badges[] = 'general';
        }

        return $badges;
    };

    $defaultWeight = fn ($member): int => (int) ($member->provider?->priority ?? 1);

    // Default attempt order for the create form: the order members are listed in.
    $memberPositions = [];
    foreach ($members->values() as $position => $member) {
        $memberPositions[$member->getKey()] = $position + 1;
    }

    // The combo whose edit panel was rejected, if any.
    $editingComboId = session('combo_edit');
@endphp

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.combos.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.combos.subtitle') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.combos.sync') }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm h-10 px-4">
                    <x-icon name="refresh" class="size-4" />
                    {{ __('ui.admin.combos.rebuild') }}
                </button>
            </form>

            <a href="{{ route('admin.combos.suggest') }}" class="btn btn-secondary btn-sm h-10 px-4">
                <x-icon name="sparkles" class="size-4" />
                {{ __('ui.admin.combos.suggestions') }}
            </a>

            <a href="{{ route('admin.providers.index') }}" class="btn btn-outline btn-sm h-10 px-4">
                <x-icon name="server" class="size-4" />
                {{ __('ui.admin.nav.providers') }}
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any() && ! $editingComboId)
        <div class="alert alert-warning">
            <ul class="list-inside list-disc text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card card-pad">
        <h2 class="font-semibold">{{ __('ui.admin.combos.create_title') }}</h2>
        <p class="mt-1 text-xs leading-6 text-ink-400">{{ __('ui.admin.combos.create_hint') }}</p>

        <form method="POST" action="{{ route('admin.combos.store') }}" class="mt-4 space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="label" for="combo-name">{{ __('ui.admin.combos.name') }}</label>
                    <input id="combo-name" name="name" value="{{ old('name') }}" required maxlength="190" class="input h-10" placeholder="idexal-coding">
                </div>
                <div>
                    <label class="label" for="combo-slug">{{ __('ui.admin.combos.slug') }}</label>
                    <input id="combo-slug" name="slug" value="{{ old('slug') }}" maxlength="160" class="input h-10 font-mono text-xs" dir="ltr" placeholder="idexal-coding">
                </div>
                <div>
                    <label class="label" for="combo-strategy">{{ __('ui.admin.combos.strategy') }}</label>
                    <select id="combo-strategy" name="strategy" class="select h-10">
                        @foreach (['round_robin', 'weighted', 'role', 'failover'] as $value)
                            <option value="{{ $value }}" @selected(old('strategy', 'round_robin') === $value)>{{ __('ui.admin.combos.strategies.'.$value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="combo-description">{{ __('ui.admin.combos.description') }}</label>
                    <input id="combo-description" name="description" value="{{ old('description') }}" maxlength="1000" class="input h-10">
                </div>
            </div>

            <div>
                <div class="label">{{ __('ui.admin.combos.members') }}</div>
                <div class="max-h-[420px] space-y-4 overflow-y-auto rounded-xl border border-ink-950/10 p-4">
                    @foreach ($members->groupBy(fn ($member) => $member->provider?->name ?? 'Provider') as $providerName => $group)
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-ink-400">{{ $providerName }}</div>
                            <div class="mt-2 grid gap-2">
                                @foreach ($group as $member)
                                    <div class="flex flex-wrap items-center gap-3 rounded-lg border border-ink-950/5 px-3 py-2">
                                        <label class="flex min-w-0 flex-1 items-center gap-2 text-sm">
                                            <input type="checkbox" name="member_ids[]" value="{{ $member->getKey() }}" class="checkbox" @checked(in_array($member->getKey(), old('member_ids', []), true))>
                                            <span class="truncate">{{ $member->display_name ?: $member->model_id }}</span>
                                            <span class="truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $member->model_id }}</span>
                                        </label>
                                        <div class="flex flex-wrap items-center gap-1">
                                            @foreach ($profileFor($member) as $badge)
                                                <span class="badge badge-gray">{{ __('ui.admin.combos.capabilities.'.$badge) }}</span>
                                            @endforeach
                                        </div>
                                        <select name="roles[{{ $member->getKey() }}]" class="select h-8 w-32 text-xs">
                                            @foreach ($roleOptions as $role)
                                                <option value="{{ $role }}" @selected(old('roles.'.$member->getKey(), 'general') === $role)>{{ __('ui.admin.combos.roles.'.$role) }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" name="weights[{{ $member->getKey() }}]" min="0" max="100" value="{{ old('weights.'.$member->getKey(), $defaultWeight($member)) }}" class="input h-8 w-20 text-xs font-mono" dir="ltr" title="{{ __('ui.admin.combos.weight') }}">
                                        <input type="number" name="orders[{{ $member->getKey() }}]" min="0" max="999" value="{{ old('orders.'.$member->getKey(), $memberPositions[$member->getKey()]) }}" class="input h-8 w-16 text-xs font-mono" dir="ltr" title="{{ __('ui.admin.combos.order') }}">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="mt-1.5 text-[10px] leading-4 text-ink-400">{{ __('ui.admin.combos.weight_hint') }}</p>
                <p class="mt-1 text-[10px] leading-4 text-ink-400">{{ __('ui.admin.combos.order_hint') }}</p>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary btn-sm h-10 px-5">{{ __('ui.admin.combos.create') }}</button>
            </div>
        </form>
    </div>

    <div class="card card-pad">
        <h2 class="font-semibold">{{ __('ui.admin.combos.existing_title') }}</h2>
        <p class="mt-1 text-xs leading-6 text-ink-400">{{ __('ui.admin.combos.existing_hint') }}</p>
        <p class="mt-1 text-[10px] leading-4 text-ink-400">{{ __('ui.admin.combos.plan_rule') }}</p>

        <div class="mt-4 space-y-3">
            @forelse ($combos as $combo)
                @php
                    $config = is_array($combo->hybrid_config) ? $combo->hybrid_config : [];
                    $selected = collect($config['members'] ?? [])->keyBy('ai_model_id');
                    // Stored members that are no longer eligible: they still
                    // count toward this combo, but cannot be shown as a choice.
                    $unavailableIds = collect($config['members'] ?? [])
                        ->pluck('ai_model_id')
                        ->filter(fn ($id) => is_string($id) && $id !== '')
                        ->reject(fn ($id) => in_array($id, $eligibleMemberIds, true))
                        ->values();
                    $isEditing = $editingComboId === $combo->getKey();
                    $selectedIds = $isEditing
                        ? (array) old('member_ids', $selected->keys()->all())
                        : $selected->keys()->all();
                    // Only the rejected combo reads from old input: every other
                    // panel keeps showing what is actually stored.
                    $field = fn (string $key, mixed $fallback): mixed => $isEditing ? old($key, $fallback) : $fallback;
                @endphp
                <div class="rounded-xl border border-ink-950/10 p-4" x-data="{ open: @js($isEditing) }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-sm font-semibold" dir="ltr">{{ $combo->model_id }}</span>
                                <span class="badge {{ $combo->is_active ? 'badge-lime' : 'badge-gray' }}">{{ $combo->is_active ? __('ui.admin.combos.active') : __('ui.admin.combos.inactive') }}</span>
                                <span class="badge badge-gray">{{ ($config['auto'] ?? false) ? __('ui.admin.combos.automatic') : __('ui.admin.combos.manual') }}</span>
                                <span class="badge badge-blue">{{ __('ui.admin.combos.strategies.'.($config['strategy'] ?? 'round_robin')) }}</span>
                            </div>
                            <div class="mt-1 text-sm text-ink-500">{{ $combo->display_name }}</div>
                            <div class="mt-1 text-xs text-ink-400">
                                {{ __('ui.admin.combos.members_count', ['count' => count($config['members'] ?? [])]) }} ·
                                {{ $combo->context_window ? __('ui.admin.combos.context', ['value' => number_format((int) $combo->context_window)]) : __('ui.admin.combos.context_inherited') }}
                                @if ($unavailableIds->isNotEmpty())
                                    · <span class="badge badge-amber">{{ __('ui.admin.combos.unavailable_count', ['count' => $unavailableIds->count()]) }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="btn btn-secondary btn-sm h-9 px-4" @click="open = ! open">
                                {{ __('ui.admin.combos.edit') }}
                            </button>
                            <form method="POST" action="{{ route('admin.combos.destroy', $combo) }}" onsubmit="return confirm(@js(__('ui.admin.combos.confirm_remove')))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline btn-sm h-9 px-4">{{ __('ui.admin.combos.remove') }}</button>
                            </form>
                        </div>
                    </div>

                    <div x-show="open" x-cloak class="mt-4 border-t border-ink-950/10 pt-4">
                        @if ($isEditing && $errors->any())
                            <div class="alert alert-warning mb-4">
                                <ul class="list-inside list-disc text-sm">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('admin.combos.update', $combo) }}" class="space-y-4">
                            @csrf
                            @method('PATCH')
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label class="label" for="edit-name-{{ $combo->getKey() }}">{{ __('ui.admin.combos.name') }}</label>
                                    <input id="edit-name-{{ $combo->getKey() }}" name="name" value="{{ $field('name', $combo->display_name) }}" required maxlength="190" class="input h-10">
                                </div>
                                <div>
                                    <label class="label" for="edit-strategy-{{ $combo->getKey() }}">{{ __('ui.admin.combos.strategy') }}</label>
                                    <select id="edit-strategy-{{ $combo->getKey() }}" name="strategy" class="select h-10">
                                        @foreach (['round_robin', 'weighted', 'role', 'failover'] as $value)
                                            <option value="{{ $value }}" @selected((string) $field('strategy', $config['strategy'] ?? 'round_robin') === $value)>{{ __('ui.admin.combos.strategies.'.$value) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="label" for="edit-description-{{ $combo->getKey() }}">{{ __('ui.admin.combos.description') }}</label>
                                    <input id="edit-description-{{ $combo->getKey() }}" name="description" value="{{ $field('description', $combo->description) }}" maxlength="1000" class="input h-10">
                                </div>
                            </div>

                            <div>
                                <div class="label">{{ __('ui.admin.combos.members') }}</div>
                                <div class="max-h-[320px] space-y-2 overflow-y-auto rounded-xl border border-ink-950/10 p-4">
                                    @foreach ($members as $member)
                                        @php($current = $selected->get($member->getKey(), []))
                                        <div class="flex flex-wrap items-center gap-3 rounded-lg border border-ink-950/5 px-3 py-2">
                                            <label class="flex min-w-0 flex-1 items-center gap-2 text-sm">
                                                <input type="checkbox" name="member_ids[]" value="{{ $member->getKey() }}" class="checkbox" @checked(in_array($member->getKey(), $selectedIds, true))>
                                                <span class="truncate">{{ $member->display_name ?: $member->model_id }}</span>
                                                <span class="truncate font-mono text-[10px] text-ink-400" dir="ltr">{{ $member->model_id }}</span>
                                            </label>
                                            <select name="roles[{{ $member->getKey() }}]" class="select h-8 w-32 text-xs">
                                                @foreach ($roleOptions as $role)
                                                    <option value="{{ $role }}" @selected((string) $field('roles.'.$member->getKey(), $current['role'] ?? 'general') === $role)>{{ __('ui.admin.combos.roles.'.$role) }}</option>
                                                @endforeach
                                            </select>
                                            <input type="number" name="weights[{{ $member->getKey() }}]" min="0" max="100" value="{{ $field('weights.'.$member->getKey(), $current['weight'] ?? $defaultWeight($member)) }}" class="input h-8 w-20 text-xs font-mono" dir="ltr" title="{{ __('ui.admin.combos.weight') }}">
                                            <input type="number" name="orders[{{ $member->getKey() }}]" min="0" max="999" value="{{ $field('orders.'.$member->getKey(), (int) ($current['order'] ?? $memberPositions[$member->getKey()])) }}" class="input h-8 w-16 text-xs font-mono" dir="ltr" title="{{ __('ui.admin.combos.order') }}">
                                        </div>
                                    @endforeach
                                </div>
                                <p class="mt-1.5 text-[10px] leading-4 text-ink-400">{{ __('ui.admin.combos.order_hint') }}</p>
                                @if ($unavailableIds->isNotEmpty())
                                    <div class="mt-3 rounded-lg border border-amber-300/40 bg-amber-300/10 px-3 py-2.5 text-xs leading-5 text-ink-600">
                                        <p>{{ __('ui.admin.combos.unavailable_hint') }}</p>
                                        <ul class="mt-1 list-inside list-disc">
                                            @foreach ($unavailableIds as $unavailableId)
                                                <li class="truncate" dir="ltr">{{ $unavailableMembers[$unavailableId]?->display_name ?? $unavailableId }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="is_active" value="1" class="checkbox" @checked($isEditing ? old('is_active') !== null : $combo->is_active)>
                                    {{ __('ui.admin.combos.active') }}
                                </label>
                                <button type="submit" class="btn btn-primary btn-sm h-10 px-5">{{ __('ui.admin.combos.save') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-ink-950/15 p-6 text-sm text-ink-500">
                    {{ __('ui.admin.combos.empty') }}
                </div>
            @endforelse
        </div>
    </div>
@endsection
