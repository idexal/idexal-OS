<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class LegalController extends Controller
{
    public function terms(): View
    {
        return view('public.legal', [
            'title' => __('ui.legal.terms_title'),
            'intro' => __('ui.legal.terms_intro'),
            'sections' => __('ui.legal.terms_sections'),
        ]);
    }

    public function privacy(): View
    {
        return view('public.legal', [
            'title' => __('ui.legal.privacy_title'),
            'intro' => __('ui.legal.privacy_intro'),
            'sections' => __('ui.legal.privacy_sections'),
        ]);
    }
}
