<?php

declare(strict_types=1);

use App\Agovena\Catalog\Capabilities\ProductCapabilityDefinition;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

test('product capabilities are composable rows not product columns', function () {
    $registry = app(ProductCapabilityRegistry::class);
    $registry->register(new ProductCapabilityDefinition(
        key: 'physical',
        label: 'admin.products.capabilities.physical',
    ));
    $registry->register(new ProductCapabilityDefinition(
        key: 'inventory',
        label: 'admin.products.capabilities.inventory',
        requires: ['physical'],
    ));

    $product = Product::factory()->create();
    $manager = app(ProductCapabilityManager::class);

    expect(fn () => $manager->enable($product, 'inventory'))
        ->toThrow(ValidationException::class);

    $manager->enable($product, 'physical');
    $manager->enable($product, 'inventory', ['note' => 'demo']);

    $product->refresh()->load('capabilities');

    expect($product->hasCapability('physical'))->toBeTrue()
        ->and($product->hasCapability('inventory'))->toBeTrue()
        ->and($product->capability('inventory')?->config)->toBe(['note' => 'demo'])
        ->and(array_key_exists('quantity', $product->getAttributes()))->toBeFalse();
});
