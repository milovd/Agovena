<?php

declare(strict_types=1);

use App\Agovena\Availability\Http\Livewire\Admin\StocksIndex;
use App\Agovena\Availability\InventoryService;
use App\Agovena\Availability\Models\InventoryStock;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Models\Product;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function inventoriedProduct(string $name, string $sku): Product
{
    $product = Product::factory()->active()->create(['name' => $name, 'sku' => $sku]);
    app(ProductCapabilityManager::class)->enable($product, 'physical');
    app(ProductCapabilityManager::class)->enable($product, 'inventory');

    return $product;
}

test('inventory list presents a product card with named editable stock controls and a per-row save', function (): void {
    $staff = $this->createStaff();
    $product = inventoriedProduct('Warehouse sample', 'WAREHOUSE-LONG-SKU-001');
    app(InventoryService::class)->setQuantity($product, 12, true, false);

    $page = Livewire::actingAs($staff)->test(StocksIndex::class);
    $html = $page->html();

    expect($html)->toContain('c-inventory__table')
        ->toContain('c-inventory__identity')
        ->toContain('Warehouse sample')
        ->toContain('WAREHOUSE-LONG-SKU-001')
        ->toContain('wire:model.live.debounce.300ms="search"')
        ->toContain('wire:model="quantities.'.$product->id.'"')
        ->toContain('wire:model="trackStock.'.$product->id.'"')
        ->toContain('wire:model="allowOversell.'.$product->id.'"')
        ->toContain('for="stock-quantity-'.$product->id.'"')
        ->toContain('id="stock-quantity-'.$product->id.'"')
        ->toContain('for="stock-track-'.$product->id.'"')
        ->toContain('id="stock-track-'.$product->id.'"')
        ->toContain('for="stock-oversell-'.$product->id.'"')
        ->toContain('id="stock-oversell-'.$product->id.'"')
        ->toContain('wire:click="saveStock('.$product->id.')"')
        ->toContain('aria-label="'.__('common.save').': Warehouse sample"');

    // Labels need row context even when the column headings disappear on mobile.
    expect($html)->toMatch('/<label[^>]+for="stock-quantity-'.$product->id.'"[^>]*>.*?'.preg_quote(__('inventory::admin.quantity'), '/').'.*?Warehouse sample.*?<\/label>/s')
        ->toMatch('/<label[^>]+for="stock-track-'.$product->id.'"[^>]*>.*?'.preg_quote(__('inventory::admin.track_stock'), '/').'.*?Warehouse sample.*?<\/label>/s')
        ->toMatch('/<label[^>]+for="stock-oversell-'.$product->id.'"[^>]*>.*?'.preg_quote(__('inventory::admin.allow_oversell'), '/').'.*?Warehouse sample.*?<\/label>/s');

    $page->set('quantities.'.$product->id, 7)
        ->set('trackStock.'.$product->id, false)
        ->set('allowOversell.'.$product->id, true)
        ->call('saveStock', $product->id)
        ->assertSee(__('inventory::admin.stock_saved'));

    $stock = InventoryStock::query()->where('product_id', $product->id)->firstOrFail();
    expect($stock->quantity)->toBe(7)
        ->and($stock->track_stock)->toBeFalse()
        ->and($stock->allow_oversell)->toBeTrue();
});

test('inventory stylesheet exposes full-width mobile cards rather than a clipped table', function (): void {
    $path = resource_path('css/admin/screens/_inventory.css');
    $stylesheet = is_file($path) ? (string) file_get_contents($path) : '';
    $entry = (string) file_get_contents(resource_path('css/admin.css'));

    expect($entry)->toContain("@import './admin/screens/_inventory.css';")
        ->and($stylesheet)->toContain('@media (max-width: 720px)')
        ->toMatch('/\.c-inventory__table-wrap\s*\{[^}]*overflow:\s*visible;[^}]*contain:\s*none;/s')
        ->toMatch('/\.c-inventory__table tbody tr\s*\{[^}]*display:\s*grid;/s')
        ->toMatch('/\.c-inventory__table tbody td\s*\{[^}]*min-width:\s*0;[^}]*white-space:\s*normal;/s')
        ->toContain('overflow-wrap: anywhere;');
});

test('inventory empty list keeps search and the existing empty guidance without invented actions', function (): void {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)->test(StocksIndex::class)
        ->assertSee('wire:model.live.debounce.300ms="search"', false)
        ->assertSee(__('inventory::admin.empty.title'))
        ->assertSee(__('inventory::admin.empty.text'))
        ->assertDontSee('c-inventory__table', false)
        ->assertDontSee('wire:click="saveStock(', false);
});

test('inventory search filters product names and SKU while retaining the same row controls', function (): void {
    $staff = $this->createStaff();
    $found = inventoriedProduct('Kept product', 'FOUND-001');
    inventoriedProduct('Hidden product', 'HIDDEN-002');

    Livewire::actingAs($staff)->test(StocksIndex::class)
        ->set('search', 'FOUND-001')
        ->assertSee('Kept product')
        ->assertDontSee('Hidden product')
        ->assertSee('wire:click="saveStock('.$found->id.')"', false);
});

test('inventory view-only staff can read stock but cannot edit or save', function (): void {
    $staff = $this->createStaff([], ['inventory.view']);
    $product = inventoriedProduct('Read-only stock', 'READ-001');
    app(InventoryService::class)->setQuantity($product, 4);

    $page = Livewire::actingAs($staff)->test(StocksIndex::class)
        ->assertSee('Read-only stock')
        ->assertDontSee('wire:click="saveStock(', false);
    $html = $page->html();

    foreach (['quantities', 'trackStock', 'allowOversell'] as $property) {
        expect($html)->toMatch('/<input[^>]+wire:model="'.$property.'\.'.$product->id.'"[^>]+disabled(?:=|\s|>)/s');
    }

    $page->call('saveStock', $product->id)->assertForbidden();
    expect(InventoryStock::query()->where('product_id', $product->id)->value('quantity'))->toBe(4);
});
