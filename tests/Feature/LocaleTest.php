<?php

use App\Agovena\Settings\SettingsRepository;
use App\Models\Category;
use App\Models\Product;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('settings locale applies site-wide to storefront and admin', function () {
    app(SettingsRepository::class)->set('general', 'locale', 'nl');

    $this->get('/')
        ->assertOk()
        ->assertSee(__('storefront.nav.cart', [], 'nl'), false)
        ->assertSee(__('storefront.skip_to_content', [], 'nl'), false);

    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee(__('admin.users.title', [], 'nl'), false)
        ->assertSee(__('admin.nav.roles', [], 'nl'), false);
});

test('storefront catalog and product chrome follow the site locale', function () {
    app(SettingsRepository::class)->set('general', 'locale', 'nl');

    $category = Category::factory()->create(['slug' => 'gadgets', 'is_active' => true]);
    $product = Product::factory()->active()->create([
        'slug' => 'locale-gadget',
        'category_id' => $category->id,
        'show_specifications' => true,
        'specifications' => [['label' => 'Kleur', 'value' => 'Zwart']],
    ]);

    $this->get(route('storefront.category', $category->slug))
        ->assertOk()
        ->assertSee(__('storefront.nav.home', [], 'nl'), false)
        ->assertSee(__('storefront.catalog.apply', [], 'nl'), false)
        ->assertSee(__('storefront.catalog.sort_price_asc', [], 'nl'), false);

    $this->get(route('storefront.product', $product->slug))
        ->assertOk()
        ->assertSee(__('storefront.product.delivery_title', [], 'nl'), false)
        ->assertSee(__('storefront.product.tab_reviews', [], 'nl'), false)
        ->assertSee(__('storefront.product.spec_specifications', [], 'nl'), false)
        ->assertSee(trans_choice('storefront.product.view_reviews', 0, ['count' => 0], 'nl'), false);
});
