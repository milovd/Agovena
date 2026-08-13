<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Agovena\Catalog\ListStorefrontCategories;
use App\Agovena\Catalog\SuggestStorefrontProducts;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CatalogController
{
    public function products(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()
            ->active()
            ->with(['category', 'images', 'capabilities', 'purchaseOptions.choices'])
            ->orderBy('name');

        $search = trim((string) $request->query('q', ''));
        if (mb_strlen($search) >= 2) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        $category = trim((string) $request->query('category', ''));
        if ($category !== '') {
            $query->whereHas('category', function ($builder) use ($category): void {
                $builder->where('slug', $category)->orWhere('id', $category);
            });
        }

        return ProductResource::collection($query->paginate(24));
    }

    public function product(string $slug): ProductResource
    {
        $product = Product::query()
            ->active()
            ->where('slug', $slug)
            ->with(['category', 'images', 'capabilities', 'purchaseOptions.choices'])
            ->firstOrFail();

        return new ProductResource($product);
    }

    public function categories(ListStorefrontCategories $list): AnonymousResourceCollection
    {
        return CategoryResource::collection($list->handle());
    }

    public function category(string $slug): JsonResponse
    {
        $category = Category::query()
            ->where('is_active', true)
            ->where('slug', $slug)
            ->withCount(['products' => fn ($q) => $q->active()])
            ->firstOrFail();

        $products = Product::query()
            ->active()
            ->where('category_id', $category->id)
            ->with(['category', 'images', 'capabilities', 'purchaseOptions.choices'])
            ->orderBy('name')
            ->paginate(24);

        return response()->json([
            'data' => [
                'category' => (new CategoryResource($category))->resolve(),
                'products' => ProductResource::collection($products)->resolve(),
            ],
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function search(Request $request, SuggestStorefrontProducts $suggest): AnonymousResourceCollection
    {
        return ProductResource::collection($suggest->handle((string) $request->query('q', ''), 8));
    }
}
