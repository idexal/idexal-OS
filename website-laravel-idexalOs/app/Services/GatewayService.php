<?php

namespace App\Services;

use App\Exceptions\GatewayException;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ApiKey;
use App\Models\Organization;
use App\Models\Plan;
use App\Services\Gateway\PiiRedactor;
use App\Services\Gateway\RepetitionGuard;
use App\Support\OutboundHttp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class GatewayService
{
    private const OPENAI_PAYLOAD_KEYS = [
        'messages',
        'temperature',
        'top_p',
        'n',
        'max_tokens',
        'max_completion_tokens',
        'stop',
        'presence_penalty',
        'frequency_penalty',
        'logit_bias',
        'user',
        'tools',
        'tool_choice',
        'parallel_tool_calls',
        'response_format',
        'seed',
        'reasoning_effort',
        'service_tier',
        'metadata',
        'modalities',
        'audio',
        'prediction',
        'web_search_options',
    ];

    private const ANTHROPIC_PAYLOAD_KEYS = [
        'messages',
        'system',
        'max_tokens',
        'temperature',
        'top_p',
        'top_k',
        'stop_sequences',
        'stream',
        'tools',
        'tool_choice',
        'thinking',
        'metadata',
    ];

    /** Statuses another provider can plausibly survive. */
    private const RETRYABLE_UPSTREAM_STATUSES = [401, 403, 404, 408, 409, 425, 429, 500, 502, 503, 504];

    /**
     * Annotation-only JSON Schema keywords. They describe a node but never
     * change its shape, so removing them is lossless.
     */
    private const JSON_SCHEMA_ANNOTATIONS = ['title', 'description', 'examples', 'default'];

    /**
     * Service tags some open models leak at the very end of an answer.
     */
    private const LEAKED_SERVICE_TAGS = [
        '</tool_call>', '<tool_call>',
        '</tool_response>', '<tool_response>',
        '</function_call>', '<function_call>',
        '</function_calls>', '<function_calls>',
        '<|im_end|>', '<|im_start|>', '<|endoftext|>', '<|eot_id|>', '<|end_of_text|>',
        '</s>', '</answer>', '</output>', '</result>',
    ];

    /** Guards the vision description sub-call against describing itself. */
    private static bool $describingImages = false;

    /**
     * Prepared-input flags, in audit-metadata order, mapped to the operator
     * toggle they came from.
     */
    private const FEATURE_FLAGS = [
        'web_search' => 'web_search',
        'param_sanitizer' => 'param_sanitizer',
        'pii_sanitizer' => 'pii_sanitizer',
        'patient_retry' => 'patient_retry',
        'json_repair' => 'json_schema',
        'repetition' => 'repetition_guard',
    ];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly UsageService $usage,
        private readonly ComboModelService $combos,
        private readonly ProviderKeyRotationService $keys,
        private readonly ProviderFallbackService $fallback,
        private readonly PiiRedactor $pii,
        private readonly ProviderCooldownService $cooldown,
    ) {}

    /**
     * The drivers the desktop transport can serve.
     *
     * Anthropic is included only while the compatibility setting is on: its
     * models are reached by translating the OpenAI-shaped request into a
     * Messages request, so with the setting off an Anthropic provider's models
     * stay out of the desktop catalog exactly as they did before.
     *
     * @return list<string>
     */
    private function desktopDrivers(): array
    {
        $drivers = [AiProvider::DRIVER_OPENAI, AiProvider::DRIVER_OPENAI_COMPATIBLE];

        if ($this->settings->boolean('gateway_anthropic_desktop', false)) {
            $drivers[] = AiProvider::DRIVER_ANTHROPIC;
        }

        return $drivers;
    }

    /**
     * Whether a request built for the OpenAI chat endpoint must be rewritten as
     * an Anthropic Messages request on the way out (and its answer rewritten
     * back on the way in).
     */
    private function translatesAnthropic(AiProvider $provider, string $endpoint): bool
    {
        return $endpoint === 'chat/completions'
            && $provider->driver === AiProvider::DRIVER_ANTHROPIC
            && $this->settings->boolean('gateway_anthropic_desktop', false);
    }

    /**
     * @return array{object: string, data: list<array<string, mixed>>}
     */
    public function catalog(Organization $organization): array
    {
        // The raw API list is deliberately wider than the desktop one: it keeps
        // Anthropic-only upstreams and keyless providers, because an API client
        // may be calling /v1/messages. Combo handling is the same either way.
        $models = $this->desktopVisibility($organization, null, false)['models'];

        return [
            'object' => 'list',
            'data' => $models
                ->values()
                ->map(fn (AiModel $model): array => [
                    'id' => $model->model_id,
                    'object' => 'model',
                    'created' => $model->created_at?->timestamp ?? now()->timestamp,
                    'owned_by' => $model->provider->slug,
                    'display_name' => $model->display_name,
                    'context_window' => $model->context_window,
                ])
                ->all(),
        ];
    }

    /**
     * Models a managed desktop provider may expose through the gateway.
     * The desktop transport is OpenAI-compatible (/chat/completions), so
     * Anthropic-only upstreams stay out. One row per model id, highest
     * priority first; the gateway still keeps every provider for failover.
     *
     * @return Collection<int, AiModel>
     */
    public function desktopGatewayModels(Organization $organization): Collection
    {
        return $this->desktopVisibility(
            $organization,
            $this->desktopDrivers(),
            true,
        )['models']
            ->sortByDesc(fn (AiModel $model): int => (int) $model->provider->priority)
            ->unique(fn (AiModel $model): string => (string) $model->model_id)
            ->values();
    }

    /**
     * One authority for what an organization may see.
     *
     * The models it is entitled to are read straight from the gates (active
     * model, active provider, driver, workspace, provider plan, model plan,
     * credential). A combo is then offered only where at least one of its
     * members reached that set, and only the members of the combos actually
     * offered are hidden.
     *
     * A combo never carries entitlement of its own: its stored plan rows are a
     * copy of its members' assignments from the moment it was built, and a
     * stale copy must not hide a member an organization is entitled to. That is
     * how a workspace ended up with an empty catalog — the combo was filtered
     * out on a stale plan while its members were already hidden by it.
     *
     * @param  list<string>|null  $drivers  desktop transport drivers, or null for every driver
     * @return array{models: Collection<int, AiModel>, combos: Collection<int, AiModel>, hidden: list<string>}
     */
    private function desktopVisibility(Organization $organization, ?array $drivers, bool $requiresCredential): array
    {
        $plan = $organization->currentPlan();

        $base = function () use ($organization, $plan, $drivers): Builder {
            $query = AiModel::query()
                ->with('provider')
                ->where('is_active', true)
                ->whereHas('provider', function (Builder $query) use ($organization, $plan, $drivers): void {
                    $query->where('is_active', true)
                        ->where(fn (Builder $inner) => $inner
                            ->whereNull('organization_id')
                            ->orWhere('organization_id', $organization->getKey()));

                    $this->limitProvidersToPlan($query, $plan);

                    if ($drivers !== null) {
                        $query->whereIn('driver', $drivers);
                    }
                });

            return $query->orderBy('sort_order');
        };

        $entitled = $base()
            ->whereNull('hybrid_config')
            ->where(fn (Builder $query) => $this->limitModelsToPlan($query, $plan))
            ->get()
            ->filter(fn (AiModel $model): bool => $model->provider instanceof AiProvider
                && (! $requiresCredential || $this->providerHasCredential($model->provider)));

        $entitledIds = $entitled->map(fn (AiModel $model): string => (string) $model->getKey())->all();

        $combos = $entitledIds === [] ? collect() : $base()
            ->whereNotNull('hybrid_config')
            ->get()
            ->filter(function (AiModel $model) use ($entitledIds): bool {
                if (! $this->combos->isCombo($model)) {
                    return false;
                }

                return array_intersect($this->combos->memberIds($model), $entitledIds) !== [];
            })
            ->values();

        $hidden = $this->combos->hiddenMemberIds($combos);

        return [
            'models' => $combos->isEmpty()
                ? $entitled
                : $entitled->reject(fn (AiModel $model): bool => in_array((string) $model->getKey(), $hidden, true))->merge($combos),
            'combos' => $combos,
            'hidden' => $hidden,
        ];
    }

    /**
     * Whether a desktop may attach an image to this model: natively for a
     * multimodal model, or because Vision will describe the image first for a
     * text-only one. The desktop shows its attach control on this signal, so the
     * Vision feature is reachable from the app instead of hidden behind a model
     * that cannot see.
     */
    public function acceptsImages(AiModel $model): bool
    {
        if ($model->supports('vision') || $model->supports('image_input')) {
            return true;
        }

        return $this->featureEnabled('vision', $model->provider, $model);
    }

    /**
     * Why each model does or does not reach a managed desktop. The catalog query
     * stays the single authority on visibility; this only names the gate that
     * dropped a row, so nothing disappears without a reason an operator can read.
     *
     * @return list<array{model_id: string, display_name: string, provider: string, driver: string, priority: int, in_catalog: bool, reason: string|null}>
     */
    public function diagnoseDesktopCatalog(Organization $organization): array
    {
        $plan = $organization->currentPlan();
        $visibility = $this->desktopVisibility(
            $organization,
            $this->desktopDrivers(),
            true,
        );
        // The desktop list keeps one row per model id, so the diagnostic mirrors
        // that last dedup step rather than the wider entitlement set: two
        // providers offering one id report the loser as a duplicate.
        $visible = $this->desktopGatewayModels($organization)
            ->mapWithKeys(fn (AiModel $model): array => [(string) $model->getKey() => true]);
        $hiddenMembers = $visibility['hidden'];
        $offeredCombos = $visibility['combos']
            ->mapWithKeys(fn (AiModel $model): array => [(string) $model->getKey() => true])
            ->all();

        return AiModel::query()
            ->with(['provider.plans', 'plans'])
            ->orderBy('sort_order')
            ->get()
            ->map(function (AiModel $model) use ($organization, $plan, $hiddenMembers, $offeredCombos, $visible): array {
                $provider = $model->provider;
                $modelKey = (string) $model->getKey();
                $inCatalog = $visible->get($modelKey) === true;

                return [
                    'model_id' => (string) $model->model_id,
                    'display_name' => (string) ($model->display_name ?: $model->model_id),
                    'provider' => $provider instanceof AiProvider ? (string) $provider->slug : '—',
                    'driver' => $provider instanceof AiProvider ? (string) $provider->driver : '—',
                    'priority' => $provider instanceof AiProvider ? (int) $provider->priority : 0,
                    'in_catalog' => $inCatalog,
                    'reason' => $inCatalog ? null : $this->desktopExclusionReason($model, $organization, $plan, $hiddenMembers, $offeredCombos),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * First gate that drops the model, in the order the catalog applies them.
     *
     * @param  list<string>  $hiddenMembers
     * @param  array<string, bool>  $offeredCombos
     */
    private function desktopExclusionReason(AiModel $model, Organization $organization, ?Plan $plan, array $hiddenMembers, array $offeredCombos): string
    {
        $provider = $model->provider;

        if (! $model->is_active) {
            return 'model_inactive';
        }

        if (! $provider instanceof AiProvider) {
            return 'provider_missing';
        }

        if (! $provider->is_active) {
            return 'provider_inactive';
        }

        if (! in_array($provider->driver, $this->desktopDrivers(), true)) {
            return 'driver_not_desktop';
        }

        if ($provider->organization_id !== null && $provider->organization_id !== $organization->getKey()) {
            return 'provider_other_organization';
        }

        // The combo connection is synthetic and never plan-gated, so its own
        // provider rows can never be the reason a combo is missing.
        if ($provider->slug !== ComboModelService::PROVIDER_SLUG && $this->excludedByPlan($provider->plans, $plan)) {
            return 'provider_plan_mismatch';
        }

        $isCombo = $this->combos->isCombo($model);

        // A combo never carries plan rows of its own: it is offered through its
        // members, so the members decide and are the only plan gate that applies.
        if (! $isCombo && $this->excludedByPlan($model->plans, $plan)) {
            return 'model_plan_mismatch';
        }

        if ($isCombo) {
            return array_key_exists((string) $model->getKey(), $offeredCombos)
                ? 'duplicate_model_id'
                : 'combo_members_unavailable';
        }

        if (in_array((string) $model->getKey(), $hiddenMembers, true)) {
            return 'combo_member_hidden';
        }

        if (! $this->providerHasCredential($provider)) {
            return 'provider_without_credential';
        }

        // Every gate passed, yet the catalog row is gone: the duplicate model id
        // kept a higher-priority provider instead of this one.
        return 'duplicate_model_id';
    }

    /**
     * A model or provider assigned to plans is hidden from every other plan, and
     * from organizations whose plan cannot be resolved at all.
     *
     * @param  Collection<int, Plan>|null  $plans
     */
    private function excludedByPlan(?Collection $plans, ?Plan $plan): bool
    {
        if ($plans === null || $plans->isEmpty()) {
            return false;
        }

        if ($plan === null) {
            return true;
        }

        return ! $plans->contains(fn (Plan $candidate): bool => $candidate->getKey() === $plan->getKey());
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{status: int, body: array<string, mixed>, request_id: string, headers: array<string, string>}
     */
    public function complete(
        Organization $organization,
        ApiKey $apiKey,
        array $input,
        string $endpoint,
        Request $request,
    ): array {
        // Provider calls legitimately run longer than PHP's default 30s. The
        // HTTP client's own timeout still bounds each attempt; without this,
        // a slow model dies as a 500 before the gateway can pick a backup.
        @set_time_limit(0);
        $resolution = $this->prepareCandidates($organization, $input, $endpoint);
        $contexts = $resolution['contexts'];
        $substituted = $resolution['substituted'];
        // The request-side pipeline is shaped by the primary provider and model:
        // they are the ones the answer is expected to come from.
        $primaryProvider = $contexts[0]['provider'];
        $primaryModel = $contexts[0]['model'];
        [$input, $visionFired] = $this->applyVision($input, $primaryModel, $organization, $endpoint, $primaryProvider);
        $prepared = $this->prepareInput($input, $endpoint, $primaryProvider, $primaryModel);
        $attempts = count($contexts);
        $lastResult = null;

        foreach ($contexts as $index => $context) {
            $credentials = $this->keys->credentialsFor($context['provider']);
            $credentialAttempts = count($credentials);

            foreach ($credentials as $credentialIndex => $credential) {
                $payload = $this->upstreamPayload($context['model'], $prepared['input'], $endpoint, false, $prepared['web_search']);
                $startedAt = hrtime(true);

                try {
                    $response = $this->withPatientRetry(fn (): Response => $this->httpClient()
                        ->withHeaders($this->headers($context['provider'], false, $credential['value']))
                        ->connectTimeout(10)
                        ->timeout($this->settings->integer('gateway_timeout', (int) config('idexal.gateway.timeout', 180)))
                        ->post($context['url'], $payload), $context['provider'], $context['model']);
                } catch (ConnectionException $exception) {
                    $latency = (int) ((hrtime(true) - $startedAt) / 1_000_000);

                    $this->recordFailure(
                        $organization,
                        $apiKey,
                        $context,
                        $endpoint,
                        (string) $payload['model'],
                        502,
                        $exception->getMessage(),
                        $latency,
                        false,
                        $request->ip(),
                    );

                    // A transport failure also counts toward the cooldown, so a
                    // dead host stops being the first attempt of every request.
                    $this->cooldown->recordFailure($context['provider'], $context['model']);

                    if ($credentialIndex < $credentialAttempts - 1) {
                        continue;
                    }

                    if ($index < $attempts - 1) {
                        continue 2;
                    }

                    throw new GatewayException(
                        'upstream_connection_error',
                        __('ui.errors.gateway_connection'),
                        502,
                        'upstream_connection_error',
                    );
                }

                $latency = (int) ((hrtime(true) - $startedAt) / 1_000_000);
                $body = $response->json();

                if (! is_array($body)) {
                    $body = [
                        'error' => [
                            'message' => trim($response->body()) ?: __('ui.errors.gateway_invalid_response'),
                            'type' => 'upstream_error',
                        ],
                    ];
                }

                // An Anthropic answer is rewritten into the OpenAI shape the
                // client asked for before the response pipeline reads it.
                if ($response->successful() && $this->translatesAnthropic($context['provider'], $endpoint)) {
                    $body = AnthropicCompatibility::toChatCompletion(
                        $body,
                        trim((string) ($input['model'] ?? '')) ?: (string) ($body['model'] ?? ''),
                    );
                }

                $body = $this->transformResponse($body, $prepared, $endpoint);

                $requestedModel = trim((string) ($input['model'] ?? ''));
                if ($requestedModel !== '' && $response->successful()) {
                    $body['model'] = $requestedModel;
                }

                $usage = $this->usageFromPayload($endpoint, $body);
                $usage['cost'] = $context['model']->costFor($usage['prompt_tokens'], $usage['completion_tokens']);
                $error = $response->failed() ? $this->errorMessage($body) : null;
                $meta = $this->featureMeta($prepared, $substituted, $visionFired);

                // Web Search: a successful grounded answer carries the operator's
                // flat per-request search fee on top of the token cost.
                if ($response->successful() && isset($meta['web_search_price'])) {
                    $usage['cost'] += $meta['web_search_price'];
                }

                $record = $this->usage->record(
                    $organization,
                    $apiKey,
                    $context['provider'],
                    $context['model'],
                    $endpoint,
                    (string) $payload['model'],
                    $response->status(),
                    $usage,
                    $latency,
                    false,
                    $context['admission']['overage'],
                    $error,
                    $request->ip(),
                    $meta,
                );

                $lastResult = [
                    'status' => $response->status(),
                    'body' => $body,
                    'request_id' => $record->request_id,
                    'headers' => $this->responseHeaders($prepared),
                ];

                if ($response->successful()) {
                    $this->keys->markUsed($credential['id']);
                    $this->cooldown->recordSuccess($context['provider'], $context['model']);

                    return $lastResult;
                }

                if ($this->isKeyProblemStatus($response->status())) {
                    $this->keys->markExhausted($credential['id']);

                    if ($credentialIndex < $credentialAttempts - 1) {
                        continue;
                    }
                }

                $retryable = $this->isRetryableUpstreamStatus($response->status());

                if ($retryable) {
                    // Counted even on the last candidate so the admin sees the
                    // truth and the next request can reorder if a peer exists.
                    $this->cooldown->recordFailure($context['provider'], $context['model']);
                }

                if ($retryable && $index < $attempts - 1) {
                    continue 2;
                }

                return $lastResult;
            }
        }

        if ($lastResult !== null) {
            return $lastResult;
        }

        throw new GatewayException(
            'upstream_connection_error',
            __('ui.errors.gateway_connection'),
            502,
            'upstream_connection_error',
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function stream(
        Organization $organization,
        ApiKey $apiKey,
        array $input,
        string $endpoint,
        Request $request,
    ): StreamedResponse {
        @set_time_limit(0);
        $resolution = $this->prepareCandidates($organization, $input, $endpoint);
        $contexts = $resolution['contexts'];
        $substituted = $resolution['substituted'];
        // The request-side pipeline is shaped by the primary provider and model:
        // they are the ones the answer is expected to come from.
        $primaryProvider = $contexts[0]['provider'];
        $primaryModel = $contexts[0]['model'];
        [$input, $visionFired] = $this->applyVision($input, $primaryModel, $organization, $endpoint, $primaryProvider);
        $prepared = $this->prepareInput($input, $endpoint, $primaryProvider, $primaryModel);
        $translateAnthropic = $primaryProvider instanceof AiProvider
            && $this->translatesAnthropic($primaryProvider, $endpoint);
        $attempts = count($contexts);
        $selected = null;

        foreach ($contexts as $index => $context) {
            $credentials = $this->keys->credentialsFor($context['provider']);
            $credentialAttempts = count($credentials);

            foreach ($credentials as $credentialIndex => $credential) {
                $payload = $this->upstreamPayload($context['model'], $prepared['input'], $endpoint, true, $prepared['web_search']);
                $startedAt = hrtime(true);

                try {
                    $response = $this->withPatientRetry(fn (): Response => $this->httpClient()
                        ->withHeaders($this->headers($context['provider'], true, $credential['value']))
                        ->withOptions(['stream' => true])
                        ->connectTimeout(10)
                        ->timeout($this->settings->integer('gateway_stream_timeout', (int) config('idexal.gateway.stream_timeout', 600)))
                        ->post($context['url'], $payload), $context['provider'], $context['model']);
                } catch (ConnectionException $exception) {
                    $this->recordFailure(
                        $organization,
                        $apiKey,
                        $context,
                        $endpoint,
                        (string) $payload['model'],
                        502,
                        $exception->getMessage(),
                        (int) ((hrtime(true) - $startedAt) / 1_000_000),
                        true,
                        $request->ip(),
                    );

                    $this->cooldown->recordFailure($context['provider'], $context['model']);

                    if ($credentialIndex < $credentialAttempts - 1) {
                        continue;
                    }

                    if ($index < $attempts - 1) {
                        continue 2;
                    }

                    throw new GatewayException(
                        'upstream_connection_error',
                        __('ui.errors.gateway_connection'),
                        502,
                        'upstream_connection_error',
                    );
                }

                if ($response->failed()) {
                    $body = $response->json();
                    $body = is_array($body) ? $body : ['error' => ['message' => $response->body(), 'type' => 'upstream_error']];

                    $this->recordFailure(
                        $organization,
                        $apiKey,
                        $context,
                        $endpoint,
                        (string) $payload['model'],
                        $response->status(),
                        $this->errorMessage($body),
                        (int) ((hrtime(true) - $startedAt) / 1_000_000),
                        true,
                        $request->ip(),
                    );

                    if ($this->isKeyProblemStatus($response->status())) {
                        $this->keys->markExhausted($credential['id']);

                        if ($credentialIndex < $credentialAttempts - 1) {
                            continue;
                        }
                    }

                    $retryable = $this->isRetryableUpstreamStatus($response->status());

                    if ($retryable) {
                        $this->cooldown->recordFailure($context['provider'], $context['model']);
                    }

                    if ($retryable && $index < $attempts - 1) {
                        continue 2;
                    }

                    throw new GatewayException(
                        (string) data_get($body, 'error.type', 'upstream_error'),
                        (string) data_get($body, 'error.message', __('ui.errors.gateway_upstream')),
                        $response->status(),
                        (string) data_get($body, 'error.code', 'upstream_error'),
                    );
                }

                $this->keys->markUsed($credential['id']);
                $this->cooldown->recordSuccess($context['provider'], $context['model']);

                $selected = [
                    'response' => $response,
                    'context' => $context,
                    'payload' => $payload,
                    'startedAt' => $startedAt,
                ];

                break 2;
            }
        }

        if ($selected === null) {
            throw new GatewayException(
                'upstream_connection_error',
                __('ui.errors.gateway_connection'),
                502,
                'upstream_connection_error',
            );
        }

        $response = $selected['response'];
        $context = $selected['context'];
        $payload = $selected['payload'];
        $startedAt = $selected['startedAt'];
        $bodyStream = $response->toPsrResponse()->getBody();
        $contentType = (string) ($response->header('Content-Type') ?: 'text/event-stream');
        $model = $context['model'];
        $provider = $context['provider'];
        $modelName = (string) $payload['model'];
        $overage = $context['admission']['overage'];
        $chargeCredits = $overage;
        $ip = $request->ip();
        $service = $this;
        // The client asked for this id (a gateway gwm_ id for managed
        // providers). Upstream aliases must not leak into chunks: clients
        // key streamed content by the model they requested.
        $requestedModel = trim((string) ($input['model'] ?? ''));
        if ($requestedModel === '') {
            $requestedModel = $modelName;
        }

        return response()->stream(function () use (
            $bodyStream,
            $model,
            $provider,
            $modelName,
            $requestedModel,
            $endpoint,
            $organization,
            $apiKey,
            $startedAt,
            $chargeCredits,
            $ip,
            $service,
            $prepared,
            $substituted,
            $visionFired,
            $translateAnthropic,
        ): void {
            ignore_user_abort(true);

            $buffer = '';
            $prompt = 0;
            $completion = 0;
            $error = null;
            $guard = new RepetitionGuard;

            try {
                while (! $bodyStream->eof()) {
                    if (connection_aborted()) {
                        break;
                    }

                    $chunk = $bodyStream->read(8192);

                    if ($chunk === '') {
                        usleep(1000);

                        continue;
                    }

                    $buffer .= $chunk;

                    while (($position = strpos($buffer, "\n")) !== false) {
                        $rawLine = substr($buffer, 0, $position);
                        $buffer = substr($buffer, $position + 1);
                        $trimmed = trim($rawLine);

                        if (str_starts_with($trimmed, 'data:')) {
                            $payload = trim(substr($trimmed, 5));

                            if ($translateAnthropic) {
                                // The upstream is speaking Anthropic's typed
                                // events; rewrite each one into the OpenAI
                                // delta the client expects.
                                $event = $payload !== '' ? json_decode($payload, true) : null;

                                if (is_array($event)) {
                                    $delta = AnthropicCompatibility::streamDelta($event, $requestedModel, $prompt, $completion);

                                    if ($delta !== null) {
                                        echo 'data: '.json_encode($delta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n";
                                    }

                                    if (($event['type'] ?? null) === 'message_stop') {
                                        echo 'data: [DONE]'."\n\n";
                                        $buffer = '';

                                        break 2;
                                    }
                                }
                            } else {
                                $decoded = $payload !== '' && $payload !== '[DONE]'
                                    ? json_decode($payload, true)
                                    : null;

                                if (is_array($decoded)) {
                                    $decoded = $this->normalizeStreamChunk($decoded);
                                    $transformed = $this->transformStreamChunk($decoded, $prepared, $guard);
                                    $decoded = $transformed['chunk'];
                                    $decoded['model'] = $requestedModel;
                                    $rewritten = 'data: '.json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                                    echo $rewritten."\n";
                                    [$prompt, $completion] = $this->readStreamUsage($rewritten, $prompt, $completion);

                                    if ($transformed['stop']) {
                                        echo 'data: [DONE]'."\n\n";
                                        $buffer = '';

                                        break 2;
                                    }
                                } else {
                                    echo $rawLine."\n";
                                    [$prompt, $completion] = $this->readStreamUsage($rawLine, $prompt, $completion);
                                }
                            }
                        } else {
                            // Anthropic's `event:` lines carry no body; the
                            // translated `data:` line that follows already says
                            // everything the client needs.
                            if (! $translateAnthropic) {
                                echo $rawLine."\n";
                            }
                        }

                        if (ob_get_level() > 0) {
                            @ob_flush();
                        }

                        flush();
                    }
                }
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }

            if (trim($buffer) !== '') {
                $trimmed = trim($buffer);
                $payload = str_starts_with($trimmed, 'data:') ? trim(substr($trimmed, 5)) : '';
                $decoded = $payload !== '' && $payload !== '[DONE]' ? json_decode($payload, true) : null;

                if (is_array($decoded)) {
                    $decoded = $this->normalizeStreamChunk($decoded);
                    $decoded['model'] = $requestedModel;
                    $rewritten = 'data: '.json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    echo $rewritten."\n";
                    [$prompt, $completion] = $this->readStreamUsage($rewritten, $prompt, $completion);
                } else {
                    echo $buffer;
                    [$prompt, $completion] = $this->readStreamUsage($buffer, $prompt, $completion);
                }
            }

            $latency = (int) ((hrtime(true) - $startedAt) / 1_000_000);
            $total = $prompt + $completion;

            $service->recordStreamUsage(
                $organization,
                $apiKey,
                $provider,
                $model,
                $endpoint,
                $modelName,
                $prompt,
                $completion,
                $total,
                $latency,
                $chargeCredits,
                $error,
                $ip,
                $service->featureMeta($prepared, $substituted, $visionFired),
            );
        }, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
            ...$this->responseHeaders($prepared),
        ]);
    }

    /**
     * Called from the streamed response after the client has received every chunk.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function recordStreamUsage(
        Organization $organization,
        ApiKey $apiKey,
        AiProvider $provider,
        AiModel $model,
        string $endpoint,
        string $modelName,
        int $prompt,
        int $completion,
        int $total,
        int $latency,
        bool $chargeCredits,
        ?string $error,
        ?string $ip,
        ?array $meta = null,
    ): void {
        $cost = $model->costFor($prompt, $completion);

        // Web Search: the flat search fee rides on top of the token cost.
        if (isset($meta['web_search_price'])) {
            $cost += (float) $meta['web_search_price'];
        }

        $this->usage->record(
            $organization,
            $apiKey,
            $provider,
            $model,
            $endpoint,
            $modelName,
            200,
            [
                'prompt_tokens' => $prompt,
                'completion_tokens' => $completion,
                'total_tokens' => $total,
                'cost' => $cost,
            ],
            $latency,
            true,
            $chargeCredits,
            $error,
            $ip,
            $meta,
        );
    }

    /**
     * Every compatible provider for this model, highest priority first. Each
     * context shares one admission decision so a fallback attempt cannot pass
     * the quota check twice.
     *
     * @param  array<string, mixed>  $input
     * @return array{contexts: list<array{model: AiModel, provider: AiProvider, url: string, admission: array{allowed: bool, overage: bool, reason: string|null}}>, substituted: bool}
     */
    private function prepareCandidates(Organization $organization, array $input, string $endpoint): array
    {
        $admission = $this->usage->admission($organization);

        if (! $admission['allowed']) {
            throw new GatewayException(
                'rate_limit_error',
                $admission['reason'] === 'gateway_disabled'
                    ? __('ui.errors.gateway_disabled')
                    : __('ui.errors.quota_exceeded'),
                429,
                $admission['reason'],
            );
        }

        $modelName = trim((string) ($input['model'] ?? ''));

        if ($modelName === '') {
            throw new GatewayException('invalid_request_error', __('ui.errors.model_required'), 422, 'model_required', 'model');
        }

        $models = $this->expandCombos($this->resolveModels($organization, $modelName, $endpoint), $organization, $input, $endpoint);
        $substituted = false;

        // Auto-substitute Model: an unknown or wholly offline model is answered
        // by the operator's default model instead of failing the request. There
        // is no provider to override here yet, so this reads the platform toggle.
        if ($models->isEmpty() && $this->featureEnabled('model_substitute')) {
            $substitute = trim((string) $this->settings->get('feature_default_model_id', ''));

            if ($substitute !== '' && $substitute !== $modelName) {
                $models = $this->expandCombos(
                    $this->resolveModels($organization, $substitute, $endpoint),
                    $organization,
                    $input,
                    $endpoint,
                );
                $substituted = $models->isNotEmpty();
            }
        }

        $contexts = [];
        foreach ($models as $model) {
            $provider = $model->provider;
            if (! $provider instanceof AiProvider) {
                continue;
            }

            $url = $this->upstreamUrl($provider, $endpoint);
            if ($url === null) {
                continue;
            }

            $contexts[] = [
                'model' => $model,
                'provider' => $provider,
                'url' => $url,
                'admission' => $admission,
            ];
        }

        if ($contexts === []) {
            if ($models->isEmpty()) {
                throw new GatewayException('invalid_request_error', __('ui.errors.model_not_found'), 404, 'model_not_found', 'model');
            }

            throw new GatewayException('server_error', __('ui.errors.provider_not_configured'), 503, 'provider_not_configured');
        }

        return ['contexts' => $this->fallback->order($contexts), 'substituted' => $substituted];
    }

    /**
     * @param  Collection<int, AiModel>  $models
     * @param  array<string, mixed>  $input
     * @return Collection<int, AiModel>
     */
    private function expandCombos(Collection $models, Organization $organization, array $input, string $endpoint): Collection
    {
        $expanded = collect();

        foreach ($models as $model) {
            if ($model instanceof AiModel && $this->combos->isCombo($model)) {
                $expanded = $expanded->merge($this->combos->expand($model, $organization, $input, $endpoint));

                continue;
            }

            $expanded->push($model);
        }

        return $expanded
            ->filter(fn (mixed $model): bool => $model instanceof AiModel)
            ->unique(fn (AiModel $model): string => $model->getKey())
            ->values();
    }

    /**
     * @return Collection<int, AiModel>
     */
    private function resolveModels(Organization $organization, string $modelName, string $endpoint): Collection
    {
        $drivers = $endpoint === 'messages'
            ? [AiProvider::DRIVER_ANTHROPIC]
            : $this->desktopDrivers();
        $plan = $organization->currentPlan();

        $baseQuery = AiModel::query()
            ->with('provider')
            ->where('is_active', true)
            ->whereHas('provider', function (Builder $query) use ($organization, $plan): void {
                $query->where('is_active', true)
                    ->where(fn (Builder $inner) => $inner
                        ->whereNull('organization_id')
                        ->orWhere('organization_id', $organization->getKey()));

                $this->limitProvidersToPlan($query, $plan);
            });

        // A combo is resolved from its members (expand() below applies the same
        // gates per member), so its own stale plan rows never block a call.
        $baseQuery->where(function (Builder $query) use ($plan): void {
            $query->whereNotNull('hybrid_config')
                ->orWhere(fn (Builder $inner) => $this->limitModelsToPlan($inner, $plan));
        });

        if (str_starts_with($modelName, 'gwm_')) {
            // gwm_<organization>_<credential-set>_<model-ulid> from the
            // managed desktop provider. The organization and credential
            // segments must match the authenticated organization, so a
            // forged id cannot address another workspace's model.
            $segments = explode('_', $modelName);
            $organizationId = $segments[1] ?? '';
            $credentialSetId = $segments[2] ?? '';
            $modelKey = $segments[3] ?? '';

            if ($organizationId !== $organization->getKey()
                || $credentialSetId !== $organization->getKey()
                || $modelKey === '') {
                return collect();
            }

            return $baseQuery
                ->whereKey($modelKey)
                ->get()
                ->filter(fn (AiModel $model): bool => $model->provider instanceof AiProvider
                    && in_array($model->provider->driver, $drivers, true))
                ->sortByDesc(fn (AiModel $model): int => (int) $model->provider->priority)
                ->unique(fn (AiModel $model): string => (string) $model->ai_provider_id)
                ->values();
        }

        $candidates = $baseQuery->where('model_id', $modelName)->get();

        if ($candidates->isEmpty() && str_contains($modelName, '/')) {
            [$providerSlug, $bareModel] = explode('/', $modelName, 2);

            $candidates = $baseQuery
                ->where('model_id', $bareModel)
                ->whereHas('provider', fn ($query) => $query->where('slug', $providerSlug))
                ->get();
        }

        return $candidates
            ->filter(fn (AiModel $model): bool => $model->provider instanceof AiProvider
                && in_array($model->provider->driver, $drivers, true))
            ->sortByDesc(fn (AiModel $model): int => (int) $model->provider->priority)
            ->unique(fn (AiModel $model): string => (string) $model->ai_provider_id)
            ->values();
    }

    /**
     * A model assigned to specific plans only serves those plans. Models
     * with no assignment follow their provider.
     *
     * @param  Builder<AiModel>  $query
     */
    private function limitModelsToPlan(Builder $query, ?Plan $plan): void
    {
        $unassignedServesAll = $this->unassignedServesEveryPlan();

        $query->where(function (Builder $query) use ($plan, $unassignedServesAll): void {
            if ($unassignedServesAll) {
                $query->whereDoesntHave('plans');
            }

            if ($plan === null) {
                // Planless (signed out, or no subscription): only unassigned
                // rows are reachable, and only while the default stands. In
                // strict mode nothing is entitled without an assignment.
                if (! $unassignedServesAll) {
                    $query->whereRaw('1 = 0');
                }

                return;
            }

            $assigned = fn ($plans) => $plans->whereKey($plan->getKey());

            if ($unassignedServesAll) {
                $query->orWhereHas('plans', $assigned);
            } else {
                $query->whereHas('plans', $assigned);
            }
        });
    }

    /**
     * Whether an operator lets a row with no plan assignment serve every plan.
     *
     * The default is yes, which is the behaviour every deployment had before a
     * plan could narrow a catalog: an operator who has never assigned anything
     * keeps serving everything. The provider console can turn it off, and then
     * an assignment is the only way a model or provider reaches a plan — the
     * strict, explicit control, with nothing left reachable by accident.
     */
    private function unassignedServesEveryPlan(): bool
    {
        return ! $this->settings->boolean('gateway_require_explicit_assignment', false);
    }

    /**
     * The same rule for a provider: one assigned to plans serves only those, and
     * one with no assignment serves everyone.
     *
     * The combo connection is synthetic: it carries no models an operator
     * assigns to a plan and is offered through its members' providers, so it is
     * never plan-gated. A stale `plan_ai_provider` row here once hid every combo
     * from every other plan — the model-level copy of the same failure.
     *
     * @param  Builder<AiProvider>  $query
     */
    private function limitProvidersToPlan(Builder $query, ?Plan $plan): void
    {
        $unassignedServesAll = $this->unassignedServesEveryPlan();

        $query->where(function (Builder $query) use ($plan, $unassignedServesAll): void {
            // The combo connection always passes: its models are combos, and
            // the model gate below decides which of them a plan may see.
            $query->where('slug', ComboModelService::PROVIDER_SLUG)
                ->orWhere(function (Builder $query) use ($plan, $unassignedServesAll): void {
                    if ($unassignedServesAll) {
                        $query->whereDoesntHave('plans');
                    }

                    if ($plan === null) {
                        if (! $unassignedServesAll) {
                            $query->whereRaw('1 = 0');
                        }

                        return;
                    }

                    $assigned = fn ($plans) => $plans->whereKey($plan->getKey());

                    if ($unassignedServesAll) {
                        $query->orWhereHas('plans', $assigned);
                    } else {
                        $query->whereHas('plans', $assigned);
                    }
                });
        });
    }

    private function isRetryableUpstreamStatus(int $status): bool
    {
        return in_array($status, self::RETRYABLE_UPSTREAM_STATUSES, true);
    }

    /**
     * Statuses that mean this particular key is the problem, so the gateway
     * should try the provider's next key before giving up on the provider.
     */
    private function isKeyProblemStatus(int $status): bool
    {
        return in_array($status, [401, 403, 429], true);
    }

    /**
     * @param  array{model: AiModel, provider: AiProvider, url: string, admission: array{allowed: bool, overage: bool, reason: string|null}}  $context
     */
    private function recordFailure(
        Organization $organization,
        ApiKey $apiKey,
        array $context,
        string $endpoint,
        string $modelName,
        int $status,
        ?string $error,
        int $latency,
        bool $stream,
        ?string $ip,
    ): void {
        $this->usage->record(
            $organization,
            $apiKey,
            $context['provider'],
            $context['model'],
            $endpoint,
            $modelName,
            $status,
            ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0, 'cost' => 0],
            $latency,
            $stream,
            $context['admission']['overage'],
            $error,
            $ip,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    /**
     * Some OpenAI-compatible upstreams send `delta: []` for an empty delta.
     * OpenAI clients (including the desktop engine) require an object, and
     * JSON encodes an empty PHP array as a list, so normalize it here.
     *
     * @param  array<string, mixed>  $chunk
     * @return array<string, mixed>
     */
    private function normalizeStreamChunk(array $chunk): array
    {
        if (is_array($chunk['choices'] ?? null)) {
            foreach ($chunk['choices'] as $index => $choice) {
                if (! is_array($choice)) {
                    continue;
                }

                $delta = $choice['delta'] ?? null;

                if (is_array($delta) && ($delta === [] || array_is_list($delta))) {
                    $chunk['choices'][$index]['delta'] = (object) $delta;
                }
            }
        }

        return $chunk;
    }

    /**
     * A managed provider must be able to answer. Keyless loopback endpoints
     * (Ollama and friends) are valid; a remote provider with no key and no
     * authentication header would only fail after the desktop offered it.
     */
    private function providerHasCredential(AiProvider $provider): bool
    {
        if (trim((string) $provider->api_key) !== '') {
            return true;
        }

        if ($provider->apiKeys()->usable()->exists()) {
            return true;
        }

        if (in_array($provider->headers, [null, []], true) === false) {
            return true;
        }

        $host = strtolower((string) parse_url((string) $provider->base_url, PHP_URL_HOST));

        return in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true);
    }

    private function httpClient(): PendingRequest
    {
        return OutboundHttp::client();
    }

    private function upstreamPayload(AiModel $model, array $input, string $endpoint, bool $stream, bool $webSearch = false): array
    {
        $provider = $model->provider;

        // The desktop speaks the OpenAI shape; an Anthropic upstream does not.
        // Rewrite the whole body rather than filtering it, so the fields
        // Anthropic requires (a system turn, `max_tokens`) are always present.
        if ($provider instanceof AiProvider && $this->translatesAnthropic($provider, $endpoint)) {
            return AnthropicCompatibility::toMessagesRequest(
                $input,
                (string) $model->model_id,
                $stream,
                (int) ($model->max_output_tokens ?: 0),
            );
        }

        $allowedKeys = $endpoint === 'messages' ? self::ANTHROPIC_PAYLOAD_KEYS : self::OPENAI_PAYLOAD_KEYS;
        $payload = [];

        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $input)) {
                $payload[$key] = $input[$key];
            }
        }

        $payload['model'] = $model->model_id;
        $payload['stream'] = $stream;

        // Web search is a gateway-owned flag: the client may ask for it in the
        // body, but only the operator's toggle decides whether it is sent.
        if ($webSearch && $endpoint !== 'messages') {
            $payload['web_search'] = true;
        }

        if ($endpoint === 'messages' && ! isset($payload['max_tokens'])) {
            $payload['max_tokens'] = min(4096, (int) ($model->max_output_tokens ?: 1024));
        }

        if ($stream && in_array($model->provider->driver, [AiProvider::DRIVER_OPENAI, AiProvider::DRIVER_OPENAI_COMPATIBLE], true)) {
            $payload['stream_options'] = array_merge(
                ['include_usage' => true],
                is_array($input['stream_options'] ?? null) ? $input['stream_options'] : [],
            );
        }

        if (! $stream) {
            unset($payload['stream_options']);
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private function headers(AiProvider $provider, bool $stream, ?string $apiKey = null): array
    {
        $headers = [
            'Accept' => $stream ? 'text/event-stream' : 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'idexal-os-gateway/1.0',
        ];

        foreach ($provider->headers ?? [] as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $headers[$name] = $value;
            }
        }

        $apiKey = $apiKey ?? (string) $provider->api_key;

        if ($provider->driver === AiProvider::DRIVER_ANTHROPIC) {
            if ($apiKey !== '') {
                $headers['x-api-key'] = $apiKey;
            }

            $headers['anthropic-version'] = $headers['anthropic-version'] ?? '2023-06-01';
        } elseif ($apiKey !== '') {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        return $headers;
    }

    private function upstreamUrl(AiProvider $provider, string $endpoint): ?string
    {
        $baseUrl = trim((string) $provider->base_url);

        if ($baseUrl === '') {
            return null;
        }

        // An Anthropic provider driven through the OpenAI chat endpoint is
        // served by its Messages path; the body is translated to match.
        $path = $endpoint === 'messages' || $this->translatesAnthropic($provider, $endpoint)
            ? '/messages'
            : '/chat/completions';

        return rtrim($baseUrl, '/').$path;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, cost: float}
     */
    private function usageFromPayload(string $endpoint, array $payload): array
    {
        $prompt = (int) data_get($payload, 'usage.prompt_tokens', 0);
        $completion = (int) data_get($payload, 'usage.completion_tokens', 0);

        if ($endpoint === 'messages') {
            $prompt = (int) data_get($payload, 'usage.input_tokens', $prompt);
            $completion = (int) data_get($payload, 'usage.output_tokens', $completion);
        }

        return [
            'prompt_tokens' => max(0, $prompt),
            'completion_tokens' => max(0, $completion),
            'total_tokens' => max(0, $prompt + $completion),
            'cost' => 0.0,
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function readStreamUsage(string $line, int $prompt, int $completion): array
    {
        $line = trim($line);

        if (! str_starts_with($line, 'data:')) {
            return [$prompt, $completion];
        }

        $data = trim(substr($line, 5));

        if ($data === '' || $data === '[DONE]') {
            return [$prompt, $completion];
        }

        $decoded = json_decode($data, true);

        if (! is_array($decoded)) {
            return [$prompt, $completion];
        }

        if (isset($decoded['usage']) && is_array($decoded['usage'])) {
            $prompt = max($prompt, (int) data_get($decoded, 'usage.prompt_tokens', 0));
            $completion = max($completion, (int) data_get($decoded, 'usage.completion_tokens', 0));
        }

        if (($decoded['type'] ?? null) === 'message_start') {
            $prompt = max($prompt, (int) data_get($decoded, 'message.usage.input_tokens', 0));
        }

        if (($decoded['type'] ?? null) === 'message_delta') {
            $completion = max($completion, (int) data_get($decoded, 'usage.output_tokens', 0));
        }

        return [$prompt, $completion];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function errorMessage(array $body): ?string
    {
        if ($body === []) {
            return null;
        }

        return (string) (data_get($body, 'error.message') ?: data_get($body, 'message') ?: Str::limit(json_encode($body), 500));
    }

    /**
     * Whether the operator wants this feature on for the request. A model
     * override outranks a provider override, which outranks the platform-wide
     * toggle set in the Features panel.
     */
    private function featureEnabled(string $feature, ?AiProvider $provider = null, ?AiModel $model = null): bool
    {
        $override = $model?->featureOverride($feature) ?? $provider?->featureOverride($feature);

        if ($override !== null) {
            return $override;
        }

        return $this->settings->boolean('feature_'.$feature.'_enabled');
    }

    /**
     * Applies every request-side feature the operator enabled and returns both
     * the input that becomes the upstream payload and the state needed to undo
     * the transformation on the answer.
     *
     * @param  array<string, mixed>  $input
     * @return array{input: array<string, mixed>, redactions: array<string, string>, notices: list<string>, compacted: bool, web_search: bool, json_repair: bool, repetition: bool, param_sanitizer: bool, pii_sanitizer: bool, patient_retry: bool}
     */
    private function prepareInput(array $input, string $endpoint, ?AiProvider $provider = null, ?AiModel $model = null): array
    {
        $redactions = [];
        $notices = [];
        $paramSanitizer = $this->featureEnabled('param_sanitizer', $provider, $model);
        $piiSanitizer = $this->featureEnabled('pii_sanitizer', $provider, $model);

        if ($paramSanitizer) {
            $input = $this->sanitizeAgentFields($input);
        }

        $jsonRepair = $this->featureEnabled('json_schema', $provider, $model);
        $compacted = false;

        if ($jsonRepair) {
            [$input, $compacted, $notice] = $this->fitJsonSchema($input);

            if ($notice !== null) {
                $notices[] = $notice;
            }
        }

        if ($piiSanitizer) {
            foreach (['messages', 'system', 'prompt'] as $field) {
                if (array_key_exists($field, $input)) {
                    $input[$field] = $this->pii->redactDeep($input[$field], $redactions);
                }
            }
        }

        return [
            'input' => $input,
            'redactions' => $redactions,
            'notices' => $notices,
            'compacted' => $compacted,
            'web_search' => $this->featureEnabled('web_search', $provider, $model) && $endpoint !== 'messages',
            'json_repair' => $jsonRepair,
            'repetition' => $this->featureEnabled('repetition_guard', $provider, $model),
            'param_sanitizer' => $paramSanitizer,
            'pii_sanitizer' => $piiSanitizer,
            'patient_retry' => $this->featureEnabled('patient_retry', $provider, $model),
        ];
    }

    /**
     * The audit record every gateway request stores, so the admin log and the
     * usage export name exactly which operator toggles touched the request.
     *
     * @param  array<string, mixed>  $prepared
     * @return array{features: list<string>, web_search_price?: float}
     */
    private function featureMeta(array $prepared, bool $substituted, bool $visionFired): array
    {
        $features = [];

        foreach (self::FEATURE_FLAGS as $flag => $name) {
            if (($prepared[$flag] ?? false) === true) {
                $features[] = $name;
            }
        }

        if ($visionFired) {
            $features[] = 'vision';
        }

        if ($substituted) {
            $features[] = 'model_substitute';
        }

        $meta = ['features' => array_values(array_unique($features))];

        if (($prepared['web_search'] ?? false) === true) {
            $meta['web_search_price'] = (float) $this->settings->get('feature_web_search_price', 0.0);
        }

        return $meta;
    }

    /**
     * Agents attach their own bookkeeping fields to a message and some networks
     * reject the whole request for it. Keep only the fields the protocol defines.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function sanitizeAgentFields(array $input): array
    {
        $allowed = [
            'role', 'content', 'name', 'tool_calls', 'tool_call_id',
            'function_call', 'refusal', 'audio', 'reasoning_content',
        ];

        if (! is_array($input['messages'] ?? null)) {
            return $input;
        }

        foreach ($input['messages'] as $index => $message) {
            if (! is_array($message)) {
                continue;
            }

            $clean = array_intersect_key($message, array_flip($allowed));

            // A message made only of unknown fields is left untouched rather
            // than emptied: the network's own error is more useful than ours.
            if ($clean !== []) {
                $input['messages'][$index] = $clean;
            }
        }

        return $input;
    }

    private function stripServiceTags(string $text): string
    {
        do {
            $changed = false;
            $text = rtrim($text);

            foreach (self::LEAKED_SERVICE_TAGS as $tag) {
                if (str_ends_with($text, $tag)) {
                    $text = rtrim(substr($text, 0, -strlen($tag)));
                    $changed = true;
                }
            }
        } while ($changed);

        return $text;
    }

    /**
     * JSON Schema Auto-Fit: above the network's node cap, drop the keywords that
     * only annotate a node. The structure is never touched.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: array<string, mixed>, 1: bool, 2: string|null}
     */
    private function fitJsonSchema(array $input): array
    {
        $schema = data_get($input, 'response_format.json_schema.schema');

        if (! is_array($schema) || data_get($input, 'response_format.type') !== 'json_schema') {
            return [$input, false, null];
        }

        $cap = (int) config('idexal.gateway.json_schema_node_cap', 2000);
        $before = $this->countSchemaNodes($schema);

        if ($before <= $cap) {
            return [$input, false, null];
        }

        $stripped = $this->stripAnnotationKeys($schema);
        $after = $this->countSchemaNodes($stripped);

        if ($after >= $before) {
            return [$input, false, null];
        }

        data_set($input, 'response_format.json_schema.schema', $stripped);

        return [$input, true, 'compacted '.($before - $after).' annotation nodes ('.$before.' -> '.$after.')'];
    }

    private function countSchemaNodes(mixed $node): int
    {
        if (! is_array($node)) {
            return 1;
        }

        $count = 1;

        foreach ($node as $value) {
            $count += $this->countSchemaNodes($value);
        }

        return $count;
    }

    private function stripAnnotationKeys(mixed $node): mixed
    {
        if (! is_array($node)) {
            return $node;
        }

        $clean = [];

        foreach ($node as $key => $value) {
            if (is_string($key) && in_array($key, self::JSON_SCHEMA_ANNOTATIONS, true)) {
                continue;
            }

            $clean[$key] = $this->stripAnnotationKeys($value);
        }

        return $clean;
    }

    /**
     * JSON-mode repair: a fenced or prose-wrapped JSON answer is reduced to the
     * JSON value itself, so the caller's JSON.parse() succeeds.
     */
    private function repairJsonContent(string $content): string
    {
        $trimmed = trim($content);

        if ($trimmed === '') {
            return $content;
        }

        if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $trimmed, $matches) === 1) {
            $trimmed = trim($matches[1]);
        }

        $objectStart = strpos($trimmed, '{');
        $arrayStart = strpos($trimmed, '[');

        if ($objectStart === false && $arrayStart === false) {
            return $content;
        }

        if ($objectStart !== false && ($arrayStart === false || $objectStart < $arrayStart)) {
            $start = $objectStart;
            $close = '}';
        } else {
            $start = (int) $arrayStart;
            $close = ']';
        }

        $end = strrpos($trimmed, $close);

        if ($end === false || $end < $start) {
            return $content;
        }

        $candidate = substr($trimmed, $start, $end - $start + 1);
        json_decode($candidate);

        return json_last_error() === JSON_ERROR_NONE ? $candidate : $content;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array{input: array<string, mixed>, redactions: array<string, string>, notices: list<string>, compacted: bool, web_search: bool, json_repair: bool, repetition: bool, param_sanitizer: bool, pii_sanitizer: bool, patient_retry: bool}  $prepared
     * @return array<string, mixed>
     */
    private function transformResponse(array $body, array $prepared, string $endpoint): array
    {
        if ($prepared['redactions'] !== []) {
            $body = $this->pii->restoreDeep($body, $prepared['redactions']);
        }

        if ($endpoint === 'messages') {
            foreach ((array) ($body['content'] ?? []) as $index => $block) {
                if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                    $body['content'][$index]['text'] = $this->transformText($block['text'], $prepared, $body, 'messages', (int) $index);
                }
            }

            return $body;
        }

        foreach ((array) ($body['choices'] ?? []) as $index => $choice) {
            if (! is_array($choice)) {
                continue;
            }

            $text = data_get($choice, 'message.content');

            if (is_string($text) && $text !== '') {
                $body['choices'][$index]['message']['content'] = $this->transformText($text, $prepared, $body, 'choice', (int) $index);
            }
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array{input: array<string, mixed>, redactions: array<string, string>, notices: list<string>, compacted: bool, web_search: bool, json_repair: bool, repetition: bool, param_sanitizer: bool, pii_sanitizer: bool, patient_retry: bool}  $prepared
     */
    private function transformText(string $text, array $prepared, array &$body, string $kind, int $index): string
    {
        if ($prepared['json_repair']) {
            $text = $this->repairJsonContent($text);
        }

        if ($prepared['repetition']) {
            $cut = RepetitionGuard::detect($text);

            if ($cut !== null) {
                $text = substr($text, 0, $cut);

                if ($kind === 'choice') {
                    $body['choices'][$index]['finish_reason'] = 'stop';
                } else {
                    $body['stop_reason'] = 'end_turn';
                }
            }
        }

        if ($prepared['param_sanitizer']) {
            $text = $this->stripServiceTags($text);
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $chunk
     * @param  array{input: array<string, mixed>, redactions: array<string, string>, notices: list<string>, compacted: bool, web_search: bool, json_repair: bool, repetition: bool, param_sanitizer: bool, pii_sanitizer: bool, patient_retry: bool}  $prepared
     * @return array{chunk: array<string, mixed>, stop: bool}
     */
    private function transformStreamChunk(array $chunk, array $prepared, RepetitionGuard $guard): array
    {
        if ($prepared['redactions'] !== []) {
            $chunk = $this->pii->restoreDeep($chunk, $prepared['redactions']);
        }

        if (! is_array($chunk['choices'] ?? null)) {
            return ['chunk' => $chunk, 'stop' => false];
        }

        $sanitize = $prepared['param_sanitizer'];
        $stop = false;

        foreach ($chunk['choices'] as $index => $choice) {
            if (! is_array($choice) || ! is_array($choice['delta'] ?? null)) {
                continue;
            }

            $text = $choice['delta']['content'] ?? null;

            if (! is_string($text) || $text === '') {
                continue;
            }

            if ($prepared['repetition']) {
                $guard->push($text);

                if ($guard->looping()) {
                    // The first copy already reached the client: drop this
                    // chunk's content and end the answer.
                    $chunk['choices'][$index]['delta']['content'] = '';
                    $chunk['choices'][$index]['finish_reason'] = 'stop';
                    $stop = true;

                    continue;
                }
            }

            if ($sanitize) {
                $chunk['choices'][$index]['delta']['content'] = $this->stripServiceTags($text);
            }
        }

        return ['chunk' => $chunk, 'stop' => $stop];
    }

    /**
     * @param  array{input: array<string, mixed>, redactions: array<string, string>, notices: list<string>, compacted: bool, web_search: bool, json_repair: bool, repetition: bool, param_sanitizer: bool, pii_sanitizer: bool, patient_retry: bool}  $prepared
     * @return array<string, string>
     */
    private function responseHeaders(array $prepared): array
    {
        $headers = [];

        if ($prepared['compacted']) {
            $headers['X-Json-Schema-Compacted'] = 'true';
        }

        if ($prepared['notices'] !== []) {
            $headers['X-Json-Schema-Notice'] = implode('; ', $prepared['notices']);
        }

        return $headers;
    }

    /**
     * Patient Retry: a 429 is retried on the same slot with an exponentially
     * growing, jittered interval until the attempt or time budget is spent.
     *
     * @param  callable(): Response  $send
     */
    private function withPatientRetry(callable $send, ?AiProvider $provider = null, ?AiModel $model = null): Response
    {
        if (! $this->featureEnabled('patient_retry', $provider, $model)) {
            return $send();
        }

        $maxAttempts = max(1, $this->settings->integer('feature_patient_retry_attempts', 6));
        $budget = max(1, $this->settings->integer('feature_patient_retry_budget', 60));
        $startedAt = microtime(true);
        $attempt = 0;

        while (true) {
            $response = $send();
            $attempt++;

            if ($response->status() !== 429 || $attempt >= $maxAttempts) {
                return $response;
            }

            $elapsed = microtime(true) - $startedAt;

            if ($elapsed >= $budget) {
                return $response;
            }

            $sleep = min($this->backoffMicros($attempt), (int) max(0, ($budget - $elapsed) * 1_000_000));

            if ($sleep > 0) {
                usleep($sleep);
            }
        }
    }

    private function backoffMicros(int $attempt): int
    {
        $base = min(2 ** $attempt, 15);
        $jitter = 0.5 + (random_int(0, 1000) / 1000);

        return (int) ($base * $jitter * 1_000_000);
    }

    /**
     * Vision (auto image description): a text-only model still answers an image
     * request because the images are described first and the description is
     * forwarded in their place.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: array<string, mixed>, 1: bool} The input and whether a description was substituted.
     */
    private function applyVision(array $input, AiModel $model, Organization $organization, string $endpoint, ?AiProvider $provider = null): array
    {
        if (! $this->featureEnabled('vision', $provider, $model) || self::$describingImages || $endpoint === 'messages') {
            return [$input, false];
        }

        $images = $this->collectImages($input);

        if ($images === [] || $model->supports('vision') || $model->supports('image_input')) {
            return [$input, false];
        }

        $visionModel = trim((string) $this->settings->get('feature_vision_model_id', ''));

        if ($visionModel === '') {
            $visionModel = (string) $model->model_id;
        }

        $description = $this->describeImages($images, $visionModel, $organization);

        return $description === null
            ? [$input, false]
            : [$this->replaceImagesWithText($input, $description), true];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    private function collectImages(array $input): array
    {
        $images = [];

        foreach ((array) ($input['messages'] ?? []) as $message) {
            if (! is_array($message) || ! is_array($message['content'] ?? null)) {
                continue;
            }

            foreach ($message['content'] as $part) {
                if (! is_array($part)) {
                    continue;
                }

                $image = $this->toImagePart($part);

                if ($image !== null) {
                    $images[] = $image;
                }
            }
        }

        return $images;
    }

    /**
     * Normalizes an image part from either the OpenAI or the Anthropic shape
     * into the OpenAI image_url shape the description call uses.
     *
     * @param  array<string, mixed>  $part
     * @return array<string, mixed>|null
     */
    private function toImagePart(array $part): ?array
    {
        if (isset($part['image_url'])) {
            $url = is_array($part['image_url']) ? $part['image_url'] : ['url' => $part['image_url']];

            return ['type' => 'image_url', 'image_url' => $url];
        }

        $source = $part['source'] ?? null;

        if (is_array($source) && ($source['type'] ?? null) === 'base64' && isset($source['data'])) {
            $media = (string) ($source['media_type'] ?? 'image/png');

            return ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$media.';base64,'.$source['data']]];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function replaceImagesWithText(array $input, string $description): array
    {
        foreach ((array) ($input['messages'] ?? []) as $index => $message) {
            if (! is_array($message) || ! is_array($message['content'] ?? null)) {
                continue;
            }

            foreach ($message['content'] as $partIndex => $part) {
                if (is_array($part) && $this->toImagePart($part) !== null) {
                    $input['messages'][$index]['content'][$partIndex] = [
                        'type' => 'text',
                        'text' => '[image: '.$description.']',
                    ];
                }
            }
        }

        return $input;
    }

    /**
     * @param  list<array<string, mixed>>  $images
     */
    private function describeImages(array $images, string $visionModelName, Organization $organization): ?string
    {
        self::$describingImages = true;

        try {
            $context = $this->firstUsableContext($organization, $visionModelName);

            if ($context === null) {
                return null;
            }

            $credentials = $this->keys->credentialsFor($context['provider']);
            $credential = $credentials[0] ?? null;

            if ($credential === null) {
                return null;
            }

            $content = [
                ...$images,
                ['type' => 'text', 'text' => 'Describe each image in detail: objects, people, text, layout and colours. Reply in plain text only.'],
            ];

            $response = $this->httpClient()
                ->withHeaders($this->headers($context['provider'], false, $credential['value']))
                ->connectTimeout(10)
                ->timeout($this->settings->integer('gateway_timeout', (int) config('idexal.gateway.timeout', 180)))
                ->post($context['url'], [
                    'model' => $context['model']->model_id,
                    'stream' => false,
                    'max_tokens' => 512,
                    'messages' => [['role' => 'user', 'content' => $content]],
                ]);

            if ($response->failed()) {
                return null;
            }

            $text = data_get($response->json(), 'choices.0.message.content');

            return is_string($text) && trim($text) !== '' ? trim($text) : null;
        } catch (Throwable) {
            return null;
        } finally {
            self::$describingImages = false;
        }
    }

    /**
     * @return array{model: AiModel, provider: AiProvider, url: string}|null
     */
    private function firstUsableContext(Organization $organization, string $modelName): ?array
    {
        $fallback = null;

        foreach ($this->resolveModels($organization, $modelName, 'chat/completions') as $model) {
            $provider = $model->provider;

            if (! $provider instanceof AiProvider) {
                continue;
            }

            $url = $this->upstreamUrl($provider, 'chat/completions');

            if ($url === null) {
                continue;
            }

            $context = ['model' => $model, 'provider' => $provider, 'url' => $url];

            if ($model->supports('vision') || $model->supports('image_input')) {
                return $context;
            }

            $fallback ??= $context;
        }

        return $fallback;
    }
}
