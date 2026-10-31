@extends('layouts.admin')

@section('title', __('ui.admin.agent_permissions.title'))
@section('page', __('ui.admin.nav.agent_permissions'))

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.agent_permissions.title') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-ink-500">{{ __('ui.admin.agent_permissions.subtitle') }}</p>
        </div>

        @if ($organizations->isNotEmpty())
            <form method="GET" action="{{ route('admin.agent-permissions') }}" class="flex items-end gap-2">
                <div>
                    <label class="label" for="organization">{{ __('ui.admin.agent_permissions.organization') }}</label>
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

    @if (! $organization instanceof \App\Models\Organization)
        <section class="card card-pad">
            <p class="text-sm text-ink-500">{{ __('ui.admin.agent_permissions.needs_organization') }}</p>
        </section>
    @else
        <form method="POST" action="{{ route('admin.agent-permissions.update') }}" class="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
            @csrf
            @method('PATCH')
            <input type="hidden" name="organization" value="{{ $organization->getKey() }}">

            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($sections as $section)
                    <section class="card card-pad {{ count($section['rows']) > 2 ? 'md:col-span-2' : '' }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <h2 class="font-semibold">{{ $section['label'] }}</h2>
                            <span class="badge badge-gray">{{ count($section['rows']) }}</span>
                        </div>

                        <div class="mt-3 space-y-3">
                            @foreach ($section['rows'] as $row)
                                @php($permission = $row['permission'])
                                <div class="rounded-xl border border-ink-950/10 p-3">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0 max-w-lg">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-xs font-semibold">{{ $row['name'] }}</span>
                                                <span class="font-mono text-[9px] text-ink-300" dir="ltr">{{ $permission->action() }}</span>
                                            </div>
                                            <p class="mt-1 text-[10px] leading-4 text-ink-500">{{ $row['body'] }}</p>
                                        </div>

                                        <div class="w-full sm:w-40">
                                            <select
                                                name="decision[{{ $permission->value }}]"
                                                class="select h-9 text-xs"
                                                aria-label="{{ $row['name'] }}"
                                            >
                                                @foreach ($row['decisions'] as $decision)
                                                    <option value="{{ $decision['value'] }}" @selected(old('decision.'.$permission->value, $row['decision']) === $decision['value'])>{{ $decision['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    @if ($row['patterns'] !== null)
                                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <label class="label" for="allow_{{ $permission->value }}">{{ __('ui.admin.agent_permissions.always_allowed') }}</label>
                                                <textarea
                                                    id="allow_{{ $permission->value }}"
                                                    name="allow[{{ $permission->value }}]"
                                                    rows="2"
                                                    class="input text-[11px] font-mono"
                                                    dir="ltr"
                                                    placeholder="{{ $permission->patternKind() === 'website' ? 'docs.example.com' : 'npm test *' }}"
                                                >{{ old('allow.'.$permission->value, $row['allow']) }}</textarea>
                                                @error('allow.'.$permission->value)
                                                    <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div>
                                                <label class="label" for="block_{{ $permission->value }}">{{ __('ui.admin.agent_permissions.always_blocked') }}</label>
                                                <textarea
                                                    id="block_{{ $permission->value }}"
                                                    name="block[{{ $permission->value }}]"
                                                    rows="2"
                                                    class="input text-[11px] font-mono"
                                                    dir="ltr"
                                                    placeholder="{{ $permission->patternKind() === 'website' ? '*.internal.example' : 'rm -rf *' }}"
                                                >{{ old('block.'.$permission->value, $row['block']) }}</textarea>
                                                @error('block.'.$permission->value)
                                                    <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                        <p class="mt-1.5 text-[10px] leading-4 text-ink-400">{{ __('ui.admin.agent_permissions.patterns_hint') }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            <div class="space-y-4">
                <section class="card card-pad xl:sticky xl:top-24">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h2 class="font-semibold">{{ __('ui.admin.agent_permissions.rules_title') }}</h2>
                            <p class="mt-1 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.agent_permissions.rules_hint') }}</p>
                        </div>
                        <span class="badge {{ $restricted ? 'badge-lime' : 'badge-gray' }}">
                            {{ $restricted ? __('ui.admin.agent_permissions.status_restricted') : __('ui.admin.agent_permissions.status_open') }}
                        </span>
                    </div>

                    <button type="submit" class="btn btn-primary mt-3 h-11 w-full">{{ __('ui.admin.agent_permissions.save') }}</button>

                    @if ($rules === [])
                        <p class="mt-3 rounded-xl bg-paper p-3 text-[11px] leading-5 text-ink-500">{{ __('ui.admin.agent_permissions.rules_empty') }}</p>
                    @else
                        <div class="mt-3 max-h-96 overflow-auto rounded-xl border border-ink-950/10">
                            <table class="w-full text-start text-[10px]">
                                <thead class="sticky top-0 bg-white">
                                    <tr class="border-b border-ink-950/5 text-[9px] uppercase tracking-wide text-ink-400">
                                        <th class="p-2 text-start font-medium">{{ __('ui.admin.agent_permissions.rules_action') }}</th>
                                        <th class="p-2 text-start font-medium">{{ __('ui.admin.agent_permissions.rules_resource') }}</th>
                                        <th class="p-2 text-start font-medium">{{ __('ui.admin.agent_permissions.rules_effect') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rules as $rule)
                                        <tr class="border-b border-ink-950/5 last:border-0">
                                            <td class="p-2 font-mono text-ink-500" dir="ltr">{{ $rule['action'] }}</td>
                                            <td class="max-w-[160px] truncate p-2 font-mono text-ink-600" dir="ltr" title="{{ $rule['resource'] }}">{{ $rule['resource'] }}</td>
                                            <td class="p-2">
                                                <span class="badge {{ $rule['effect'] === 'deny' ? 'badge-red' : ($rule['effect'] === 'ask' ? 'badge-amber' : 'badge-green') }}">
                                                    {{ __('ui.admin.agent_permissions.decision_'.$rule['effect']) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-2 text-[10px] leading-4 text-ink-400">{{ __('ui.admin.agent_permissions.rules_order') }}</p>
                    @endif

                    <p class="mt-3 border-t border-ink-950/5 pt-3 text-[10px] leading-4 text-ink-400">{{ __('ui.admin.agent_permissions.engine_note') }}</p>

                    @if ($policy !== null)
                        <p class="mt-2 text-[10px] leading-4 text-ink-300">{{ __('ui.admin.agent_permissions.updated', ['when' => $policy->updated_at?->translatedFormat('d M Y H:i')]) }}</p>
                    @endif
                </section>
            </div>
        </form>
    @endif
@endsection
