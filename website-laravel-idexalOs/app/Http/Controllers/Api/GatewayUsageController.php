<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\UsageCounter;
use App\Models\UsageLimitResetRequest;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\OrganizationContext;
use App\Services\UsageAllowanceService;
use App\Services\UsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GatewayUsageController extends Controller
{
    public function __construct(
        private readonly UsageService $usage,
        private readonly OrganizationContext $context,
        private readonly UsageAllowanceService $allowances,
        private readonly NotificationService $notifications,
    ) {}

    public function show(): JsonResponse
    {
        $organization = $this->context->requireOrganization();
        $membership = $this->context->membership();
        $summary = $this->usage->summary($organization);
        $plan = $organization->currentPlan();

        $state = 'unlimited';

        if ($summary['limits']['requests'] !== null || $summary['limits']['tokens'] !== null) {
            $state = $summary['blocked']
                ? 'blocked'
                : ($summary['over_quota'] ? 'over_limit' : 'within_limit');
        }

        // A plan may grant a monthly spend allowance. When it does, the desktop
        // gets it as one `month` bucket it can show and, once exhausted, ask to
        // raise; when it does not, no bucket is sent rather than a zero one.
        $bucket = $this->allowances->bucketFor($organization);

        return response()->json([
            'serverTime' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'organizationId' => $organization->getKey(),
            'memberId' => $membership?->getKey() ?? $organization->owner_id,
            'state' => $state,
            'planUsage' => [
                'plan' => [
                    'name' => $plan?->name ?? 'Free',
                    'slug' => $plan?->slug ?? 'free',
                ],
                'requests' => [
                    'used' => $summary['requests'],
                    'limit' => $summary['limits']['requests'],
                ],
                'tokens' => [
                    'used' => $summary['total_tokens'],
                    'limit' => $summary['limits']['tokens'],
                ],
            ],
            'coverage' => [
                'complete' => true,
                'unpricedRequests' => 0,
                'captureEnabled' => true,
                'historicalCoverage' => 'tracked_since_epoch',
                'trackingStartedAt' => $organization->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            ],
            'buckets' => $bucket === null ? [] : [$bucket],
            'providers' => [],
        ]);
    }

    /**
     * A member asks an operator for more spend this period.
     *
     * The ask records one open row per member and bucket, tells the
     * organization's managers, and returns the request in the shape the desktop
     * parses — so the button it replaced becomes "waiting for an answer"
     * immediately, before the next usage read corroborates it.
     */
    public function storeResetRequest(Request $request): JsonResponse
    {
        $organization = $this->context->requireOrganization();
        $user = $request->user();

        $validated = $request->validate([
            'bucketId' => ['required', 'string', 'max:64'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $bucket = $this->allowances->bucketFor($organization);

        // A bucket the plan does not grant, or one that is not the member's own,
        // is not a request this platform can answer.
        if ($bucket === null || $bucket['id'] !== $validated['bucketId']) {
            return response()->json([
                'error' => [
                    'message' => __('ui.errors.not_found'),
                    'type' => 'invalid_request_error',
                    'code' => 'unknown_bucket',
                ],
            ], 404);
        }

        if ($bucket['usedMicroUsd'] < $bucket['allowanceMicroUsd']) {
            return response()->json([
                'error' => [
                    'message' => __('ui.admin.gateway.usage_requests.allowance_remaining'),
                    'type' => 'invalid_request_error',
                    'code' => 'allowance_remaining',
                ],
            ], 409);
        }

        $period = UsageCounter::currentPeriod();
        $alreadyOpen = UsageLimitResetRequest::openFor($organization->getKey(), $bucket['id'], (string) $user->getKey());

        $ask = UsageLimitResetRequest::ask(
            $organization->getKey(),
            $bucket['id'],
            $period,
            (string) $user->getKey(),
            (string) $validated['reason'],
        );

        if (! $alreadyOpen instanceof UsageLimitResetRequest) {
            $this->notifications->notifyManagers(
                $organization,
                'usage_limit_request',
                __('ui.admin.gateway.usage_requests.notification_title', ['member' => $user->name]),
                __('ui.admin.gateway.usage_requests.notification_body', [
                    'member' => $user->name ?? (string) $user->email,
                    'period' => $period,
                ]),
                route('admin.gateway'),
            );

            AuditLog::record('member.usage_limit_request.created', $organization, $ask, $user->email, [
                'bucket' => $bucket['id'],
                'period' => $period,
            ]);
        }

        return response()->json($this->payload($ask, $bucket, $user));
    }

    /**
     * One request in the shape the desktop parses.
     *
     * @param  array<string, mixed>  $bucket
     * @return array<string, mixed>
     */
    private function payload(UsageLimitResetRequest $ask, array $bucket, User $user): array
    {
        return [
            'id' => $ask->getKey(),
            'memberId' => (string) $ask->user_id,
            'memberName' => (string) $user->name,
            'memberEmail' => (string) $user->email,
            'bucketId' => (string) $ask->bucket_id,
            'timeframe' => (string) $bucket['timeframe'],
            'policyName' => (string) $bucket['policyName'],
            'reason' => (string) $ask->reason,
            'status' => in_array($ask->status, ['pending', 'approved', 'denied', 'expired'], true) ? $ask->status : 'pending',
            'createdAt' => $ask->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'reviewedBy' => $ask->reviewed_by,
            'reviewedAt' => $ask->reviewed_at?->toIso8601String(),
            'baseAllowanceMicroUsd' => (int) $bucket['baseAllowanceMicroUsd'],
            'allowanceMicroUsd' => (int) $bucket['allowanceMicroUsd'],
            'usedMicroUsd' => (int) $bucket['usedMicroUsd'],
            'resetAt' => (string) $bucket['resetAt'],
        ];
    }
}
