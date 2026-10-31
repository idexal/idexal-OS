<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * What people do with the product, by the role doing the work and by the job to
 * be done.
 *
 * The copy lives in the translation tables (`ui.use_cases.items`) with every
 * other page's, and the product each use case runs on is a slug from
 * `ui.products.items` so the two sections stay linked to the same catalogue.
 */
class UseCaseController extends Controller
{
    public function index(): View
    {
        $useCases = $this->useCases();

        return view('public.use-cases.index', [
            'groups' => [
                'roles' => array_filter($useCases, fn (array $useCase): bool => ($useCase['group'] ?? 'roles') === 'roles'),
                'tasks' => array_filter($useCases, fn (array $useCase): bool => ($useCase['group'] ?? 'roles') === 'tasks'),
            ],
        ]);
    }

    public function show(string $slug): View
    {
        $useCases = $this->useCases();

        abort_unless(isset($useCases[$slug]), 404);

        $useCase = $useCases[$slug];
        $products = __('ui.products.items');
        $products = is_array($products) ? $products : [];

        return view('public.use-cases.show', [
            'slug' => $slug,
            'useCase' => $useCase,
            // A use case names the products it runs on; a product that is not in
            // the catalogue is dropped rather than linked to a 404.
            'products' => array_filter(
                array_intersect_key($products, array_flip($useCase['products'] ?? [])),
            ),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function useCases(): array
    {
        /** @var array<string, array<string, mixed>> $items */
        $items = __('ui.use_cases.items');

        return is_array($items) ? $items : [];
    }
}
