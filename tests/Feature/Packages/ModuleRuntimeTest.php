<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Availability\InventoryService;
use App\Agovena\Availability\Models\InventoryStock;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Store\ApplyStorePresets;
use App\Livewire\Admin\Modules\Index as ModulesIndex;
use App\Models\Product;
use App\Models\ProductCapability;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function enableAvailabilityCapability(): void
{
    // Availability and physical commerce are Core capabilities.
}

test('core registers physical and availability capabilities without optional modules', function () {
    enableAvailabilityCapability();

    expect(app(ProductCapabilityRegistry::class)->has('physical'))->toBeTrue()
        ->and(app(ProductCapabilityRegistry::class)->has('shippable'))->toBeTrue()
        ->and(app(ProductCapabilityRegistry::class)->has('availability'))->toBeTrue();
});

test('core physical inventory navigation is grouped under catalog', function () {
    enableAvailabilityCapability();

    $inventory = collect(app(AdminRegistrar::class)->navigationItems())
        ->firstWhere('id', 'inventory-stocks');

    expect($inventory)->not->toBeNull()
        ->and($inventory->group)->toBe('admin.nav_groups.catalog')
        ->and(__('admin.nav_groups.fulfillment'))->toBe('Fulfillment');
});

test('core availability preserves inventory stock rows', function () {
    enableAvailabilityCapability();

    $product = Product::factory()->active()->create();
    app(ProductCapabilityManager::class)->enable($product, 'physical');
    app(ProductCapabilityManager::class)->enable($product, 'inventory');
    app(InventoryService::class)->setQuantity($product, 12);

    expect(InventoryStock::query()->where('product_id', $product->id)->value('quantity'))->toBe(12);

    expect(InventoryStock::query()->where('product_id', $product->id)->value('quantity'))->toBe(12)
        ->and(ProductCapability::query()->where('product_id', $product->id)->count())->toBe(2);
});

test('Core Availability stores stock outside products table and decrements on order', function () {
    enableAvailabilityCapability();

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

test('placing order fails when Core Availability stock is insufficient', function () {
    enableAvailabilityCapability();

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

test('admin modules page does not list inventory as an optional module', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(ModulesIndex::class)
        ->set('tab', 'available')
        ->assertOk()
        ->assertDontSee('Inventory')
        ->assertDontSee('Shipping')
        ->assertDontSee('Subscription Inventory');
});

test('core-only selling setups are not installable modules on either tab', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(ModulesIndex::class)
        ->set('tab', 'available')
        ->assertViewHas('availablePresets', fn (array $rows): bool => collect($rows)
            ->every(fn (array $row): bool => ! in_array($row['preset']->id, ['physical', 'subscriptions'], true)))
        ->call('installPreset', 'physical')
        ->assertViewHas('installedPresetRows', fn (array $rows): bool => collect($rows)
            ->every(fn (array $row): bool => ! in_array($row['preset']->id, ['physical', 'subscriptions'], true)));

    expect(app(ApplyStorePresets::class)->selected())->not->toContain('physical');

    app(ApplyStorePresets::class)->installPreset('subscriptions', []);
    Livewire::actingAs($staff)
        ->test(ModulesIndex::class)
        ->assertViewHas('installedPresetRows', fn (array $rows): bool => collect($rows)
            ->every(fn (array $row): bool => $row['preset']->id !== 'subscriptions'));
});

test('a disabled preset module moves its setup out of the installed tab', function () {
    app(ApplyStorePresets::class)->installPreset('hosting', []);
    app(ModuleManager::class)->disable('domains');

    Livewire::actingAs($this->createStaff())
        ->test(ModulesIndex::class)
        ->set('tab', 'available')
        ->assertViewHas('installedPresetRows', fn (array $rows): bool => collect($rows)
            ->every(fn (array $row): bool => $row['preset']->id !== 'hosting'))
        ->assertViewHas('availablePresets', fn (array $rows): bool => collect($rows)
            ->contains(fn (array $row): bool => $row['preset']->id === 'hosting' && $row['status'] === 'partial'))
        ->assertSee(__('admin.modules.preset_status.partial'));
});

test('a disabled custom module is not marked as an active installed setup', function () {
    app(ApplyStorePresets::class)->installCustomModule('events', [], []);
    app(ModuleManager::class)->disable('events');

    Livewire::actingAs($this->createStaff())
        ->test(ModulesIndex::class)
        ->set('tab', 'available')
        ->assertViewHas('hasCustomModules', false)
        ->assertViewHas('customStatus', 'missing')
        ->assertViewHas('showCustomRemove', true)
        ->assertSee(__('admin.modules.uninstall_setup'))
        ->call('uninstallPreset', 'custom')
        ->assertViewHas('showCustomRemove', false);

    expect(app(ApplyStorePresets::class)->selected())->not->toContain('custom');
});
