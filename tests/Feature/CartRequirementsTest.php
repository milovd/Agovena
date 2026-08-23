<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\CartRequirement;
use App\Agovena\Checkout\CartRequirementComposer;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Enums\CustomerPropertyType;
use App\Enums\ProductOptionType;
use App\Models\CustomerPropertyDefinition;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionChoice;

function enableCommerceModules(): void
{
    installAndEnableModules(['shipping', 'digital']);
    app(SyncRegisteredPermissions::class)(force: true);
}

test('digital-only carts require billing and payment but not shipping', function () {
    enableCommerceModules();
    $ebook = Product::factory()->active()->create(['name' => 'Ebook']);
    app(ProductCapabilityManager::class)->enable($ebook, 'digital');
    app(CartService::class)->add($ebook->id, 1);

    $requirements = app(CartRequirementComposer::class)->compose(app(CartService::class));

    expect($requirements->has(CartRequirement::Billing))->toBeTrue()
        ->and($requirements->has(CartRequirement::Payment))->toBeTrue()
        ->and($requirements->requiresShipping())->toBeFalse()
        ->and($requirements->has(CartRequirement::ProductConfiguration))->toBeFalse();
});

test('mixed carts compose shipping and product configuration requirements', function () {
    enableCommerceModules();

    $shirt = Product::factory()->active()->create(['name' => 'T-shirt']);
    app(ProductCapabilityManager::class)->enable($shirt, 'physical');
    app(ProductCapabilityManager::class)->enable($shirt, 'shippable');

    $ebook = Product::factory()->active()->create(['name' => 'Ebook']);
    app(ProductCapabilityManager::class)->enable($ebook, 'digital');

    $vps = Product::factory()->active()->create(['name' => 'VPS']);
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

    CustomerPropertyDefinition::query()->create([
        'key' => 'company_reg',
        'label' => 'Company registration',
        'type' => CustomerPropertyType::Text,
        'is_required' => false,
        'constraints' => [],
        'options' => [],
        'sort' => 1,
        'is_active' => true,
        'show_on_registration' => false,
        'show_on_checkout' => true,
        'show_on_account' => true,
        'show_on_invoice' => false,
        'customer_editable' => true,
        'staff_editable' => true,
        'internal_only' => false,
    ]);

    $cart = app(CartService::class);
    $cart->add($shirt->id, 1);
    $cart->add($ebook->id, 1);
    $cart->add($vps->id, 1, ['location' => 'ams']);

    $requirements = app(CartRequirementComposer::class)->compose($cart);

    expect($requirements->has(CartRequirement::Billing))->toBeTrue()
        ->and($requirements->requiresShipping())->toBeTrue()
        ->and($requirements->has(CartRequirement::ShippingMethod))->toBeTrue()
        ->and($requirements->has(CartRequirement::ProductConfiguration))->toBeTrue()
        ->and($requirements->has(CartRequirement::CustomProperties))->toBeTrue()
        ->and($requirements->has(CartRequirement::Payment))->toBeTrue()
        ->and($cart->requiresShipping())->toBeTrue();
});
