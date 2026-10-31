<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\ProviderHealthLog;
use App\Models\Setting;
use App\Services\ComboModelService;
use App\Services\ProviderCooldownService;
use App\Services\ProviderFallbackService;
use App\Services\ProviderHealthMonitorService;
use App\Services\ProviderKeyRotationService;
use App\Services\SettingsService;
use App\Support\DesktopSync;
use App\Support\GatewayFeature;
use App\Support\GatewayFeatureState;
use App\Support\OutboundHttp;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminProviderController extends Controller
{
    /**
     * @var list<string>
     */
    private const CAPABILITIES = ['chat', 'streaming', 'tools', 'vision', 'reasoning'];

    /**
     * Values the desktop engine can materialize as reasoning-effort variants.
     *
     * @var list<string>
     */
    private const REASONING_OPTIONS = ['none', 'minimal', 'low', 'medium', 'high', 'xhigh'];

    public function __construct(
        private readonly ProviderKeyRotationService $keyRotation,
        private readonly ProviderFallbackService $fallback,
        private readonly ProviderHealthMonitorService $health,
        private readonly ProviderCooldownService $cooldown,
        private readonly SettingsService $settings,
    ) {}

    public function index(): View
    {
        return view('admin.providers.index', [
            'providers' => AiProvider::query()
                ->with('plans:id,name,slug')
                ->withCount(['models', 'apiKeys'])
                ->orderByDesc('is_active')
                ->orderBy('priority')
                ->get(),
            'health' => $this->health->latestByProvider(),
            'cooling' => $this->cooldown->coolingCountsByProvider(),
            // The platform-wide answer to "may members add their own keys at
            // all", so the operator can cancel BYOK for everyone from the same
            // screen that lists the providers.
            'byokEnabled' => $this->settings->boolean('byok_enabled', false),
            // And the answer to "must a model or provider be assigned to a plan
            // before anyone gets it": off means an unassigned row serves every
            // plan, on means the assignment is the only way in.
            'explicitAssignmentRequired' => $this->settings->boolean('gateway_require_explicit_assignment', false),
        ]);
    }

    /**
     * Turn one provider on or off from the list, without opening its form.
     *
     * The gateway reads `is_active` on every build of the desktop catalog, so
     * this is the operator's on/off switch for a whole provider — and the only
     * other thing that hides its models completely is the plan assignment.
     */
    public function toggle(Request $request, AiProvider $aiProvider): RedirectResponse
    {
        $aiProvider->forceFill(['is_active' => ! $aiProvider->is_active])->save();

        AuditLog::record('admin.provider.toggled', null, $aiProvider, $aiProvider->name, [
            'is_active' => (bool) $aiProvider->is_active,
        ]);

        DesktopSync::announce('provider_updated', ['provider' => $aiProvider->slug]);

        return back()->with('status', $aiProvider->is_active
            ? __('ui.admin.providers.toggled_on', ['provider' => $aiProvider->name])
            : __('ui.admin.providers.toggled_off', ['provider' => $aiProvider->name]));
    }

    /**
     * Whether an unassigned model or provider may still serve every plan.
     *
     * Off is the historic behaviour and stays the default. On makes an
     * assignment mandatory: nothing reaches a plan — models, providers,
     * and the combos derived from those models — until the operator assigns
     * it. The gateway and the desktop both read this one switch, so what the
     * console says and what a desktop offers can never disagree.
     */
    public function updateAssignment(Request $request): RedirectResponse
    {
        $required = $request->boolean('explicit_assignment_required');

        Setting::put('gateway_require_explicit_assignment', $required ? '1' : '0');

        AuditLog::record('admin.provider.assignment_policy', null, null, $required ? 'explicit' : 'open');
        DesktopSync::announce('provider_updated');

        return back()->with('status', __($required
            ? 'ui.admin.providers.assignment_on_message'
            : 'ui.admin.providers.assignment_off_message'));
    }

    /**
     * The platform-wide BYOK switch. Off means no member of any workspace may
     * add their own provider keys, whatever their plan says — the operator's
     * way to require the platform's own plans everywhere.
     */
    public function updateByok(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('byok_enabled');

        Setting::put('byok_enabled', $enabled ? '1' : '0');

        AuditLog::record('admin.byok.toggled', null, null, $enabled ? 'enabled' : 'disabled');
        DesktopSync::announce('features_updated');

        return back()->with('status', __($enabled
            ? 'ui.admin.providers.byok_enabled_message'
            : 'ui.admin.providers.byok_disabled_message'));
    }

    /**
     * Probe one provider now and record the result.
     */
    public function checkHealth(AiProvider $aiProvider): RedirectResponse
    {
        $result = $this->health->checkProviderHealth($aiProvider);
        $this->releaseHealthyCooldowns([$result]);

        return back()->with('status', __('ui.admin.providers.health_result', [
            'provider' => $aiProvider->name,
            'status' => __('ui.admin.providers.health_'.$result['status']),
        ]));
    }

    /**
     * Probe every active provider now and record the results.
     */
    public function checkAllHealth(): RedirectResponse
    {
        $results = $this->health->checkAllProviders();
        $this->releaseHealthyCooldowns($results->all());
        $unhealthy = $results->where('status', '!=', 'healthy')->count();

        return back()->with('status', __('ui.admin.providers.health_all_done', [
            'checked' => $results->count(),
            'unhealthy' => $unhealthy,
        ]));
    }

    /**
     * A green probe is the operator's proof the provider is back, so its
     * cooling targets return to the front of the fallback order immediately
     * instead of waiting out the cooldown.
     *
     * @param  list<array{provider_id: string, status: string}>  $results
     */
    private function releaseHealthyCooldowns(array $results): void
    {
        foreach ($results as $result) {
            if (($result['status'] ?? null) !== ProviderHealthLog::STATUS_HEALTHY) {
                continue;
            }

            $provider = AiProvider::query()->find($result['provider_id']);

            if ($provider instanceof AiProvider) {
                $this->cooldown->clearForProvider($provider);
            }
        }
    }

    public function create(): View
    {
        return view('admin.providers.form', [
            'provider' => new AiProvider([
                'driver' => AiProvider::DRIVER_OPENAI,
                'is_active' => true,
                'supports_streaming' => true,
            ]),
            'drivers' => AiProvider::drivers(),
            'capabilities' => self::CAPABILITIES,
            'reasoningOptions' => self::REASONING_OPTIONS,
            'plans' => $this->plans(),
            'featureKeys' => GatewayFeature::keys(),
            'platformFeatures' => $this->platformFeatureToggles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $plans = $this->validatedPlans($request);
        $provider = AiProvider::query()->create($this->payload($request));
        $provider->plans()->sync($plans);

        AuditLog::record('admin.provider.created', null, $provider, $provider->name);
        $this->refreshCombos();
        $this->broadcastSyncEvent('provider_created', $provider);

        return redirect()->route('admin.providers.edit', $provider)->with('status', __('ui.admin.providers.saved'));
    }

    public function edit(AiProvider $aiProvider): View
    {
        return view('admin.providers.form', [
            'provider' => $aiProvider,
            'drivers' => AiProvider::drivers(),
            'capabilities' => self::CAPABILITIES,
            'reasoningOptions' => self::REASONING_OPTIONS,
            'plans' => $this->plans(),
            'featureKeys' => GatewayFeature::keys(),
            'platformFeatures' => $this->platformFeatureToggles(),
            'models' => $aiProvider->models()->orderBy('sort_order')->orderBy('display_name')->get(),
            'backupProviders' => $aiProvider->backupProviders(),
            'candidateProviders' => AiProvider::query()
                ->whereKeyNot($aiProvider->getKey())
                ->orderBy('name')
                ->get(),
            'keyCount' => $aiProvider->apiKeys()->count(),
        ]);
    }

    public function update(Request $request, AiProvider $aiProvider): RedirectResponse
    {
        $plans = $this->validatedPlans($request);
        $overridesBefore = $aiProvider->feature_overrides;
        $aiProvider->forceFill($this->payload($request, $aiProvider))->save();
        $aiProvider->plans()->sync($plans);
        $this->recordFeaturePinChanges('admin.provider.updated', $aiProvider, $aiProvider->name, $overridesBefore, $aiProvider->feature_overrides);
        $this->refreshCombos();
        $this->broadcastSyncEvent('provider_updated', $aiProvider);

        return redirect()->route('admin.providers.edit', $aiProvider)->with('status', __('ui.admin.providers.saved'));
    }

    public function destroy(AiProvider $aiProvider): RedirectResponse
    {
        $aiProvider->delete();
        AuditLog::record('admin.provider.deleted', null, $aiProvider, $aiProvider->name);
        $this->refreshCombos();
        $this->broadcastSyncEvent('provider_deleted', $aiProvider);

        return redirect()->route('admin.providers.index')->with('status', __('ui.admin.providers.deleted'));
    }

    public function test(AiProvider $aiProvider): RedirectResponse
    {
        if (! is_string($aiProvider->api_key) || $aiProvider->api_key === '') {
            return back()->withErrors(['provider' => __('ui.admin.providers.no_key')]);
        }

        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'idexal-os-admin/1.0',
        ];

        if ($aiProvider->driver === AiProvider::DRIVER_ANTHROPIC) {
            $headers['x-api-key'] = $aiProvider->api_key;
            $headers['anthropic-version'] = '2023-06-01';
        } else {
            $headers['Authorization'] = 'Bearer '.$aiProvider->api_key;
        }

        try {
            $response = OutboundHttp::client()->withHeaders($headers)
                ->connectTimeout(8)
                ->timeout(20)
                ->get(rtrim($aiProvider->base_url, '/').'/models');
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['provider' => __('ui.admin.providers.test_failed', ['message' => $exception->getMessage()])]);
        }

        if (! $response->successful()) {
            return back()->withErrors([
                'provider' => __('ui.admin.providers.test_failed', ['message' => 'HTTP '.$response->status()]),
            ]);
        }

        return back()->with('status', __('ui.admin.providers.test_ok'));
    }

    public function storeModel(Request $request, AiProvider $aiProvider): RedirectResponse
    {
        $aiProvider->models()->create($this->modelPayload($request, $aiProvider));
        AuditLog::record('admin.provider_model.created', null, $aiProvider, $aiProvider->name);
        $this->refreshCombos();
        $this->broadcastSyncEvent('model_created', $aiProvider);

        return redirect()->route('admin.providers.edit', $aiProvider)->with('status', __('ui.admin.providers.model_saved'));
    }

    public function updateModel(Request $request, AiProvider $aiProvider, AiModel $aiModel): RedirectResponse
    {
        abort_unless($aiModel->ai_provider_id === $aiProvider->getKey(), 404);

        $overridesBefore = $aiModel->feature_overrides;
        $aiModel->forceFill($this->modelPayload($request, $aiProvider, $aiModel))->save();
        $this->recordFeaturePinChanges('admin.provider_model.updated', $aiModel, $aiModel->display_name, $overridesBefore, $aiModel->feature_overrides);
        $this->refreshCombos();
        $this->broadcastSyncEvent('model_updated', $aiModel);

        return redirect()->route('admin.providers.edit', $aiProvider)->with('status', __('ui.admin.providers.model_saved'));
    }

    public function destroyModel(AiProvider $aiProvider, AiModel $aiModel): RedirectResponse
    {
        abort_unless($aiModel->ai_provider_id === $aiProvider->getKey(), 404);

        $aiModel->delete();
        AuditLog::record('admin.provider_model.deleted', null, $aiProvider, $aiModel->display_name);
        $this->refreshCombos();
        $this->broadcastSyncEvent('model_deleted', $aiModel);

        return redirect()->route('admin.providers.edit', $aiProvider)->with('status', __('ui.admin.providers.model_deleted'));
    }

    /**
     * Ask the provider which models it offers and show them for selection.
     */
    public function fetchModels(AiProvider $aiProvider): View
    {
        $probe = $this->probeProviderModels($aiProvider);

        return view('admin.providers.models-fetch', [
            'provider' => $aiProvider,
            'available' => $probe['models'],
            'error' => $probe['error'],
            'existing' => $aiProvider->models()->pluck('model_id')->all(),
        ]);
    }

    /**
     * Import the chosen models in one go, skipping ones already registered.
     */
    public function importModels(Request $request, AiProvider $aiProvider): RedirectResponse
    {
        $validated = $request->validate([
            'models' => ['required', 'array', 'min:1'],
            'models.*' => ['string', 'max:190'],
            'input_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'output_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'context_window' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $existing = $aiProvider->models()->pluck('model_id')->all();
        $inputPrice = (float) ($validated['input_price'] ?? 0);
        $outputPrice = (float) ($validated['output_price'] ?? 0);
        $contextWindow = isset($validated['context_window']) ? (int) $validated['context_window'] : null;
        $isActive = $request->boolean('is_active', true);
        $created = 0;

        foreach (array_unique($validated['models']) as $modelId) {
            if (in_array($modelId, $existing, true)) {
                continue;
            }

            $aiProvider->models()->create([
                'model_id' => $modelId,
                'display_name' => Str::headline($modelId),
                'context_window' => $contextWindow,
                'max_output_tokens' => null,
                'input_price' => $inputPrice,
                'output_price' => $outputPrice,
                'capabilities' => [
                    'chat' => true,
                    'streaming' => true,
                    'tools' => false,
                    'vision' => false,
                    'reasoning' => false,
                ],
                'is_active' => $isActive,
                'sort_order' => 0,
            ]);

            $existing[] = $modelId;
            $created++;
        }

        AuditLog::record('admin.provider_model.imported', null, $aiProvider, (string) $created);
        $this->refreshCombos();
        $this->broadcastSyncEvent('models_imported', $aiProvider);

        return redirect()
            ->route('admin.providers.edit', $aiProvider)
            ->with('status', __('ui.admin.providers.imported', ['count' => $created]));
    }

    /**
     * Replace this provider's ordered fallback chain.
     */
    public function updateBackupChain(Request $request, AiProvider $aiProvider): RedirectResponse
    {
        $validated = $request->validate([
            'backup_providers' => ['nullable', 'array'],
            'backup_providers.*' => ['string', Rule::exists(AiProvider::class, 'id')],
            'backup_order' => ['nullable', 'array'],
            'backup_order.*' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        /** @var Collection<int, string> $order */
        $order = collect($validated['backup_order'] ?? [])->mapWithKeys(
            fn (mixed $position, mixed $id): array => [(string) $id => (int) $position]
        );

        $ids = collect($validated['backup_providers'] ?? [])
            ->map(fn (mixed $id): string => (string) $id)
            ->reject(fn (string $id): bool => $id === (string) $aiProvider->getKey())
            ->unique()
            ->values()
            ->sortBy(fn (string $id): int => $order->get($id, 999))
            ->values()
            ->all();

        $this->fallback->syncChain($aiProvider, $ids);

        AuditLog::record('admin.provider.fallback_chain', null, $aiProvider, implode(', ', $ids));
        $this->broadcastSyncEvent('provider_updated', $aiProvider);

        return redirect()->route('admin.providers.edit', $aiProvider)->with('status', __('ui.admin.providers.fallback_saved'));
    }

    /**
     * @return array{models: list<string>, error: string|null}
     */
    private function probeProviderModels(AiProvider $aiProvider): array
    {
        $apiKey = $this->firstProbeKey($aiProvider);

        if ($apiKey === null) {
            return ['models' => [], 'error' => __('ui.admin.providers.no_key')];
        }

        try {
            $response = OutboundHttp::client()
                ->withHeaders($this->probeHeaders($aiProvider, $apiKey))
                ->connectTimeout(8)
                ->timeout(20)
                ->get(rtrim($aiProvider->base_url, '/').'/models');
        } catch (\Throwable $exception) {
            report($exception);

            return ['models' => [], 'error' => __('ui.admin.providers.test_failed', ['message' => $exception->getMessage()])];
        }

        if (! $response->successful()) {
            return ['models' => [], 'error' => __('ui.admin.providers.test_failed', ['message' => 'HTTP '.$response->status()])];
        }

        $models = collect($response->json('data', []))
            ->map(function (mixed $model): ?string {
                if (is_string($model)) {
                    return $model;
                }

                if (is_array($model) && is_string($model['id'] ?? null)) {
                    return $model['id'];
                }

                return null;
            })
            ->filter(fn (?string $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();

        return ['models' => $models, 'error' => null];
    }

    private function firstProbeKey(AiProvider $aiProvider): ?string
    {
        foreach ($this->keyRotation->credentialsFor($aiProvider) as $credential) {
            if ($credential['value'] !== '') {
                return $credential['value'];
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function probeHeaders(AiProvider $aiProvider, string $apiKey): array
    {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'idexal-os-admin/1.0',
        ];

        if ($aiProvider->driver === AiProvider::DRIVER_ANTHROPIC) {
            $headers['x-api-key'] = $apiKey;
            $headers['anthropic-version'] = '2023-06-01';
        } else {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        return $headers;
    }

    /**
     * An empty selection offers the provider on every plan.
     *
     * @return Collection<int, Plan>
     */
    private function plans(): Collection
    {
        return Plan::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * Record a sync event so connected desktops learn about the change
     * immediately through the /v1/inference-providers/inventory/events
     * stream, without waiting for their polling interval.
     */
    private function broadcastSyncEvent(string $event, mixed $model): void
    {
        DesktopSync::announce($event, [
            'provider_slug' => is_object($model) && method_exists($model, 'getAttribute') ? $model->getAttribute('slug') : null,
            'model_id' => is_object($model) && method_exists($model, 'getAttribute') ? $model->getAttribute('model_id') : null,
        ]);
    }

    /**
     * @return list<string>
     */
    private function validatedPlans(Request $request): array
    {
        $validated = $request->validate([
            'plans' => ['nullable', 'array'],
            'plans.*' => ['string', Rule::exists(Plan::class, 'id')],
        ]);

        return array_values(array_map('strval', $validated['plans'] ?? []));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, ?AiProvider $provider = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique(AiProvider::class)->ignore($provider?->getKey())],
            'driver' => ['required', Rule::in(array_keys(AiProvider::drivers()))],
            'base_url' => ['required', 'url', 'max:2048'],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'headers_json' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'feature_overrides' => ['nullable', 'array'],
            'feature_overrides.*' => ['nullable', Rule::in(['inherit', 'on', 'off'])],
        ]);

        $headers = $provider?->headers ?? [];

        if (isset($validated['headers_json']) && trim($validated['headers_json']) !== '') {
            $decoded = json_decode($validated['headers_json'], true);

            if (! is_array($decoded)) {
                throw ValidationException::withMessages([
                    'headers_json' => __('ui.admin.providers.headers_invalid'),
                ]);
            }

            $headers = $decoded;
        }

        $data = [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'driver' => $validated['driver'],
            'base_url' => rtrim($validated['base_url'], '/'),
            'headers' => $headers,
            'priority' => (int) ($validated['priority'] ?? 0),
            'notes' => $validated['notes'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'supports_streaming' => $request->boolean('supports_streaming'),
            'feature_overrides' => $this->featureOverrides($request),
        ];

        if (filled($validated['api_key'] ?? null)) {
            $data['api_key'] = $validated['api_key'];
        } elseif ($provider instanceof AiProvider && $request->boolean('clear_api_key')) {
            $data['api_key'] = null;
        }

        return $data;
    }

    /**
     * The platform-level state each feature inherits, so the form can say what
     * leaving a feature alone actually means instead of a bare "inherit".
     *
     * @return array<string, bool>
     */
    private function platformFeatureToggles(): array
    {
        return GatewayFeatureState::toggles($this->settings);
    }

    /**
     * Record a provider or model save, naming any feature pin it moved. The pin
     * is the per-provider answer that outranks the platform toggle, so without
     * this the audit trail would say "saved" and lose which capability moved.
     *
     * @param  array<string, bool>|null  $before
     * @param  array<string, bool>|null  $after
     */
    private function recordFeaturePinChanges(string $action, Model $subject, ?string $name, ?array $before, ?array $after): void
    {
        $changes = GatewayFeatureState::overrideChanges($before, $after);

        AuditLog::record($action, null, $subject, $name, $changes === [] ? [] : ['features' => $changes]);
    }

    /**
     * The per-feature override a provider stores: "on"/"off" pin the feature,
     * anything else (or missing) inherits the platform setting.
     *
     * @return array<string, bool>
     */
    private function featureOverrides(Request $request): array
    {
        $submitted = (array) $request->input('feature_overrides', []);
        $overrides = [];

        foreach (GatewayFeature::keys() as $feature) {
            $value = $submitted[$feature] ?? 'inherit';

            if ($value === 'on') {
                $overrides[$feature] = true;
            } elseif ($value === 'off') {
                $overrides[$feature] = false;
            }
        }

        return $overrides;
    }

    /**
     * @return array<string, mixed>
     */
    private function refreshCombos(): void
    {
        app(ComboModelService::class)->syncAutomaticCombos();
    }

    private function modelPayload(Request $request, AiProvider $provider, ?AiModel $model = null): array
    {
        $validated = $request->validate([
            'model_id' => [
                'required',
                'string',
                'max:190',
                Rule::unique(AiModel::class)->where('ai_provider_id', $provider->getKey())->ignore($model?->getKey()),
            ],
            'display_name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:1000'],
            'context_window' => ['nullable', 'integer', 'min:1'],
            'max_output_tokens' => ['nullable', 'integer', 'min:1'],
            'input_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'output_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'reasoning_options' => ['nullable', 'array'],
            'reasoning_options.*' => ['string', Rule::in(self::REASONING_OPTIONS)],
            'feature_overrides' => ['nullable', 'array'],
            'feature_overrides.*' => ['nullable', Rule::in(['inherit', 'on', 'off'])],
        ]);

        $capabilities = [];
        foreach (self::CAPABILITIES as $capability) {
            $capabilities[$capability] = $request->boolean('capabilities.'.$capability);
        }

        $reasoningOptions = collect($request->input('reasoning_options', []))
            ->filter(fn (mixed $value): bool => is_string($value))
            ->unique()
            ->values()
            ->all();

        return [
            'model_id' => $validated['model_id'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'context_window' => $validated['context_window'] ?? null,
            'max_output_tokens' => $validated['max_output_tokens'] ?? null,
            'input_price' => $validated['input_price'],
            'output_price' => $validated['output_price'],
            'capabilities' => $capabilities,
            'reasoning_options' => $reasoningOptions !== [] ? $reasoningOptions : null,
            'feature_overrides' => $this->featureOverrides($request),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }
}
