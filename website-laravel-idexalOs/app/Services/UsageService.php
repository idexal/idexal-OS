<?php

namespace App\Services;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ApiKey;
use App\Models\GatewayRequest;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\UsageCounter;
use App\Support\GatewayFeature;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UsageService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly CreditService $credits,
    ) {}

    /**
     * @return array{
     *     period: string,
     *     requests: int,
     *     prompt_tokens: int,
     *     completion_tokens: int,
     *     total_tokens: int,
     *     cost: float,
     *     limits: array{requests: int|null, tokens: int|null, seats: int|null, api_keys: int|null},
     *     percentages: array{requests: float, tokens: float},
     *     over_quota: bool,
     *     blocked: bool,
     *     credit_balance: float,
     *     plan: Plan|null
     * }
     */
    public function summary(Organization $organization, ?string $period = null): array
    {
        $counter = UsageCounter::forOrganization($organization, $period);
        $plan = $organization->currentPlan();
        $requestLimit = $this->integerLimit($plan?->limit(Plan::LIMIT_REQUESTS));
        $tokenLimit = $this->integerLimit($plan?->limit(Plan::LIMIT_TOKENS));

        $requests = (int) $counter->requests;
        $tokens = (int) $counter->total_tokens;
        $overQuota = ($requestLimit !== null && $requests >= $requestLimit)
            || ($tokenLimit !== null && $tokens >= $tokenLimit);

        return [
            'period' => $counter->period,
            'requests' => $requests,
            'prompt_tokens' => (int) $counter->prompt_tokens,
            'completion_tokens' => (int) $counter->completion_tokens,
            'total_tokens' => $tokens,
            'cost' => (float) $counter->cost,
            'limits' => [
                'requests' => $requestLimit,
                'tokens' => $tokenLimit,
                'seats' => $this->integerLimit($plan?->limit(Plan::LIMIT_SEATS)),
                'api_keys' => $this->integerLimit($plan?->limit(Plan::LIMIT_API_KEYS)),
            ],
            'percentages' => [
                'requests' => $requestLimit !== null && $requestLimit > 0
                    ? min(100, round(($requests / $requestLimit) * 100, 2))
                    : 0.0,
                'tokens' => $tokenLimit !== null && $tokenLimit > 0
                    ? min(100, round(($tokens / $tokenLimit) * 100, 2))
                    : 0.0,
            ],
            'over_quota' => $overQuota,
            'blocked' => $overQuota && $organization->creditBalance() <= 0,
            'credit_balance' => $organization->creditBalance(),
            'plan' => $plan,
        ];
    }

    /**
     * @return array{allowed: bool, overage: bool, reason: string|null}
     */
    public function admission(Organization $organization): array
    {
        if (! $this->settings->boolean('gateway_enabled', true)) {
            return ['allowed' => false, 'overage' => false, 'reason' => 'gateway_disabled'];
        }

        if ($organization->isSuspended()) {
            return ['allowed' => false, 'overage' => false, 'reason' => 'organization_suspended'];
        }

        $summary = $this->summary($organization);

        if (! $summary['over_quota']) {
            return ['allowed' => true, 'overage' => false, 'reason' => null];
        }

        if ($summary['credit_balance'] > 0) {
            return ['allowed' => true, 'overage' => true, 'reason' => null];
        }

        return ['allowed' => false, 'overage' => true, 'reason' => 'quota_exceeded'];
    }

    /**
     * @return array{requests: int, prompt_tokens: int, completion_tokens: int, total_tokens: int, cost: float}
     */
    public function usageFromResponse(string $endpoint, array $payload): array
    {
        $prompt = (int) data_get($payload, 'usage.prompt_tokens', data_get($payload, 'usage.input_tokens', 0));
        $completion = (int) data_get($payload, 'usage.completion_tokens', data_get($payload, 'usage.output_tokens', 0));

        if ($endpoint === 'messages') {
            $prompt = (int) data_get($payload, 'usage.input_tokens', $prompt);
            $completion = (int) data_get($payload, 'usage.output_tokens', $completion);
        }

        return [
            'requests' => 1,
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $prompt + $completion,
            'cost' => 0.0,
        ];
    }

    /**
     * @param  array{prompt_tokens: int, completion_tokens: int, total_tokens: int, cost: float}  $usage
     * @param  array<string, mixed>|null  $meta  What the feature pipeline did, for the admin audit trail.
     */
    public function record(
        Organization $organization,
        ApiKey $apiKey,
        ?AiProvider $provider,
        ?AiModel $model,
        string $endpoint,
        string $modelName,
        int $statusCode,
        array $usage,
        int $latencyMs,
        bool $isStream,
        bool $chargeCredits,
        ?string $error = null,
        ?string $ip = null,
        ?array $meta = null,
    ): GatewayRequest {
        $requestId = 'req_'.Str::random(24);
        $prompt = max(0, (int) ($usage['prompt_tokens'] ?? 0));
        $completion = max(0, (int) ($usage['completion_tokens'] ?? 0));
        $total = max(0, (int) ($usage['total_tokens'] ?? ($prompt + $completion)));
        $cost = max(0, (float) ($usage['cost'] ?? 0));

        return DB::transaction(function () use (
            $organization,
            $apiKey,
            $provider,
            $model,
            $endpoint,
            $modelName,
            $statusCode,
            $prompt,
            $completion,
            $total,
            $cost,
            $latencyMs,
            $isStream,
            $chargeCredits,
            $error,
            $ip,
            $meta,
            $requestId,
        ): GatewayRequest {
            $request = GatewayRequest::query()->create([
                'request_id' => $requestId,
                'organization_id' => $organization->getKey(),
                'user_id' => $apiKey->user_id,
                'api_key_id' => $apiKey->getKey(),
                'ai_provider_id' => $provider?->getKey(),
                'ai_model_id' => $model?->getKey(),
                'endpoint' => $endpoint,
                'model' => $modelName,
                'status_code' => $statusCode,
                'prompt_tokens' => $prompt,
                'completion_tokens' => $completion,
                'total_tokens' => $total,
                'cost' => $cost,
                'latency_ms' => max(0, $latencyMs),
                'is_stream' => $isStream,
                'error' => $error,
                'meta' => $meta,
                'ip' => $ip,
                'created_at' => now(),
            ]);

            $counter = UsageCounter::forOrganization($organization);

            UsageCounter::query()->whereKey($counter->getKey())->update([
                'requests' => DB::raw('requests + 1'),
                'prompt_tokens' => DB::raw('prompt_tokens + '.$prompt),
                'completion_tokens' => DB::raw('completion_tokens + '.$completion),
                'total_tokens' => DB::raw('total_tokens + '.$total),
                'cost' => DB::raw('cost + '.number_format($cost, 8, '.', '')),
                'updated_at' => now(),
            ]);

            if ($chargeCredits && $cost > 0) {
                try {
                    $this->credits->debit(
                        $organization,
                        $cost,
                        __('ui.billing.usage_debit', ['model' => $modelName]),
                        'gateway_request',
                        $request->getKey(),
                    );
                } catch (InvalidArgumentException) {
                    // The request is already complete; never fail the response because credit ran out mid-flight.
                }
            }

            return $request;
        });
    }

    /**
     * @return list<array{date: string, requests: int, tokens: int, cost: float}>
     */
    public function dailySeries(Organization $organization, Carbon $from, Carbon $to): array
    {
        $rows = GatewayRequest::query()
            ->where('organization_id', $organization->getKey())
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as requests, COALESCE(SUM(total_tokens), 0) as tokens, COALESCE(SUM(cost), 0) as cost')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $series = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $row = $rows->get($key);

            $series[] = [
                'date' => $key,
                'requests' => (int) ($row->requests ?? 0),
                'tokens' => (int) ($row->tokens ?? 0),
                'cost' => (float) ($row->cost ?? 0),
            ];

            $cursor->addDay();
        }

        return $series;
    }

    /**
     * Requests each gateway feature ran on, per day, over the range. Only names
     * in `GatewayFeature` are counted, so a usage export never echoes a
     * feature the platform dropped. Portable across MySQL and SQLite: one
     * grouped `whereJsonContains` per feature, at most one row per day.
     *
     * Pass null to count every workspace, which is what the operator panel needs:
     * a feature's platform-wide activity is not any one workspace's business.
     *
     * @return array<string, array<string, int>> date => feature => requests
     */
    public function dailyFeatureUsage(?Organization $organization, Carbon $from, Carbon $to): array
    {
        $usage = [];

        foreach (GatewayFeature::keys() as $feature) {
            $rows = GatewayRequest::query()
                ->when(
                    $organization instanceof Organization,
                    fn ($query) => $query->where('organization_id', $organization->getKey()),
                )
                ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->whereJsonContains('meta->features', $feature)
                ->selectRaw('DATE(created_at) as day, COUNT(*) as requests')
                ->groupBy('day')
                ->get();

            foreach ($rows as $row) {
                $usage[(string) $row->day][$feature] = (int) $row->requests;
            }
        }

        return $usage;
    }

    /**
     * Requests each gateway feature ran on over the range, summed across days.
     * Canonical feature order is preserved so the page renders the same tiles in
     * the same order no matter which features happened to run.
     *
     * @return array<string, int> feature => requests
     */
    public function featureTotals(?Organization $organization, Carbon $from, Carbon $to): array
    {
        $totals = array_fill_keys(GatewayFeature::keys(), 0);

        foreach ($this->dailyFeatureUsage($organization, $from, $to) as $days) {
            foreach ($days as $feature => $requests) {
                $totals[$feature] += $requests;
            }
        }

        return $totals;
    }

    public function recentRequests(Organization $organization, int $perPage = 20): LengthAwarePaginator
    {
        return GatewayRequest::query()
            ->with(['provider:id,name,slug', 'aiModel:id,display_name,model_id'])
            ->where('organization_id', $organization->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    private function integerLimit(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }
}
