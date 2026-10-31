<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request, string $locale): RedirectResponse
    {
        if (! in_array($locale, supported_locales(), true)) {
            abort(404);
        }

        $request->session()->put('locale', $locale);

        if ($request->user() !== null) {
            $request->user()->forceFill(['locale' => $locale])->save();
        }

        return redirect()->back(302, [], route('home'));
    }
}
