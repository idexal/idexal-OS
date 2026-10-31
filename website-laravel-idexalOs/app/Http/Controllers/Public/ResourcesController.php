<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\ReleaseNotesService;
use Illuminate\Contracts\View\View;

/**
 * The hub for everything that is not a product page: the documentation, the
 * model catalogue, the release record, the security posture and the ways to
 * reach a person.
 */
class ResourcesController extends Controller
{
    public function __construct(private readonly ReleaseNotesService $releases) {}

    public function index(): View
    {
        return view('public.resources', [
            'releases' => $this->releases->recent(5),
        ]);
    }
}
