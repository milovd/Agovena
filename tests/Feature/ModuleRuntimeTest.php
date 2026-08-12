<?php

declare(strict_types=1);

use Agovena\Modules\Inventory\InventoryService;
use Agovena\Modules\Inventory\Models\InventoryStock;
use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Livewire\Admin\Modules\Index as ModulesIndex;
use App\Models\AgovenaModule;
use App\Models\Product;
use App\Models\ProductCapability;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function enableInventoryModule(): ModuleManager
{
    $modules = app(ModuleManager::class);
    $modules->enable('inventory');
    app(SyncRegisteredPermissions::class)(force: true);

    return $modules;
}

test('module manager discovers inventory and enable boots capabilities', function () {
    $modules = app(ModuleManager::class);

    expect($modules->manifest('inventory'))->not->toBeNull()
        ->and($modules->isEnabled('inventory'))->toBeFalse();

    enableInventoryModule();

    expect($modules->isEnabled('inventory'))->toBeTrue()
        ->and(app(ProductCapabilityRegistry::class)->has('physical'))->toBeTrue()
        ->and(app(ProductCapabilityRegistry::class)->has('inventory'))->toBeTrue()
        ->and(AgovenaModule::query()->where('module_id', 'inventory')->where('enabled', true)->exists())->toBeTrue();
});

test('enabled inventory navigation is grouped under services', function () {
    enableInventoryModule();

    $inventory = collect(app(AdminRegistrar::class)->navigationItems())
        ->firstWhere('id', 'inventory-stocks');

    expect($inventory)->not->toBeNull()
        ->and($inventory->group)->toBe('admin.nav_groups.services')
        ->and(__('admin.nav_groups.services'))->toBe('Services');
});

test('module disable preserves inventory stock rows', function () {
    enableInventoryModule();

    $product = Product::factory()->active()->create();
    app(ProductCapabilityManager::class)->enable($product, 'physical');
    app(ProductCapabilityManager::class)->enable($product, 'inventory');
    app(InventoryService::class)->setQuantity($product, 12);

    expect(InventoryStock::query()->where('product_id', $product->id)->value('quantity'))->toBe(12);

    app(ModuleManager::class)->disable('inventory');

    expect(app(ModuleManager::class)->isEnabled('inventory'))->toBeFalse()
        ->and(InventoryStock::query()->where('product_id', $product->id)->value('quantity'))->toBe(12)
        ->and(ProductCapability::query()->where('product_id', $product->id)->count())->toBe(2);
});

test('inventory capability stores stock outside products table and decrements on order', function () {
    enableInventoryModule();

    $product = Product::factory()->active()->create(['price_amount' => 1000]);
    app(ProductCapabilityManager::class)->enable($product, 'physical');
    app(ProductCapabilityManager::class)->enable($product, 'inventory');
    app(InventoryService::class)->setQuantity($product, 5);

    expect($product->getAttributes())->not->toHaveKey('quantity')
        ->and(InventoryStock::query()->where('product_id', $product->id)->exists())->toBeTrue();

    app(CartService::class)->add($product->id, 2);
    app(PlaceOrder::class)->handle([
        'customer_name' => 'Stock Buyer',
        'customer_email' => 'stock@example.test',
        'billing' => AddressData::fromArray([
            'name' => 'Stock Buyer',
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
    ]);

    expect(InventoryStock::query()->where('product_id', $product->id)->value('quantity'))->toBe(3);
});

test('placing order fails when inventory stock is insufficient', function () {
    enableInventoryModule();

    $product = Product::factory()->active()->create(['price_amount' => 1000]);
    app(ProductCapabilityManager::class)->enable($product, 'physical');
    app(ProductCapabilityManager::class)->enable($product, 'inventory');
    app(InventoryService::class)->setQuantity($product, 1);

    app(CartService::class)->add($product->id, 2);

    expect(fn () => app(PlaceOrder::class)->handle([
        'customer_name' => 'No Stock',
        'customer_email' => 'nostock@example.test',
        'billing' => AddressData::fromArray([
            'name' => 'No Stock',
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
    ]))->toThrow(ValidationException::class);

    expect(InventoryStock::query()->where('product_id', $product->id)->value('quantity'))->toBe(1);
});

test('admin modules page lists inventory', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff, 'staff')
        ->test(ModulesIndex::class)
        ->assertOk()
        ->assertSee('Inventory');
});
