<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\UsageCounter;
use App\Models\UsageLimitResetRequest;
use App\Services\NotificationService;
use App\Services\UsageAllowanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The operator's queue for members who asked for more spend this period.
 *
 * Approving sets the extension for the request's own period — never a later one
 * — and the usage bucket the desktop reads for that period is the plan's base
 * plus every approved extension. Denying closes the ask and tells the member;
 * either way the member may ask again next period.
 */
class AdminUsageRequestController extends Controller
{
    public function __construct(
        private readonly UsageAllowanceService $allowances,
        private readonly NotificationService $notifications,
    ) {}

    public function approve(Request $request, UsageLimitResetRequest $usageRequest): RedirectResponse
    {
        $validated = $request->validate([
            'extension_usd' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
        ]);

        abort_unless($usageRequest->status === UsageLimitResetRequest::STATUS_PENDING, 404);

        $usageRequest->forceFill([
            'status' => UsageLimitResetRequest::STATUS_APPROVED,
            'extension_micro_usd' => (int) round((float) $validated['extension_usd'] * 1_000_000),
            'reviewed_by' => $request->user()->getKey(),
            'reviewed_at' => now(),
        ])->save();

        AuditLog::record('admin.usage_request.approved', $usageRequest->organization, $usageRequest, null, [
            'bucket' => $usageRequest->bucket_id,
            'period' => $usageRequest->period,
            'extension_usd' => (float) $validated['extension_usd'],
        ]);

        $this->notifyMember($usageRequest, true, (float) $validated['extension_usd']);

        return back()->with('status', __('ui.admin.gateway.usage_requests.approved'));
    }

    public function deny(Request $request, UsageLimitResetRequest $usageRequest): RedirectResponse
    {
        abort_unless($usageRequest->status === UsageLimitResetRequest::STATUS_PENDING, 404);

        $usageRequest->forceFill([
            'status' => UsageLimitResetRequest::STATUS_DENIED,
            'extension_micro_usd' => null,
            'reviewed_by' => $request->user()->getKey(),
            'reviewed_at' => now(),
        ])->save();

        AuditLog::record('admin.usage_request.denied', $usageRequest->organization, $usageRequest, null, [
            'bucket' => $usageRequest->bucket_id,
            'period' => $usageRequest->period,
        ]);

        $this->notifyMember($usageRequest, false, 0.0);

        return back()->with('status', __('ui.admin.gateway.usage_requests.denied'));
    }

    private function notifyMember(UsageLimitResetRequest $usageRequest, bool $approved, float $extensionUsd): void
    {
        $member = $usageRequest->member;
        $organization = $usageRequest->organization;

        if ($member === null || $organization === null) {
            return;
        }

        $this->notifications->send(
            $member,
            $approved ? 'usage_limit_request_approved' : 'usage_limit_request_denied',
            __($approved
                ? 'ui.admin.gateway.usage_requests.member_approved_title'
                : 'ui.admin.gateway.usage_requests.member_denied_title'),
            __($approved
                ? 'ui.admin.gateway.usage_requests.member_approved_body'
                : 'ui.admin.gateway.usage_requests.member_denied_body', [
                    'amount' => '$'.number_format($extensionUsd, 2),
                    'period' => $usageRequest->period,
                ]),
            route('dashboard.usage'),
            $organization,
        );
    }

    /**
     * The pending asks for the operator's queue, newest first, with the period
     * each one concerns.
     *
     * @return list<array<string, mixed>>
     */
    public function pending(): array
    {
        return UsageLimitResetRequest::query()
            ->with(['member:id,name,email', 'organization:id,name,slug'])
            ->where('status', UsageLimitResetRequest::STATUS_PENDING)
            ->orderBy('created_at')
            ->limit(50)
            ->get()
            ->map(function (UsageLimitResetRequest $ask): array {
                $organization = $ask->organization;

                return [
                    'id' => $ask->getKey(),
                    'member' => $ask->member?->name ?? $ask->member?->email ?? '—',
                    'memberEmail' => (string) $ask->member?->email,
                    'organization' => $organization?->name ?? '—',
                    'organizationId' => (string) $ask->organization_id,
                    'bucket' => (string) $ask->bucket_id,
                    'period' => (string) $ask->period,
                    'reason' => (string) $ask->reason,
                    'askedAt' => $ask->created_at?->translatedFormat('d M H:i'),
                    'currentAllowanceUsd' => $organization instanceof Organization
                        ? $this->currentAllowanceUsd($organization)
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * What the organization's bucket allows and has used this period, so the
     * operator decides with the numbers in front of them.
     */
    private function currentAllowanceUsd(Organization $organization): ?array
    {
        $bucket = $this->allowances->bucketFor($organization);

        if ($bucket === null) {
            return null;
        }

        return [
            'allowance' => $bucket['allowanceMicroUsd'] / 1_000_000,
            'used' => $bucket['usedMicroUsd'] / 1_000_000,
        ];
    }

    /** The period a new request would concern, for the queue's context. */
    public function currentPeriod(): string
    {
        return UsageCounter::currentPeriod();
    }
}
