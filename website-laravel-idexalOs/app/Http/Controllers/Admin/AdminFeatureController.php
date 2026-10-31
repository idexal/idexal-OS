<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AuditLog;
use App\Services\SettingsService;
use App\Services\UsageService;
use App\Support\DesktopSwitch;
use App\Support\DesktopSwitchState;
use App\Support\DesktopSync;
use App\Support\GatewayFeature;
use App\Support\GatewayFeatureState;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * The gateway feature pipeline. Each toggle is a platform-level capability the
 * operator owns: it is applied to every provider and every model the gateway
 * serves, so behaviour is identical no matter which upstream answers.
 */
class AdminFeatureController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly UsageService $usage,
    ) {}

    public function edit(): View
    {
        $models = $this->availableModels();

        return view('admin.features', [
            'features' => collect(GatewayFeature::cases())
                ->map(fn (GatewayFeature $feature): array => [
                    'key' => $feature->value,
                    'field' => $feature->fieldKey(),
                    'name' => __($feature->labelKey()),
                    'body' => __($feature->bodyKey()),
                    'beta' => $feature->isBeta(),
                    'note' => $feature->noteKey() === null ? null : __($feature->noteKey()),
                    'on' => $this->settings->boolean($feature->settingKey()),
                ])
                ->all(),
            'recentUsage' => $this->recentUsage(),
            'previousUsage' => $this->previousUsage(),
            'switches' => $this->weeklySwitches(),
            'desktop' => collect(DesktopSwitch::cases())
                ->map(fn (DesktopSwitch $switch): array => [
                    'key' => $switch->value,
                    'field' => $switch->fieldKey(),
                    'name' => __($switch->labelKey()),
                    'body' => __($switch->bodyKey()),
                    'on' => $this->settings->boolean($switch->settingKey()),
                ])
                ->all(),
            'values' => $this->formValues(),
            'models' => $models,
            'modelIds' => $models->pluck('id'),
            'overrides' => $this->overrideSummary(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // The pickers only offer active models, so anything else is a model the
        // gateway could never route to: reject it rather than pin a feature to a
        // target that silently does nothing.
        $validated = $request->validate([
            'web_search_price' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vision_model_id' => ['nullable', 'string', 'max:190', Rule::in($this->availableModels()->pluck('id')->all())],
            'default_model_id' => ['nullable', 'string', 'max:190', Rule::in($this->availableModels()->pluck('id')->all())],
            'patient_retry_attempts' => ['nullable', 'integer', 'min:1', 'max:20'],
            'patient_retry_budget' => ['nullable', 'integer', 'min:5', 'max:600'],
        ]);

        $settings = $this->submittedSettings($request, $validated);
        $togglesBefore = GatewayFeatureState::toggles($this->settings);
        $desktopBefore = DesktopSwitchState::toggles($this->settings);
        $valuesBefore = $this->valueSettings();

        $this->settings->setMany($settings, 'features');

        $featureChanges = GatewayFeatureState::between($togglesBefore, GatewayFeatureState::toggles($this->settings));
        $desktopChanges = DesktopSwitchState::between($desktopBefore, DesktopSwitchState::toggles($this->settings));
        $valueChanges = $this->valueChanges($valuesBefore, $this->valueSettings());

        // A save that leaves every toggle and every value untouched is not a
        // configuration change: recording it would bury the real edits in noise
        // and wake every connected desktop for nothing.
        if ($featureChanges === [] && $desktopChanges === [] && $valueChanges === []) {
            return redirect()->route('admin.features')->with('status', __('ui.common.saved'));
        }

        $meta = [];

        if ($featureChanges !== []) {
            $meta['features'] = $featureChanges;
        }

        if ($desktopChanges !== []) {
            $meta['desktop'] = $desktopChanges;
        }

        if ($valueChanges !== []) {
            $meta['settings'] = $valueChanges;
        }

        AuditLog::record(
            'admin.features.updated',
            null,
            null,
            $this->describe($featureChanges, $desktopChanges) ?? __('ui.admin.features.title'),
            $meta,
        );
        DesktopSync::announce('features_updated');

        return redirect()->route('admin.features')->with('status', __('ui.common.saved'));
    }

    /**
     * The settings row each feature field writes, in the shape setMany expects.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function submittedSettings(Request $request, array $validated): array
    {
        $settings = [
            'feature_web_search_price' => (float) ($validated['web_search_price'] ?? 0.015),
            'feature_vision_model_id' => trim((string) ($validated['vision_model_id'] ?? '')),
            'feature_default_model_id' => trim((string) ($validated['default_model_id'] ?? '')),
            'feature_patient_retry_attempts' => (int) ($validated['patient_retry_attempts'] ?? 6),
            'feature_patient_retry_budget' => (int) ($validated['patient_retry_budget'] ?? 60),
            'feature_require_plan_selection' => $request->boolean('require_plan_selection') ? 1 : 0,
            'gateway_anthropic_desktop' => $request->boolean('gateway_anthropic_desktop') ? 1 : 0,
        ];

        foreach (GatewayFeature::cases() as $feature) {
            $settings[$feature->settingKey()] = $request->boolean($feature->fieldKey());
        }

        foreach (DesktopSwitch::cases() as $switch) {
            $settings[$switch->settingKey()] = $request->boolean($switch->fieldKey());
        }

        return $settings;
    }

    /**
     * One description for a save that can move gateway toggles, desktop
     * switches, or both. Null when no toggle moved (value-only saves).
     *
     * @param  array<string, array{from: bool, to: bool}>  $features
     * @param  array<string, array{from: bool, to: bool}>  $desktop
     */
    private function describe(array $features, array $desktop): ?string
    {
        $parts = array_filter([
            GatewayFeatureState::describe($features),
            DesktopSwitchState::describe($desktop),
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * The current value settings, normalised the same way the submitted ones are
     * so a diff never reports a change that is only a type difference.
     *
     * @return array<string, float|int|string>
     */
    private function valueSettings(): array
    {
        return [
            'feature_web_search_price' => (float) $this->settings->get('feature_web_search_price', 0.015),
            'feature_vision_model_id' => (string) $this->settings->get('feature_vision_model_id', ''),
            'feature_default_model_id' => (string) $this->settings->get('feature_default_model_id', ''),
            'feature_patient_retry_attempts' => $this->settings->integer('feature_patient_retry_attempts', 6),
            'feature_patient_retry_budget' => $this->settings->integer('feature_patient_retry_budget', 60),
            'feature_require_plan_selection' => $this->settings->boolean('feature_require_plan_selection') ? 1 : 0,
            'gateway_anthropic_desktop' => $this->settings->boolean('gateway_anthropic_desktop') ? 1 : 0,
        ];
    }

    /**
     * The same values keyed the way the panel's inputs name them, so the form and
     * the diff can never disagree about a default.
     *
     * @return array<string, float|int|string>
     */
    private function formValues(): array
    {
        $values = [];

        foreach ($this->valueSettings() as $key => $value) {
            $values[str_replace('feature_', '', $key)] = $value;
        }

        return $values;
    }

    /**
     * Only the value settings whose stored value actually moved.
     *
     * @param  array<string, float|int|string>  $before
     * @param  array<string, float|int|string>  $after
     * @return array<string, array{from: float|int|string|null, to: float|int|string}>
     */
    private function valueChanges(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $key => $value) {
            $previous = $before[$key] ?? null;

            if ($previous !== $value) {
                $changes[$key] = ['from' => $previous, 'to' => $value];
            }
        }

        return $changes;
    }

    /**
     * Platform-wide requests each feature ran on in the last week, so an operator
     * can tell a toggle that is working from one that never fires.
     *
     * @return array<string, int>
     */
    private function recentUsage(): array
    {
        return $this->usage->featureTotals(null, now()->subDays(6)->startOfDay(), now());
    }

    /**
     * The same count for the seven days before, so a rise after a toggle is
     * visible next to the toggle itself instead of needing the log.
     *
     * @return array<string, int> feature => requests
     */
    private function previousUsage(): array
    {
        return $this->usage->featureTotals(null, now()->subDays(13)->startOfDay(), now()->subDays(7));
    }

    /**
     * Every feature an operator switched in the last seven days, with the person
     * and the moment. A platform toggle applies to every provider and model, so
     * this list is the whole story of "who turned what on", which the cards
     * alone cannot show.
     *
     * @return list<array{at: Carbon, actor: string, feature: string, on: bool}>
     */
    private function weeklySwitches(): array
    {
        $entries = AuditLog::query()
            ->with('user:id,name,email')
            ->where('action', 'admin.features.updated')
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $switches = [];

        foreach ($entries as $entry) {
            $changes = $entry->meta['features'] ?? [];

            if (is_array($changes)) {
                foreach ($changes as $key => $change) {
                    $feature = GatewayFeature::tryFrom((string) $key);

                    if (! $feature instanceof GatewayFeature || ! is_array($change)) {
                        continue;
                    }

                    $switches[] = [
                        'at' => $entry->created_at,
                        'actor' => $entry->user?->name ?? __('ui.admin.audit.system'),
                        'feature' => __($feature->labelKey()),
                        'on' => (bool) ($change['to'] ?? false),
                    ];
                }
            }

            // The same save can flip a desktop switch, so the list shows both
            // kinds in one chronology instead of hiding half of the history.
            $desktopChanges = $entry->meta['desktop'] ?? [];

            if (! is_array($desktopChanges)) {
                continue;
            }

            foreach ($desktopChanges as $key => $change) {
                $switch = DesktopSwitch::tryFrom((string) $key);

                if (! $switch instanceof DesktopSwitch || ! is_array($change)) {
                    continue;
                }

                $switches[] = [
                    'at' => $entry->created_at,
                    'actor' => $entry->user?->name ?? __('ui.admin.audit.system'),
                    'feature' => __($switch->labelKey()),
                    'on' => (bool) ($change['to'] ?? false),
                ];
            }
        }

        return $switches;
    }

    /**
     * How many providers and models pin each feature on or off, so the panel
     * shows at a glance where the platform default is being overridden.
     *
     * @return array<string, array{on: int, off: int}>
     */
    private function overrideSummary(): array
    {
        $summary = array_fill_keys(GatewayFeature::keys(), ['on' => 0, 'off' => 0]);

        $sources = [
            ...AiProvider::query()->get(['feature_overrides'])->pluck('feature_overrides'),
            ...AiModel::query()->get(['feature_overrides'])->pluck('feature_overrides'),
        ];

        foreach ($sources as $overrides) {
            if (! is_array($overrides)) {
                continue;
            }

            foreach ($overrides as $feature => $value) {
                if (! isset($summary[$feature])) {
                    continue;
                }

                $summary[$feature][$value ? 'on' : 'off']++;
            }
        }

        return $summary;
    }

    /**
     * Every active model id the gateway can route to, with its provider name,
     * so the model pickers only ever offer something that resolves.
     *
     * @return Collection<int, array{id: string, label: string}>
     */
    private function availableModels(): Collection
    {
        return AiModel::query()
            ->with('provider')
            ->where('is_active', true)
            ->orderBy('model_id')
            ->get()
            ->map(fn (AiModel $model): array => [
                'id' => (string) $model->model_id,
                'label' => trim((string) ($model->display_name ?: $model->model_id)),
            ])
            ->unique('id')
            ->values();
    }
}
