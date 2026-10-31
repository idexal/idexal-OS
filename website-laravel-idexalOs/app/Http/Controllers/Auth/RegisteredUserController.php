<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OnboardingService;
use App\Services\ReferralService;
use App\Services\SettingsService;
use App\Services\SocialLoginService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisteredUserController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboarding,
        private readonly SettingsService $settings,
        private readonly SocialLoginService $social,
        private readonly ReferralService $referrals,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->settings->boolean('registration_enabled', true)) {
            return redirect()->route('login')->withErrors(['email' => __('ui.errors.not_allowed')]);
        }

        return view('auth.register', [
            'socialProviders' => $this->social->enabledProviders(),
            'referralCode' => $this->referralCodeFrom($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->settings->boolean('registration_enabled', true)) {
            abort(403, __('ui.errors.not_allowed'));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'ref' => ['nullable', 'string', 'max:32'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => Str::lower($validated['email']),
                'password' => $validated['password'],
                'role' => User::ROLE_USER,
                'status' => User::STATUS_ACTIVE,
                'locale' => app()->getLocale(),
                'timezone' => config('app.timezone', 'UTC'),
            ]);

            $this->onboarding->createPersonalOrganization($user);

            return $user;
        });

        $this->referrals->attribute($user, $this->referralCodeFrom($request));
        $request->session()->forget('referral_code');

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        // A registration that started from the desktop's mandatory sign-in gate
        // finishes by connecting the desktop, not by landing on the dashboard
        // inside a sign-in window. When the operator requires every account to
        // hold a plan of its own, the new account is sent to the plan chooser
        // first; the billing page hands the desktop connection on once a plan
        // exists. The flag is read, not consumed here, so the chooser can still
        // see it.
        if ($request->session()->get('desktop_auth', false)) {
            return redirect()->route(
                // Choosing a plan is the default: a new desktop account must
                // hold a subscription (a free one counts) before the app opens,
                // so the plan chooser comes first and hands the desktop on
                // once a plan exists. An operator can turn this off in
                // /admin/features.
                $this->settings->boolean('feature_require_plan_selection', true)
                    ? 'dashboard.billing'
                    : 'desktop.authorize',
            );
        }

        if ($this->settings->boolean('require_email_verification', false)) {
            return redirect()->route('verification.notice');
        }

        return redirect()->route('dashboard.index')->with('status', __('ui.dashboard.welcome', ['name' => $user->name]));
    }

    /**
     * The referral code a visitor arrived with: the hidden form field first,
     * then `?ref=`, then whatever the referral link left in the session.
     */
    private function referralCodeFrom(Request $request): ?string
    {
        $candidates = [
            $request->input('ref'),
            $request->query('ref'),
            $request->session()->get('referral_code'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }
}
