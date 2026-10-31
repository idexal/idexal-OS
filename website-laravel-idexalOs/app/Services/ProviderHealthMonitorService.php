<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\ProviderHealthLog;
use App\Support\OutboundHttp;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Probes providers with their own credentials so the admin can see which
 * upstreams are actually answering, and keeps the newest result per provider.
 *
 * The probe is a lightweight model listing: it is free, needs no tokens, and
 * uses the same base URL and key the gateway would use, so a green badge means
 * the gateway has a working path to that provider.
 */
class ProviderHealthMonitorService
{
    public function __construct(
        private readonly ProviderKeyRotationService $keys,
    ) {}

    /**
     * Check one provider and record the result.
     *
     * @return array{provider_id: string, status: string, response_time_ms: int|null, error_rate: float, error_message: string|null, checked_at: string}
     */
    public function checkProviderHealth(AiProvider $provider): array
    {
        $apiKey = $this->firstKey($provider);

        if ($apiKey === null) {
            return $this->record(
                $provider,
                ProviderHealthLog::STATUS_DEGRADED,
                null,
                1.0,
                __('ui.admin.providers.no_key'),
            );
        }

        $startedAt = hrtime(true);

        try {
            $response = OutboundHttp::client()
                ->withHeaders($this->probeHeaders($provider, $apiKey))
                ->connectTimeout(5)
                ->timeout(12)
                ->get(rtrim($provider->base_url, '/').'/models');
        } catch (Throwable $exception) {
            report($exception);

            return $this->record(
                $provider,
                ProviderHealthLog::STATUS_UNHEALTHY,
                (int) ((hrtime(true) - $startedAt) / 1_000_000),
                1.0,
                $exception->getMessage(),
            );
        }

        $latency = (int) ((hrtime(true) - $startedAt) / 1_000_000);
        $status = $response->status();

        if ($response->successful()) {
            return $this->record($provider, ProviderHealthLog::STATUS_HEALTHY, $latency, 0.0, null);
        }

        if ($status === 429 || $status >= 500) {
            return $this->record(
                $provider,
                ProviderHealthLog::STATUS_DEGRADED,
                $latency,
                0.5,
                'HTTP '.$status,
            );
        }

        return $this->record(
            $provider,
            ProviderHealthLog::STATUS_UNHEALTHY,
            $latency,
            1.0,
            'HTTP '.$status,
        );
    }

    /**
     * Check every active provider once.
     *
     * @return Collection<int, array{provider_id: string, status: string, response_time_ms: int|null, error_rate: float, error_message: string|null, checked_at: string}>
     */
    public function checkAllProviders(): Collection
    {
        return AiProvider::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->get()
            ->map(fn (AiProvider $provider): array => $this->checkProviderHealth($provider))
            ->values();
    }

    /**
     * Newest result per provider, keyed by provider id, for the admin screen.
     *
     * @return Collection<string, ProviderHealthLog>
     */
    public function latestByProvider(): Collection
    {
        $latestIds = ProviderHealthLog::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('ai_provider_id')
            ->pluck('id');

        return ProviderHealthLog::query()
            ->whereIn('id', $latestIds)
            ->get()
            ->keyBy(fn (ProviderHealthLog $log): string => (string) $log->ai_provider_id);
    }

    /**
     * @return array{provider_id: string, status: string, response_time_ms: int|null, error_rate: float, error_message: string|null, checked_at: string}
     */
    private function record(
        AiProvider $provider,
        string $status,
        ?int $latency,
        float $errorRate,
        ?string $errorMessage,
    ): array {
        $now = now();

        ProviderHealthLog::query()->create([
            'ai_provider_id' => $provider->getKey(),
            'check_time' => $now,
            'status' => $status,
            'response_time_ms' => $latency,
            'error_rate' => $errorRate,
            'error_message' => $errorMessage,
            'metadata' => ['latency_ms' => $latency],
        ]);

        return [
            'provider_id' => (string) $provider->getKey(),
            'status' => $status,
            'response_time_ms' => $latency,
            'error_rate' => $errorRate,
            'error_message' => $errorMessage,
            'checked_at' => $now->toIso8601String(),
        ];
    }

    private function firstKey(AiProvider $provider): ?string
    {
        foreach ($this->keys->credentialsFor($provider) as $credential) {
            if ($credential['value'] !== '') {
                return $credential['value'];
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function probeHeaders(AiProvider $provider, string $apiKey): array
    {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'idexal-os-health/1.0',
        ];

        if ($provider->driver === AiProvider::DRIVER_ANTHROPIC) {
            $headers['x-api-key'] = $apiKey;
            $headers['anthropic-version'] = '2023-06-01';
        } else {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        return $headers;
    }
}
