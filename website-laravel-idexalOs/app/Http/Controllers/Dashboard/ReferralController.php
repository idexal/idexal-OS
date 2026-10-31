<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\OrganizationContext;
use App\Services\ReferralService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The customer's own view of the referral programme: their link, their stats,
 * and everyone they have invited.
 */
class ReferralController extends Controller
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly ReferralService $referrals,
    ) {}

    public function index(Request $request): View
    {
        $organization = $this->context->requireOrganization();
        $user = $request->user();

        return view('dashboard.referrals', [
            'organization' => $organization,
            'enabled' => $this->referrals->enabled(),
            'code' => $this->referrals->enabled() ? $this->referrals->codeFor($user) : null,
            'link' => $this->referrals->enabled() ? $this->referrals->linkFor($user) : null,
            'stats' => $this->referrals->stats($user),
            'invites' => $this->referrals->recentFor($user),
            'rewardAmount' => $this->referrals->rewardAmount(),
            'refereeBonus' => $this->referrals->refereeBonus(),
            'currency' => $this->referrals->currency(),
        ]);
    }
}
