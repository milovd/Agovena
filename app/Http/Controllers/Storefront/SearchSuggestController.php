<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Agovena\Catalog\SuggestStorefrontProducts;
use App\Agovena\Media\ProductMedia;
use App\Support\MoneyFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SearchSuggestController
{
    public function __invoke(Request $request, SuggestStorefrontProducts $suggest): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $products = $suggest->handle($query);

        $items = $products->map(function ($product): array {
            return [
                'name' => $product->name,
                'slug' => $product->slug,
                'url' => route('storefront.product', $product->slug),
                'price' => MoneyFormatter::format($product->price_amount, $product->currency),
                'image' => ProductMedia::primaryUrl($product),
                'category' => $product->category?->name,
            ];
        })->values();

        return response()->json([
            'query' => trim($query),
            'items' => $items,
            'results_url' => route('storefront.home', ['q' => trim($query)]),
        ]);
    }
}
