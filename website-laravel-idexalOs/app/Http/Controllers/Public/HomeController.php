<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->boolean('desktopAuth')) {
            $request->session()->put('desktop_auth', true);
            $request->session()->put('desktop_scheme', (string) $request->query('desktopScheme', config('idexal.desktop.scheme')));

            // The desktop can show these pages inside its own window instead of
            // handing off to a browser. When it does, the completion page
            // navigates to the idexalos:// link itself so the user does not
            // have to click anything; a browser sign-in never sets this, so
            // that flow is unchanged. The flag always states the current flow,
            // so it is cleared when this entry is not an embedded one.
            if ($request->boolean('desktopEmbed')) {
                $request->session()->put('desktop_embed', true);
            } else {
                $request->session()->forget('desktop_embed');
            }

            return $request->user()
                ? redirect()->route('desktop.authorize')
                : redirect()->route('login');
        }

        if ($request->query('mode') === 'sign-in') {
            return redirect()->route('login');
        }

        if ($request->query('mode') === 'sign-up') {
            return redirect()->route('register');
        }

        $plans = Plan::query()
            ->where('is_public', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('public.home', [
            'plans' => $plans,
            'yearly' => false,
        ]);
    }
}
