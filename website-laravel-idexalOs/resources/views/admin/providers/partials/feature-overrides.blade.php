@php
    /**
     * A compact, three-way per-feature control shared by the provider form and
     * every model row. $featureLabels maps a feature key to its label;
     * $selected maps the same key to inherit|on|off; $platformFeatures maps the
     * same key to the platform toggle "inherit" falls back to (optional).
     *
     * @var array<string, string> $featureLabels
     * @var array<string, string> $selected
     * @var array<string, bool> $platformFeatures
     */
    $field = $field ?? 'feature_overrides';
    $platformFeatures = $platformFeatures ?? [];
@endphp

<div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($featureLabels as $feature => $label)
        <label class="flex items-center gap-2">
            <span class="min-w-0 flex-1 truncate text-xs text-ink-500" title="{{ $label }}">{{ $label }}</span>
            <select name="{{ $field }}[{{ $feature }}]" class="select h-8 w-28 text-xs">
                <option value="inherit" @selected(($selected[$feature] ?? 'inherit') === 'inherit')>{{ __('ui.admin.providers.feature_inherit') }}@if (array_key_exists($feature, $platformFeatures)) ({{ $platformFeatures[$feature] ? __('ui.admin.providers.feature_on') : __('ui.admin.providers.feature_off') }})@endif</option>
                <option value="on" @selected(($selected[$feature] ?? 'inherit') === 'on')>{{ __('ui.admin.providers.feature_on') }}</option>
                <option value="off" @selected(($selected[$feature] ?? 'inherit') === 'off')>{{ __('ui.admin.providers.feature_off') }}</option>
            </select>
        </label>
    @endforeach
</div>
