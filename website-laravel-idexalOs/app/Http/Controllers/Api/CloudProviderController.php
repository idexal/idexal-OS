<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\ApiKey;
use App\Models\Organization;
use App\Services\GatewayService;
use App\Services\GatewayToken;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Managed provider contract for the desktop cloud-provider sync.
 *
 * The desktop materializes one OpenAI-compatible provider whose base URL is
 * this platform's gateway and whose credential is a derived ow_gw_ token.
 * Upstream provider keys never leave the server. Legacy llm-providers stay
 * empty on purpose: this platform is a gateway, not a key distributor.
 */
class CloudProviderController extends Controller
{
    private const GATEWAY_PROVIDER_ID_PREFIX = 'ipr_';

    public function __construct(
        private readonly GatewayService $gateway,
        private readonly OrganizationContext $context,
    ) {}

    public function llmProviders(): JsonResponse
    {
        return response()->json(['llmProviders' => []]);
    }

    public function connectLlmProvider(string $provider): JsonResponse
    {
        return response()->json([
            'error' => ['message' => 'Provider not found', 'type' => 'not_found'],
        ], 404);
    }

    public function inferenceProviders(Request $request): JsonResponse
    {
        $organization = $this->context->requireOrganization();
        $provider = $this->provider($organization, $request);

        return response()->json([
            'inferenceProviders' => $provider === null ? [] : [$provider],
        ]);
    }

    public function connectInferenceProvider(Request $request, string $provider): JsonResponse
    {
        $organization = $this->context->requireOrganization();
        $row = $this->provider($organization, $request);

        if ($row === null || $row['id'] !== $provider) {
            return response()->json([
                'error' => ['message' => 'Provider not found', 'type' => 'not_found'],
            ], 404);
        }

        $apiKey = $request->attributes->get('api_key');

        if (! $apiKey instanceof ApiKey) {
            return response()->json([
                'error' => ['message' => __('ui.errors.missing_api_key'), 'type' => 'authentication_error'],
            ], 401);
        }

        $token = app(GatewayToken::class)->issue($apiKey);
        $envName = (string) ($row['providerConfig']['env'][0] ?? '');

        $row['apiKey'] = $token;
        $row['apiKeys'] = [$envName => $token];

        return response()->json(['inferenceProvider' => $row]);
    }

    /**
     * Desktop provider-sync invalidation stream. This host buffers proxied
     * responses until they close, so the stream is deliberately short-lived:
     * the desktop reconciles on every (re)connect, then opens a fresh cycle.
     * Ten seconds keeps admin provider/model/plan changes flowing into a
     * running app without a full-inventory poll from every open desktop.
     */
    public function inventoryEvents(): StreamedResponse
    {
        $this->context->requireOrganization();

        return response()->stream(function (): void {
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            ignore_user_abort(true);
            @set_time_limit(0);

            // A padded comment releases small first writes on hosts that buffer.
            echo ': connected'.str_repeat(' ', 2048)."\n\n";
            echo "event: inventory.changed\ndata: {}\n\n";
            flush();

            // Watch the shared sync marker written by admin saves. When a
            // provider or model changes, connected desktops get an immediate
            // inventory.changed event and refetch without waiting for their
            // polling interval. The stream ends after a few minutes; the
            // desktop reconnects and the cycle repeats.
            $lastSequence = (string) Cache::get('desktop.sync.sequence', '0');
            $deadline = time() + 240;

            while (time() < $deadline && ! connection_aborted()) {
                sleep(2);

                $sequence = (string) Cache::get('desktop.sync.sequence', '0');

                if ($sequence !== $lastSequence) {
                    $lastSequence = $sequence;
                    $latest = Cache::get('desktop.sync.latest');

                    echo 'event: inventory.changed'
                        ."\ndata: ".json_encode(is_array($latest) ? $latest : new \stdClass)
                        ."\n\n";
                    flush();
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function provider(Organization $organization, Request $request): ?array
    {
        $models = $this->gateway->desktopGatewayModels($organization);

        if ($models->isEmpty()) {
            return null;
        }

        $organizationKey = (string) $organization->getKey();
        $providerId = self::GATEWAY_PROVIDER_ID_PREFIX.$organizationKey;
        $envName = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '_', $providerId)).'_API_KEY';
        $baseUrl = url('/v1');

        $rows = $models->map(function (AiModel $model) use ($organizationKey): array {
            $name = (string) ($model->display_name ?: $model->model_id);
            $modelId = 'gwm_'.$organizationKey.'_'.$organizationKey.'_'.$model->getKey();

            $config = ['id' => $modelId, 'name' => $name];

            if ($model->context_window) {
                $config['limit']['context'] = (int) $model->context_window;
            }

            if ($model->max_output_tokens) {
                $config['limit']['output'] = (int) $model->max_output_tokens;
            }

            if ($model->supports('tools')) {
                $config['tool_call'] = true;
            }

            if ($model->supports('reasoning')) {
                $config['reasoning'] = true;
            }

            if (is_array($model->reasoning_options) && $model->reasoning_options !== []) {
                $config['reasoning_options'] = [[
                    'type' => 'effort',
                    'values' => array_values($model->reasoning_options),
                ]];
            }

            if ($this->gateway->acceptsImages($model)) {
                $config['attachment'] = true;
            }

            return [
                'id' => $modelId,
                'name' => $name,
                'config' => $config,
                'upstreamModelId' => (string) $model->model_id,
                'modelGroupId' => 'gmg_'.$organizationKey,
                'modelGroupName' => 'idexal Os',
                'credentialSetId' => 'gcs_'.$organizationKey,
                'credentialSetName' => (string) ($model->provider?->name ?: 'idexal Os'),
            ];
        })->values()->all();

        $updatedAt = $models
            ->map(fn (AiModel $model) => $model->updated_at)
            ->filter()
            ->sortDesc()
            ->first();

        return [
            'id' => $providerId,
            'providerId' => $providerId,
            'name' => 'idexal Os Gateway',
            'source' => 'idexal_os_gateway',
            'updatedAt' => $updatedAt?->toIso8601String() ?? '1970-01-01T00:00:00Z',
            'providerConfig' => [
                'npm' => '@ai-sdk/openai-compatible',
                'env' => [$envName],
                'api' => $baseUrl,
                'options' => ['baseURL' => $baseUrl],
            ],
            'models' => $rows,
            'pinnedModelIds' => [],
            'credentialStatus' => 'ready',
            'authUrl' => null,
            'authorizationRequests' => [],
        ];
    }
}
