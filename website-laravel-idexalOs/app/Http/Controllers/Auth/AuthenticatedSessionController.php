<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SocialLoginService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly SocialLoginService $social) {}

    public function create(): View
    {
        return view('auth.login', [
            'desktopAuth' => (bool) session('desktop_auth', false),
            'socialProviders' => $this->social->enabledProviders(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('ui.auth.invalid_credentials'),
            ]);
        }

        if ($request->user()?->isSuspended()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => __('ui.auth.suspended'),
            ]);
        }

        $request->session()->regenerate();
        $request->user()?->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        if ($request->session()->pull('desktop_auth', false)) {
            return redirect()->route('desktop.authorize');
        }

        return redirect()->intended(route('dashboard.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
