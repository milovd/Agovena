<?php

declare(strict_types=1);

use App\Agovena\Catalog\ListStorefrontProducts;
use App\Agovena\Media\ProductMedia;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

test('storefront product listings eager-load images instead of querying per card', function () {
    $products = Product::factory()->active()->count(6)->create();
    foreach ($products as $index => $product) {
        $product->images()->create([
            'path' => 'products/demo-'.$index.'.jpg',
            'sort' => 0,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $listed = app(ListStorefrontProducts::class)->handle(limit: 6);
    foreach ($listed as $product) {
        ProductMedia::primaryUrl($product);
    }

    $imageQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains(strtolower($query), 'product_images'));

    expect($listed)->toHaveCount(6)
        ->and($imageQueries->count())->toBe(1);
});

test('category catalog paginates instead of loading the whole table', function () {
    $category = Category::factory()->create(['name' => 'Lamps', 'slug' => 'lamps-catalog']);
    Product::factory()->active()->count(30)->create(['category_id' => $category->id]);

    $page = $this->get(route('storefront.category', $category->slug))
        ->assertOk();

    $page->assertSee(__('storefront.catalog.pagination'), false)
        ->assertSee(__('storefront.catalog.next'), false);
    expect(substr_count($page->getContent(), '<li class="store-product-card">'))->toBe(24);
});
