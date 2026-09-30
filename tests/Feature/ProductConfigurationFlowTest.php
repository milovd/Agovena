<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Enums\ProductOptionType;
use App\Livewire\Storefront\ProductConfigure;
use App\Livewire\Storefront\ProductShow;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionChoice;
use Livewire\Livewire;

function makeConfigurableStorefrontProduct(): Product
{
    $product = Product::factory()->active()->create([
        'name' => 'Configurable VPS',
        'slug' => 'configurable-vps',
        'price_amount' => 1000,
        'currency' => 'EUR',
    ]);

    $option = ProductOption::query()->create([
        'product_id' => $product->id,
        'key' => 'location',
        'label' => 'Location',
        'type' => ProductOptionType::Select,
        'is_required' => true,
        'is_active' => true,
        'sort' => 1,
        'price_adjustment_amount' => 0,
        'constraints' => [],
    ]);

    ProductOptionChoice::query()->create([
        'product_option_id' => $option->id,
        'value' => 'ams',
        'label' => 'Amsterdam',
        'price_adjustment_amount' => 0,
        'sort' => 1,
        'is_active' => true,
    ]);

    return $product->fresh(['purchaseOptions.choices']);
}

test('product detail pages keep configurable options out of the product view', function (): void {
    $product = makeConfigurableStorefrontProduct();

    $this->get(route('storefront.product', $product->slug))
        ->assertOk()
        ->assertSee(__('storefront.product.add_to_cart'), false)
        ->assertDontSee('Location', false)
        ->assertDontSee('optionSelections', false);

    Livewire::test(ProductShow::class, ['slug' => $product->slug])
        ->call('addToCart')
        ->assertRedirect(route('storefront.product.configure', [
            'slug' => $product->slug,
            'intent' => 'cart',
            'quantity' => 1,
        ]));
});

test('configuration page validates options before adding the configured product to cart', function (): void {
    $product = makeConfigurableStorefrontProduct();

    $this->get(route('storefront.product.configure', $product->slug))
        ->assertOk()
        ->assertSee(__('storefront.product.configure_title', ['product' => $product->name]), false)
        ->assertSee('Location', false);

    Livewire::test(ProductConfigure::class, ['slug' => $product->slug])
        ->set('optionSelections.location', 'ams')
        ->call('addToCart')
        ->assertRedirect(route('storefront.cart'));

    $line = app(CartService::class)->lines()[0];
    expect($line->selections)->toBe(['location' => 'ams']);
});
