<?php

use App\Agovena\Cart\CartService;
use App\Agovena\Settings\SettingsRepository;
use App\Livewire\Storefront\CartPage;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('storefront chrome includes navigation cart and footer structure', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Shop', false)
        ->assertSee('Cart', false)
        ->assertSee('Log in', false)
        ->assertSee('Register', false)
        ->assertSee('Search', false)
        ->assertSee(__('storefront.footer.shop'), false)
        ->assertSee(__('storefront.footer.account'), false)
        ->assertSee('store-footer__brand', false)
        ->assertSee('store-footer__logo', false)
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
        ->assertSee('/storage/'.$path, false);
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
        ->assertSee('aria-label="Cart, 3 items"', false);
});

test('cart prunes deleted products and clears badge', function () {
    $product = Product::factory()->active()->create();
    $goneId = $product->id + 9000;

    $this->withSession(['agovena.cart' => [$goneId => 1]])
        ->get('/cart')
        ->assertOk()
        ->assertSee('Your cart is empty', false)
        ->assertSee('Unavailable items were removed from your cart.', false);

    expect(session('agovena.cart', []))->toBe([]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('aria-label="Cart, 1 items"', false);
});

test('missing merchant logo falls back to the bundled storefront mark', function () {
    app(SettingsRepository::class)->set('branding', 'logo_path', 'branding/missing-logo.png');

    $this->get('/')
        ->assertOk()
        ->assertSee('store-brand__logo', false)
        ->assertSee('vendor/agovena/logo.png', false)
        ->assertDontSee('storage/branding/missing-logo.png', false);
});

test('cart lines show a placeholder when the product image file is missing', function () {
    $product = Product::factory()->active()->create([
        'name' => 'Nova Phone 14',
        'image_path' => 'products/missing.png',
    ]);
    app(CartService::class)->add($product->id, 1);

    $this->get('/cart')
        ->assertOk()
        ->assertSee('Nova Phone 14', false)
        ->assertSee('store-cart-line__media', false)
        ->assertSee('store-product-card__placeholder', false)
        ->assertDontSee('storage/products/missing.png', false)
        ->assertDontSee(__('common.update'), false);
});

test('long names and zero prices still render on the product page', function () {
    $name = str_repeat('Aurora Lantern ', 12).'XL';
    $product = Product::factory()->active()->create([
        'name' => $name,
        'price_amount' => 0,
    ]);

    $this->get(route('storefront.product', $product->slug))
        ->assertOk()
        ->assertSee($name, false)
        ->assertDontSee('SQLSTATE', false);
});

test('checkout keeps the storefront header and bundled logo', function () {
    $product = Product::factory()->active()->create(['price_amount' => 1500]);
    app(CartService::class)->add($product->id, 1);

    $this->get('/checkout')
        ->assertOk()
        ->assertSee('store-chrome', false)
        ->assertDontSee('store-chrome--reduced', false)
        ->assertSee('store-header__search', false)
        ->assertSee('store-header__cart', false)
        ->assertSee('store-footer', false)
        ->assertSee('store-footer__brand', false)
        ->assertSee('vendor/agovena/logo.png', false)
        ->assertDontSee(__('storefront.checkout.back_to_cart'), false)
        ->assertDontSee('store-checkout-chrome', false)
        ->assertDontSee(__('storefront.checkout.steps.review'), false);
});

test('cart quantity stepper updates the line without a separate update action', function () {
    $product = Product::factory()->active()->create(['name' => 'Nova Phone 14']);
    app(CartService::class)->add($product->id, 1);

    $component = Livewire::test(CartPage::class);
    $lineKey = array_key_first($component->get('quantities'));

    expect($lineKey)->not->toBeNull();

    $component
        ->call('incrementLine', $lineKey)
        ->assertSet('quantities.'.$lineKey, 2)
        ->call('decrementLine', $lineKey)
        ->assertSet('quantities.'.$lineKey, 1)
        ->assertDontSee(__('common.update'), false);
});
