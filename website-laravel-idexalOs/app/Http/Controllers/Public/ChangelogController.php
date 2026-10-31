<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\ReleaseNotesService;
use Illuminate\Contracts\View\View;

/**
 * The public record of every release, in the visitor's language. The list
 * itself lives in ReleaseNotesService so the resources page reads the same one.
 */
class ChangelogController extends Controller
{
    public function __construct(private readonly ReleaseNotesService $releases) {}

    public function index(): View
    {
        $releases = $this->releases->all();

        return view('public.changelog', [
            'releases' => $releases,
            'current' => $releases[0] ?? null,
        ]);
    }
}
