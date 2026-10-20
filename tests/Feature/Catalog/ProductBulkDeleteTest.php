<?php

declare(strict_types=1);

use App\Agovena\Catalog\DeleteSelectedProducts;
use App\Livewire\Admin\Products\Index;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('offers bulk delete only to staff who may delete products', function (): void {
    Product::factory()->draft()->create();

    Livewire::actingAs($this->createStaff([], ['products.view', 'products.update', 'products.delete']))->test(Index::class)
        ->assertSee('wire:click="bulkDeleteProducts"', false)
        ->assertSee('wire:confirm="'.e(__('admin.products.bulk_confirm_delete')).'"', false);

    Livewire::actingAs($this->createStaff([], ['products.view', 'products.update']))->test(Index::class)
        ->assertDontSee('bulkDeleteProducts', false);
});

it('deletes the selected products on the current page and keeps products that appear in orders', function (): void {
    $staff = $this->createStaff([], ['products.view', 'products.update', 'products.delete']);
    $free = Product::factory()->draft()->create(['name' => 'Free']);
    $alsoFree = Product::factory()->draft()->create(['name' => 'Also free']);
    $ordered = Product::factory()->active()->create(['name' => 'Ordered']);
    OrderItem::factory()->create(['order_id' => Order::factory()->create()->id, 'product_id' => $ordered->id]);
    $untouched = Product::factory()->draft()->create(['name' => 'Untouched']);

    Livewire::actingAs($staff)->test(Index::class)
        ->set('selectedProductIds', array_map('strval', [$free->id, $alsoFree->id, $ordered->id, 999999]))
        ->call('bulkDeleteProducts')
        ->assertHasNoErrors()
        ->assertSet('selectedProductIds', [])
        ->assertSet('bulkDeleteResult', ['deleted' => 2, 'in_orders' => 1, 'skipped' => 1, 'failed' => 0])
        ->assertSee(__('admin.products.bulk_delete_result', ['deleted' => 2, 'in_orders' => 1, 'skipped' => 1, 'failed' => 0]));

    expect(Product::query()->whereKey([$free->id, $alsoFree->id])->exists())->toBeFalse()
        ->and($ordered->fresh())->not->toBeNull()
        ->and($untouched->fresh())->not->toBeNull();
});

it('refuses bulk delete without the delete permission, without a selection and above the limit', function (): void {
    $product = Product::factory()->draft()->create();

    Livewire::actingAs($this->createStaff([], ['products.view', 'products.update']))->test(Index::class)
        ->set('selectedProductIds', [(string) $product->id])
        ->call('bulkDeleteProducts')
        ->assertForbidden();

    $deleter = $this->createStaff([], ['products.view', 'products.update', 'products.delete']);
    Livewire::actingAs($deleter)->test(Index::class)
        ->call('bulkDeleteProducts')
        ->assertHasErrors(['selectedProductIds']);
    Livewire::actingAs($deleter)->test(Index::class)
        ->set('selectedProductIds', array_fill(0, 501, (string) $product->id))
        ->call('bulkDeleteProducts')
        ->assertHasErrors(['selectedProductIds']);

    expect($product->fresh())->not->toBeNull();
});

it('re-checks the delete permission per product inside the domain action', function (): void {
    $product = Product::factory()->draft()->create();
    $this->actingAs($this->createStaff([], ['products.view']));

    expect(app(DeleteSelectedProducts::class)->handle([$product->id]))->toBe(['deleted' => 0, 'in_orders' => 0, 'skipped' => 1, 'failed' => 0])
        ->and($product->fresh())->not->toBeNull();
});
