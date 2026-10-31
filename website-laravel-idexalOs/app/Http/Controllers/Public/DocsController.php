<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Services\DocsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The documentation, served from the files the product ships.
 *
 * `/docs` is the home of the documentation (its first page), `/docs/{path}` is
 * one page, and `/docs/{path}.md` returns the source itself — the address the
 * docs' own configuration advertises to agents. The navigation, the order and
 * the titles all come from the authors' `docs.json`, so nothing here has to be
 * kept in step by hand.
 */
class DocsController extends Controller
{
    public function __construct(private readonly DocsService $docs) {}

    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));

        if ($query !== '') {
            return $this->search($query);
        }

        $slug = $this->docs->firstSlug();

        abort_if($slug === null, 404);

        return $this->article($slug);
    }

    public function show(Request $request, string $path = ''): View|Response
    {
        $wantsSource = str_ends_with($path, '.md') || str_ends_with($path, '.mdx');

        if ($wantsSource) {
            $source = $this->docs->raw($path);

            abort_if($source === null, 404);

            return response($source, 200, [
                'Content-Type' => 'text/markdown; charset=UTF-8',
                'X-Robots-Tag' => 'noindex',
            ]);
        }

        abort_if($path === '', 404);

        return $this->article($path);
    }

    /** The gateway API reference, which used to be the whole of `/docs`. */
    public function api(): View
    {
        $models = AiModel::query()
            ->with('provider')
            ->where('is_active', true)
            ->whereHas('provider', fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->limit(12)
            ->get();

        return view('public.api', [
            'baseUrl' => url('/v1'),
            'models' => $models,
            'appUrl' => url('/'),
        ]);
    }

    private function article(string $slug): View
    {
        $page = $this->docs->page($slug);

        abort_if($page === null, 404);

        return view('public.docs.show', [
            'page' => $page,
            'navigation' => $this->docs->navigation(),
            'pageCount' => count($this->docs->pages()),
            'searchQuery' => null,
            'results' => null,
        ]);
    }

    private function search(string $query): View
    {
        return view('public.docs.show', [
            'page' => null,
            'navigation' => $this->docs->navigation(),
            'pageCount' => count($this->docs->pages()),
            'searchQuery' => $query,
            'results' => $this->docs->search($query, 30),
        ]);
    }
}
