<?php

namespace App\Services;

use App\Models\AiModel;
use App\Models\ApiKey;
use App\Models\FreeInferenceCredential;
use App\Models\GatewayRequest;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class FreeInferenceService
{
    public const PROVIDER_ID = 'idexal-os-free';

    public const MODEL_ID = 'openai/gpt-6-luna';

    public function __construct(private readonly GatewayService $gateway) {}

    public function enabled(): bool
    {
        return (bool) Setting::get('free_auto_enabled', true);
    }

    /**
     * The signed-in Auto credential. The plaintext is kept encrypted at rest
     * and reused until it nears expiry so the member's usage keeps one ledger.
     *
     * @return array{credential: array{apiKey: string, modelID: string, baseURL: string, statusURL: string}}
     */
    public function credential(Organization $organization, User $user): array
    {
        $existing = FreeInferenceCredential::query()
            ->with('apiKey')
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $user->getKey())
            ->where('expires_at', '>', now()->addHour())
            ->latest('expires_at')
            ->first();

        if ($existing instanceof FreeInferenceCredential
            && $existing->apiKey instanceof ApiKey
            && $existing->apiKey->isActive()) {
            try {
                $token = $existing->encrypted_token;
            } catch (\Throwable) {
                $token = null;
            }

            if (is_string($token) && $token !== '') {
                return $this->credentialPayload($token);
            }
        }

        $plain = 'ow_inf_'.Str::random(43);
        $expiresAt = now()->addDays(30);

        $apiKey = ApiKey::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'name' => 'Auto inference',
            'key_prefix' => mb_substr($plain, 0, 16),
            'key_hash' => hash('sha256', $plain),
            'abilities' => [
                ApiKey::ABILITY_GATEWAY_CHAT,
                ApiKey::ABILITY_GATEWAY_MODELS,
            ],
            'expires_at' => $expiresAt,
        ]);

        FreeInferenceCredential::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'api_key_id' => $apiKey->getKey(),
            'encrypted_token' => $plain,
            'expires_at' => $expiresAt,
        ]);

        return $this->credentialPayload($plain);
    }

    /**
     * @return array{state: string, code: string|null, minimumVersion: null, allowance: array{limitUsd: float, usedUsd: float, remainingUsd: float, resetsAt: string}}
     */
    public function status(Organization $organization, User $user): array
    {
        $weekStart = now()->utc()->startOfWeek(Carbon::MONDAY);
        $resetsAt = $weekStart->copy()->addWeek()->format('Y-m-d\TH:i:s\Z');
        $limit = max(0.0, (float) Setting::get('free_auto_weekly_usd', 5.0));

        $apiKeyIds = FreeInferenceCredential::query()
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $user->getKey())
            ->pluck('api_key_id');

        $used = (float) GatewayRequest::query()
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $user->getKey())
            ->whereIn('api_key_id', $apiKeyIds)
            ->where('created_at', '>=', $weekStart)
            ->sum('cost');

        $remaining = max(0.0, $limit - $used);
        $state = $remaining <= 0 ? 'exhausted' : 'ready';

        return [
            'state' => $state,
            'code' => $state === 'ready' ? null : 'free_allowance_exhausted',
            'minimumVersion' => null,
            'allowance' => [
                'limitUsd' => round($limit, 6),
                'usedUsd' => round($used, 6),
                'remainingUsd' => round($remaining, 6),
                'resetsAt' => $resetsAt,
            ],
        ];
    }

    public function autoModel(Organization $organization): ?AiModel
    {
        $configured = trim((string) Setting::get('free_auto_model_id', ''));
        $models = $this->gateway->desktopGatewayModels($organization);

        if ($configured !== '') {
            $model = $models->first(fn (AiModel $candidate): bool => $candidate->getKey() === $configured
                || $candidate->model_id === $configured);
            if ($model instanceof AiModel) {
                return $model;
            }
        }

        $model = $models->first();

        return $model instanceof AiModel ? $model : null;
    }

    /**
     * @return array{credential: array{apiKey: string, modelID: string, baseURL: string, statusURL: string}}
     */
    private function credentialPayload(string $plain): array
    {
        return [
            'credential' => [
                'apiKey' => $plain,
                'modelID' => self::MODEL_ID,
                'baseURL' => url('/api/v1'),
                'statusURL' => url('/api/v1/auto/status'),
            ],
        ];
    }
}
