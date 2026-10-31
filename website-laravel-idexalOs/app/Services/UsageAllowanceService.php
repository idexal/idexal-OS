<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\UsageCounter;
use App\Models\UsageLimitResetRequest;
use Illuminate\Support\Carbon;

/**
 * The monthly spend allowance a plan grants, as the usage bucket the desktop
 * reads.
 *
 * This platform limits usage by requests and tokens per period and already
 * tracks what the period cost. A plan may additionally carry a monthly cost
 * allowance, and when it does this builds one `month` bucket from it: the base
 * from the plan, the extension from every request an operator approved for the
 * same period, and the spend from the period's own counter — all real figures,
 * nothing invented. The bucket reports itself as a soft budget (`hardLimit`
 * false) because the hard stop on this platform remains the plan's request and
 * token limits; claiming a block we do not enforce would be a lie in the UI.
 */
class UsageAllowanceService
{
    private const MICRO_USD = 1_000_000;

    /**
     * The bucket id the desktop uses to ask for more. Stable per organization,
     * so an approval can be matched to it across periods.
     */
    public static function bucketId(Organization $organization): string
    {
        return 'cost_month_'.$organization->getKey();
    }

    /**
     * The bucket for the current period, or null when the plan sets no cost
     * allowance — in which case the desktop is told nothing rather than told
     * zero.
     *
     * @return array<string, mixed>|null
     */
    public function bucketFor(Organization $organization): ?array
    {
        $plan = $organization->currentPlan();
        $allowanceUsd = $this->allowanceUsd($plan);

        if ($allowanceUsd === null) {
            return null;
        }

        $period = UsageCounter::currentPeriod();
        $counter = UsageCounter::forOrganization($organization, $period);
        $bucketId = self::bucketId($organization);

        $base = (int) round($allowanceUsd * self::MICRO_USD);
        $extension = UsageLimitResetRequest::extensionMicroUsd($organization->getKey(), $bucketId, $period);
        $used = (int) round(((float) $counter->cost) * self::MICRO_USD);
        $allowance = $base + $extension;

        $exhausted = $allowance > 0 && $used >= $allowance;
        $status = $this->requestStatus($organization, $bucketId, $period);

        return [
            'id' => $bucketId,
            'timeframe' => 'month',
            'policyId' => 'plan_'.($plan?->slug ?? 'free'),
            'policyName' => (string) ($plan?->name ?? 'Free'),
            'baseAllowanceMicroUsd' => $base,
            'extensionMicroUsd' => $extension,
            'allowanceMicroUsd' => $allowance,
            'usedMicroUsd' => $used,
            'remainingMicroUsd' => $allowance - $used,
            'resetAt' => $this->resetAt($period),
            'hardLimit' => false,
            'allowRequestReset' => true,
            // An ask already waiting is not asked again: the desktop reads this
            // and shows "waiting for an answer" instead of the button.
            'canRequestReset' => $exhausted && $status !== 'pending',
            'resetRequestStatus' => $status,
            'providerId' => null,
        ];
    }

    /**
     * The period the bucket covers, as `Y-m`, and the instant it resets.
     */
    public function resetAt(string $period): string
    {
        return Carbon::createFromFormat('Y-m', $period, 'UTC')
            ->startOfMonth()
            ->addMonth()
            ->toIso8601String();
    }

    /**
     * The allowance the plan declares, or null when it declares none.
     */
    public function allowanceUsd(?Plan $plan): ?float
    {
        if (! $plan instanceof Plan) {
            return null;
        }

        $value = $plan->limit(Plan::LIMIT_MONTHLY_COST_USD);

        if ($value === null || $value === '') {
            return null;
        }

        $usd = (float) $value;

        return $usd > 0 ? $usd : null;
    }

    /**
     * What the desktop should show for the member's own ask: the latest answer
     * for this period, or null when they have not asked.
     */
    private function requestStatus(Organization $organization, string $bucketId, string $period): ?string
    {
        $latest = UsageLimitResetRequest::query()
            ->where('organization_id', $organization->getKey())
            ->where('bucket_id', $bucketId)
            ->where('period', $period)
            ->latest('created_at')
            ->first();

        if (! $latest instanceof UsageLimitResetRequest) {
            return null;
        }

        return match ($latest->status) {
            UsageLimitResetRequest::STATUS_APPROVED => 'approved',
            UsageLimitResetRequest::STATUS_DENIED => 'denied',
            default => 'pending',
        };
    }
}
