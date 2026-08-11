<?php

use App\Agovena\Cart\CartService;
use App\Agovena\Settings\SettingsRepository;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('storefront chrome includes navigation cart and footer placeholders', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Shop', false)
        ->assertSee('Cart', false)
        ->assertSee('Account', false)
        ->assertSee('Search', false)
        ->assertSee('Explore', false)
        ->assertSee('Legal', false)
        ->assertSee('Terms', false)
        ->assertDontSee('Powered by a default Theme', false)
        ->assertDontSee('Agovena Admin', false);
});

test('storefront header uses branding logo when configured', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('logo.png')->store('branding', 'public');

    app(SettingsRepository::class)->set('branding', 'logo_path', $path);

    $this->get('/')
        ->assertOk()
        ->assertSee('store-brand__logo', false)
        ->assertSee(Storage::disk('public')->url($path), false);
});

test('catalog search filters products by name', function () {
    Product::factory()->active()->create(['name' => 'Alpine Jacket']);
    Product::factory()->active()->create(['name' => 'Desert Boots']);

    $this->get('/?q=Jacket')
        ->assertOk()
        ->assertSee('Alpine Jacket', false)
        ->assertSee('Results for', false)
        ->assertDontSee('Desert Boots', false);
});

test('cart item count sums quantities', function () {
    $product = Product::factory()->active()->create();

    /** @var CartService $cart */
    $cart = app(CartService::class);
    $cart->add($product->id, 2);
    $cart->add($product->id, 1);

    expect($cart->itemCount())->toBe(3);

    $this->withSession(['agovena.cart' => [$product->id => 3]])
        ->get('/')
        ->assertOk()
        ->assertSee('aria-label="3 items in cart"', false);
});
