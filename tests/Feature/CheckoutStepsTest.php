<?php

declare(strict_types=1);

use Agovena\Modules\Shipping\Enums\ShippingMethodType;
use Agovena\Modules\Shipping\Models\ShippingMethod;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\CheckoutStep;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Enums\ProductOptionType;
use App\Livewire\Storefront\CheckoutPage;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionChoice;
use Livewire\Livewire;

function checkoutEnableShipping(): void
{
    app(ModuleManager::class)->enable('shipping');
    app(SyncRegisteredPermissions::class)(force: true);
    ShippingMethod::query()->create([
        'name' => 'Standard',
        'code' => 'standard-'.uniqid(),
        'type' => ShippingMethodType::Flat,
        'config' => ['amount' => 495],
        'currency' => 'EUR',
        'is_active' => true,
        'sort' => 1,
    ]);
}

test('digital checkout omits delivery from the progress indicator', function () {
    $product = Product::factory()->active()->create(['price_amount' => 1200]);
    app(CartService::class)->add($product->id, 1);

    Livewire::test(CheckoutPage::class)
        ->assertSee(__('storefront.checkout.steps.details'))
        ->assertSee(__('storefront.checkout.steps.payment'))
        ->assertDontSee(__('storefront.checkout.steps.review'), false)
        ->assertDontSee(__('storefront.checkout.steps.delivery'), false)
        ->assertSet('step', CheckoutStep::Details->value);
});

test('checkout continues from details to payment for a digital cart', function () {
    $product = Product::factory()->active()->create(['price_amount' => 800]);
    app(CartService::class)->add($product->id, 1);

    Livewire::test(CheckoutPage::class)
        ->set('customer_name', 'Ada Guest')
        ->set('customer_email', 'ada@example.com')
        ->set('billing_name', 'Ada Guest')
        ->set('billing_line1', 'Keizersgracht 1')
        ->set('billing_city', 'Amsterdam')
        ->set('billing_postal_code', '1015 CJ')
        ->set('billing_country', 'NL')
        ->call('continueStep')
        ->assertSet('step', CheckoutStep::Payment->value)
        ->assertSee(__('storefront.checkout.hosted_payment_note'))
        ->assertSee(__('storefront.checkout.place_order'))
        ->assertDontSee(__('storefront.checkout.steps.review'), false);
});

test('physical checkout includes delivery and keeps totals on the server', function () {
    checkoutEnableShipping();
    $product = Product::factory()->active()->create(['price_amount' => 2500]);
    app(ProductCapabilityManager::class)->enable($product, 'physical');
    app(ProductCapabilityManager::class)->enable($product, 'shippable', ['weight_grams' => 400]);
    app(CartService::class)->add($product->id, 1);

    $component = Livewire::test(CheckoutPage::class)
        ->assertSee(__('storefront.checkout.steps.delivery'))
        ->set('customer_name', 'Ship Buyer')
        ->set('customer_email', 'ship@example.com')
        ->set('billing_name', 'Ship Buyer')
        ->set('billing_line1', 'Kalverstraat 12')
        ->set('billing_city', 'Amsterdam')
        ->set('billing_postal_code', '1012 PH')
        ->set('billing_country', 'NL')
        ->call('continueStep')
        ->assertSet('step', CheckoutStep::Delivery->value)
        ->assertSee('Standard');

    expect($component->get('shipping_quote_key'))->toStartWith('method:');
});

