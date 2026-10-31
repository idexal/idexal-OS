<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\SocialLoginDeniedException;
use App\Http\Controllers\Controller;
use App\Services\SocialLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * The social sign-in round trip. A visitor is sent to the provider, comes back
 * with a profile, and is signed in as the idexal Os user that profile belongs
 * to — created on first sign-in unless registration is switched off.
 */
class SocialAuthController extends Controller
{
    public function __construct(private readonly SocialLoginService $social) {}

    public function redirect(string $provider): RedirectResponse
    {
        abort_unless($this->social->enabled($provider), 404);

        return $this->social->driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless($this->social->enabled($provider), 404);

        try {
            $socialUser = $this->social->driver($provider)->user();
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed();
        }

        try {
            $referralCode = $request->session()->get('referral_code');
            $user = $this->social->resolveUser(
                $provider,
                $socialUser,
                is_string($referralCode) ? $referralCode : null,
            );
        } catch (SocialLoginDeniedException $exception) {
            return redirect()->route('login')->withErrors(['social' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed();
        }

        if ($user->trashed() || $user->isSuspended()) {
            return redirect()->route('login')->withErrors(['email' => __('ui.auth.suspended')]);
        }

        Auth::guard('web')->login($user, true);

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        if ($request->session()->pull('desktop_auth', false)) {
            return redirect()->route('desktop.authorize');
        }

        return redirect()->intended(route('dashboard.index'));
    }

    private function failed(): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['social' => __('ui.errors.social_login_failed')]);
    }
}
