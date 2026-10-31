<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * The public referral link (`/r/<code>`). It remembers the code on the session
 * and in a cookie and then sends the visitor to sign-up, where the code is
 * attached to the new account.
 */
class ReferralLinkController extends Controller
{
    public function __construct(private readonly ReferralService $referrals) {}

    public function track(Request $request, string $code): RedirectResponse
    {
        if ($this->referrals->enabled() && $this->referrals->referrerForCode($code) instanceof User) {
            $request->session()->put('referral_code', $code);
            Cookie::queue('referral_code', $code, $this->referrals->attributionDays() * 1440);
        }

        return redirect()->route('register');
    }
}
