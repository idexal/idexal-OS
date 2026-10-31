<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Referral;
use App\Services\ReferralService;
use App\Services\SettingsService;
use App\Support\DesktopSync;
use App\Support\SettingsChanges;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * One page that prices the referral programme, switches it on and off, and
 * lists every referral with the actions an operator needs: approve a qualified
 * invite, pay a reward by hand, or reject a referral that should never pay out.
 */
class AdminReferralController extends Controller
{
    /**
     * @var list<string>
     */
    private const STATUSES = [
        Referral::STATUS_PENDING,
        Referral::STATUS_QUALIFIED,
        Referral::STATUS_REWARDED,
        Referral::STATUS_REJECTED,
    ];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly ReferralService $referrals,
    ) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');

        $query = Referral::query()->with(['referrer', 'referred'])->latest('created_at');

        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        return view('admin.referrals', [
            'values' => [
                'referral_enabled' => $this->settings->boolean('referral_enabled', true),
                'referral_reward_amount' => $this->settings->get('referral_reward_amount', 0),
                'referral_reward_currency' => $this->referrals->currency(),
                'referral_referee_bonus' => $this->referrals->refereeBonus(),
                'referral_auto_reward' => $this->settings->boolean('referral_auto_reward', true),
                'referral_cookie_days' => $this->referrals->attributionDays(),
            ],
            'referrals' => $query->paginate(25)->withQueryString(),
            'status' => $status,
            'statuses' => self::STATUSES,
            'totals' => $this->referrals->totals(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'referral_enabled' => ['nullable', 'boolean'],
            'referral_reward_amount' => ['required', 'numeric', 'min:0', 'max:100000'],
            'referral_reward_currency' => ['required', 'string', 'size:3', 'alpha'],
            'referral_referee_bonus' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'referral_auto_reward' => ['nullable', 'boolean'],
            'referral_cookie_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $values = [
            'referral_enabled' => $request->boolean('referral_enabled'),
            'referral_reward_amount' => (float) $validated['referral_reward_amount'],
            'referral_reward_currency' => strtoupper($validated['referral_reward_currency']),
            'referral_referee_bonus' => (float) ($validated['referral_referee_bonus'] ?? 0),
            'referral_auto_reward' => $request->boolean('referral_auto_reward'),
            'referral_cookie_days' => (int) $validated['referral_cookie_days'],
        ];

        $before = SettingsChanges::snapshot(array_keys($values));

        $this->settings->setMany($values, 'referrals');

        $changes = SettingsChanges::between($before, $values);

        if ($changes === []) {
            return redirect()->route('admin.referrals')->with('status', __('ui.common.saved'));
        }

        AuditLog::record('admin.referrals.updated', null, null, __('ui.admin.referrals.saved_log'), ['settings' => $changes]);
        DesktopSync::announce('referrals_updated');

        return redirect()->route('admin.referrals')->with('status', __('ui.common.saved'));
    }

    public function updateReferral(Request $request, Referral $referral): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['qualify', 'reward', 'reject'])],
            'reason' => ['nullable', 'string', 'max:190'],
        ]);

        switch ($validated['action']) {
            case 'qualify':
                if ($referral->isPending()) {
                    $this->referrals->qualify($referral);
                }
                break;

            case 'reward':
                if ($referral->isRewarded() || $referral->status === Referral::STATUS_REJECTED) {
                    return back()->withErrors(['action' => __('ui.admin.referrals.not_rewardable')]);
                }
                $this->referrals->reward($referral, $request->user());
                break;

            case 'reject':
                $this->referrals->reject($referral, $request->user(), $validated['reason'] ?? null);
                break;
        }

        return redirect()
            ->route('admin.referrals', array_filter($request->only('status')))
            ->with('status', __('ui.admin.referrals.updated'));
    }
}
