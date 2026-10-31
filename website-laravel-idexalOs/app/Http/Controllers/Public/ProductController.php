<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * The products the platform is made of, one page each.
 *
 * The copy lives in the translation tables (`ui.products.items`) beside every
 * other page's copy, so both locales describe the same suite and a translated
 * page cannot exist for one language and not the other. Order is the authored
 * order of that array, which is the order the navigation shows.
 */
class ProductController extends Controller
{
    public function index(): View
    {
        return view('public.products.index', [
            'products' => $this->products(),
        ]);
    }

    public function show(string $slug): View
    {
        $products = $this->products();

        abort_unless(isset($products[$slug]), 404);

        $product = $products[$slug];

        return view('public.products.show', [
            'slug' => $slug,
            'product' => $product,
            // Only products that are still in the catalogue become links.
            'related' => array_values(array_filter(
                array_intersect_key($products, array_flip($product['related'] ?? [])),
                fn (array $candidate, string $key): bool => $key !== $slug,
                ARRAY_FILTER_USE_BOTH,
            )),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function products(): array
    {
        /** @var array<string, array<string, mixed>> $items */
        $items = __('ui.products.items');

        return is_array($items) ? $items : [];
    }
}