test('mixed carts combine delivery and configuration into fulfillment', function () {
    checkoutEnableShipping();
    $shirt = Product::factory()->active()->create(['name' => 'Shirt', 'price_amount' => 2000]);
    app(ProductCapabilityManager::class)->enable($shirt, 'physical');
    app(ProductCapabilityManager::class)->enable($shirt, 'shippable');

    $vps = Product::factory()->active()->create(['name' => 'VPS', 'price_amount' => 5000]);
    $option = ProductOption::query()->create([
        'product_id' => $vps->id,
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

    $cart = app(CartService::class);
    $cart->add($shirt->id, 1);
    $cart->add($vps->id, 1, ['location' => 'ams']);

    Livewire::test(CheckoutPage::class)
        ->assertSee(__('storefront.checkout.steps.fulfillment'))
        ->assertDontSee(__('storefront.checkout.steps.review'), false);
});

test('configurable checkout continues from configure without extra fields', function () {
    $vps = Product::factory()->active()->create(['name' => 'VPS', 'price_amount' => 4000]);
    $option = ProductOption::query()->create([
        'product_id' => $vps->id,
        'key' => 'os',
        'label' => 'Operating system',
        'type' => ProductOptionType::Select,
        'is_required' => true,
        'is_active' => true,
        'sort' => 1,
        'price_adjustment_amount' => 0,
        'constraints' => [],
    ]);
    ProductOptionChoice::query()->create([
        'product_option_id' => $option->id,
        'value' => 'ubuntu',
        'label' => 'Ubuntu',
        'price_adjustment_amount' => 0,
        'sort' => 1,
        'is_active' => true,
    ]);

    app(CartService::class)->add($vps->id, 1, ['os' => 'ubuntu']);

    Livewire::test(CheckoutPage::class)
        ->assertSee(__('storefront.checkout.steps.configuration'))
        ->set('customer_name', 'Config Buyer')
        ->set('customer_email', 'config@example.com')
        ->set('billing_name', 'Config Buyer')
        ->set('billing_line1', 'Dam 1')
        ->set('billing_city', 'Amsterdam')
        ->set('billing_postal_code', '1012 JS')
        ->set('billing_country', 'NL')
        ->call('continueStep')
        ->assertSet('step', CheckoutStep::Configuration->value)
        ->call('continueStep')
        ->assertSet('step', CheckoutStep::Payment->value);
});

test('checkout cannot skip ahead of an incomplete step', function () {
    $product = Product::factory()->active()->create(['price_amount' => 900]);
    app(CartService::class)->add($product->id, 1);

    Livewire::test(CheckoutPage::class)
        ->set('step', CheckoutStep::Payment->value)
        ->assertSet('step', CheckoutStep::Details->value)
        ->call('goToStep', CheckoutStep::Payment->value)
        ->assertSet('step', CheckoutStep::Details->value);
});

test('checkout back navigation keeps previously entered details', function () {
    $product = Product::factory()->active()->create(['price_amount' => 1100]);
    app(CartService::class)->add($product->id, 1);

    Livewire::test(CheckoutPage::class)
        ->set('customer_name', 'Back Buyer')
        ->set('customer_email', 'back@example.com')
        ->set('billing_name', 'Back Buyer')
        ->set('billing_line1', 'Damrak 1')
        ->set('billing_city', 'Amsterdam')
        ->set('billing_postal_code', '1012 LG')
        ->set('billing_country', 'NL')
        ->call('continueStep')
        ->assertSet('step', CheckoutStep::Payment->value)
        ->call('back')
        ->assertSet('step', CheckoutStep::Details->value)
        ->assertSet('customer_name', 'Back Buyer')
        ->assertSet('customer_email', 'back@example.com');
});

test('changing country invalidates delivery without clearing contact details', function () {
    checkoutEnableShipping();
    $product = Product::factory()->active()->create(['price_amount' => 1800]);
    app(ProductCapabilityManager::class)->enable($product, 'physical');
    app(ProductCapabilityManager::class)->enable($product, 'shippable', ['weight_grams' => 250]);
    app(CartService::class)->add($product->id, 1);

    Livewire::test(CheckoutPage::class)
        ->set('customer_name', 'Ship Buyer')
        ->set('customer_email', 'ship@example.com')
        ->set('billing_name', 'Ship Buyer')
        ->set('billing_line1', 'Kalverstraat 12')
        ->set('billing_city', 'Amsterdam')
        ->set('billing_postal_code', '1012 PH')
        ->set('billing_country', 'NL')
        ->call('continueStep')
        ->assertSet('step', CheckoutStep::Delivery->value)
        ->call('continueStep')
        ->assertSet('step', CheckoutStep::Payment->value)
        ->set('billing_country', 'BE')
        ->assertSet('customer_name', 'Ship Buyer')
        ->assertSet('step', CheckoutStep::Delivery->value);
});

test('digital checkout payment places a manual order without collecting card data', function () {
    $product = Product::factory()->active()->create(['price_amount' => 2200]);
    app(CartService::class)->add($product->id, 1);

    Livewire::test(CheckoutPage::class)
        ->set('customer_name', 'Ada Guest')
        ->set('customer_email', 'ada@example.com')
        ->set('billing_name', 'Ada Guest')
        ->set('billing_line1', 'Keizersgracht 1')
        ->set('billing_city', 'Amsterdam')
        ->set('billing_postal_code', '1015 CJ')
        ->set('billing_country', 'NL')
        ->call('continueStep')
        ->assertSet('step', CheckoutStep::Payment->value)
        ->assertSee(__('storefront.checkout.hosted_payment_note'))
        ->assertDontSee('CVC', false)
        ->assertDontSee('CVV', false)
        ->assertSee(__('storefront.checkout.place_order'))
        ->call('placeOrder')
        ->assertRedirect();
});

test('physical checkout walks details delivery and payment', function () {
    checkoutEnableShipping();
    $product = Product::factory()->active()->create(['price_amount' => 2500]);
    app(ProductCapabilityManager::class)->enable($product, 'physical');
    app(ProductCapabilityManager::class)->enable($product, 'shippable', ['weight_grams' => 400]);
    app(CartService::class)->add($product->id, 1);

    Livewire::test(CheckoutPage::class)
        ->set('customer_name', 'Ship Buyer')
        ->set('customer_email', 'ship@example.com')
        ->set('billing_name', 'Ship Buyer')
        ->set('billing_line1', 'Kalverstraat 12')
        ->set('billing_city', 'Amsterdam')
        ->set('billing_postal_code', '1012 PH')
        ->set('billing_country', 'NL')
        ->call('continueStep')
        ->assertSet('step', CheckoutStep::Delivery->value)
        ->assertSee('Standard')
        ->call('continueStep')
        ->assertSet('step', CheckoutStep::Payment->value)
        ->assertSee(__('storefront.checkout.place_order'))
        ->call('placeOrder')
        ->assertRedirect();
});
