<?php

declare(strict_types=1);

use App\Agovena\Catalog\SetSelectedProductStatus;
use App\Enums\ProductStatus;
use App\Livewire\Admin\Products\Index;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('renders confirmed bulk controls and accessible row selection only for product editors', function (): void {
    $product = Product::factory()->draft()->create(['name' => 'Example']);
    $manager = $this->createStaff([], ['products.view', 'products.update']);

    Livewire::actingAs($manager)->test(Index::class)
        ->assertSee('aria-label="'.__('admin.products.bulk_select_row', ['name' => $product->name]).'"', false)
        ->assertDontSee('class="ag-checkbox__label"', false)
        ->assertSee('wire:click="bulkSetStatus(\'active\')"', false)
        ->assertSee('wire:confirm=', false);

    $viewer = $this->createStaff([], ['products.view']);
    Livewire::actingAs($viewer)->test(Index::class)
        ->assertDontSee('selectedProductIds', false)
        ->assertDontSee('bulkSetStatus', false);
});

it('publishes selected products without changing their catalog data and audits each change', function (): void {
    $staff = $this->createStaff([], ['products.view', 'products.update']);
    $first = Product::factory()->draft()->create(['name' => 'First', 'price_amount' => 1234, 'description' => 'Keep this']);
    $second = Product::factory()->draft()->create(['name' => 'Second', 'price_amount' => 5678]);

    Livewire::actingAs($staff)->test(Index::class)
        ->set('selectedProductIds', [(string) $first->id, (string) $second->id])
        ->call('bulkSetStatus', 'active')
        ->assertHasNoErrors()
        ->assertSet('bulkResult', ['updated' => 2, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0])
        ->assertSee(__('admin.products.bulk_result', ['updated' => 2, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0]));

    expect($first->fresh()->status)->toBe(ProductStatus::Active)
        ->and($first->fresh()->price_amount)->toBe(1234)
        ->and($first->fresh()->description)->toBe('Keep this')
        ->and($second->fresh()->status)->toBe(ProductStatus::Active)
        ->and($second->fresh()->price_amount)->toBe(5678)
        ->and(AuditLog::query()->where('action', 'product.status_changed')->count())->toBe(2);
});

it('selects only the current page and clears selection after filters sort and pagination change', function (): void {
    $staff = $this->createStaff([], ['products.view', 'products.update']);
    $category = Category::factory()->create();
    foreach (range(1, 16) as $number) {
        Product::factory()->draft()->create(['name' => sprintf('Product %02d', $number), 'category_id' => $category->id]);
    }

    Livewire::actingAs($staff)->test(Index::class)
        ->set('search', 'Product 16')
        ->call('selectCurrentPage')
        ->assertCount('selectedProductIds', 1)
        ->set('search', '')
        ->assertSet('selectedProductIds', [])
        ->call('selectCurrentPage')
        ->assertCount('selectedProductIds', 15)
        ->set('sort', 'name')
        ->assertSet('selectedProductIds', [])
        ->call('selectCurrentPage')
        ->set('category', (string) $category->id)
        ->assertSet('selectedProductIds', [])
        ->call('selectCurrentPage')
        ->set('status', 'active')
        ->assertSet('selectedProductIds', [])
        ->set('status', '')
        ->call('selectCurrentPage')
        ->call('gotoPage', 2)
        ->assertSet('selectedProductIds', [])
        ->call('selectCurrentPage')
        ->assertCount('selectedProductIds', 1);
});

it('skips off-page missing and filter-excluded forged ids', function (): void {
    $staff = $this->createStaff([], ['products.view', 'products.update']);
    $category = Category::factory()->create();
    $otherCategory = Category::factory()->create();
    $visible = Product::factory()->draft()->create(['name' => 'Match aardvark', 'category_id' => $category->id]);
    $wrongCategory = Product::factory()->draft()->create(['name' => 'Match category', 'category_id' => $otherCategory->id]);
    $wrongSearch = Product::factory()->draft()->create(['name' => 'Different', 'category_id' => $category->id]);
    $wrongStatus = Product::factory()->active()->create(['name' => 'Match active', 'category_id' => $category->id]);
    Product::factory()->draft()->count(15)->create(['name' => 'Match filler', 'category_id' => $category->id]);
    $offPage = Product::factory()->draft()->create(['name' => 'Match last', 'category_id' => $category->id]);

    Livewire::actingAs($staff)->test(Index::class)
        ->set('sort', 'name')
        ->set('search', 'Match')
        ->set('status', 'draft')
        ->set('category', (string) $category->id)
        ->set('selectedProductIds', array_map('strval', [$visible->id, $wrongCategory->id, $wrongSearch->id, $wrongStatus->id, $offPage->id, 999999]))
        ->call('bulkSetStatus', 'active')
        ->assertSet('bulkResult', ['updated' => 1, 'unchanged' => 0, 'skipped' => 5, 'failed' => 0]);

    expect($visible->fresh()->status)->toBe(ProductStatus::Active)
        ->and($wrongCategory->fresh()->status)->toBe(ProductStatus::Draft)
        ->and($wrongSearch->fresh()->status)->toBe(ProductStatus::Draft)
        ->and($offPage->fresh()->status)->toBe(ProductStatus::Draft)
        ->and(AuditLog::query()->where('action', 'product.status_changed')->count())->toBe(1);
});

it('rejects oversized raw selections before deduplication or writes', function (): void {
    $staff = $this->createStaff([], ['products.view', 'products.update']);
    $product = Product::factory()->draft()->create();

    Livewire::actingAs($staff)->test(Index::class)
        ->set('selectedProductIds', array_fill(0, 501, (string) $product->id))
        ->call('bulkSetStatus', 'active')
        ->assertHasErrors(['selectedProductIds']);

    expect($product->fresh()->status)->toBe(ProductStatus::Draft)
        ->and(AuditLog::query()->where('action', 'product.status_changed')->exists())->toBeFalse();
});

it('drafts only changed records once and counts unchanged records separately', function (): void {
    $staff = $this->createStaff([], ['products.view', 'products.update']);
    $active = Product::factory()->active()->create(['price_amount' => 1234]);
    $draft = Product::factory()->draft()->create();

    Livewire::actingAs($staff)->test(Index::class)
        ->set('selectedProductIds', [(string) $active->id, (string) $active->id, (string) $draft->id])
        ->call('bulkSetStatus', 'draft')
        ->assertSet('bulkResult', ['updated' => 1, 'unchanged' => 1, 'skipped' => 0, 'failed' => 0]);

    $log = AuditLog::query()->where('action', 'product.status_changed')->sole();
    expect($active->fresh()->status)->toBe(ProductStatus::Draft)
        ->and($active->fresh()->price_amount)->toBe(1234)
        ->and($log->before)->toMatchArray(['status' => 'active'])
        ->and($log->after)->toMatchArray(['status' => 'draft']);
});

it('denies forged bulk mutation to a reader and rejects invalid status and empty selection', function (): void {
    $viewer = $this->createStaff([], ['products.view']);
    $product = Product::factory()->draft()->create();

    Livewire::actingAs($viewer)->test(Index::class)
        ->set('selectedProductIds', [(string) $product->id])
        ->call('bulkSetStatus', 'active')->assertForbidden();

    $manager = $this->createStaff([], ['products.view', 'products.update']);
    Livewire::actingAs($manager)->test(Index::class)
        ->set('selectedProductIds', [(string) $product->id])
        ->call('bulkSetStatus', 'published')->assertHasErrors(['bulkStatus'])
        ->assertSet('selectedProductIds', [(string) $product->id]);
    Livewire::actingAs($manager)->test(Index::class)
        ->call('bulkSetStatus', 'active')->assertHasErrors(['selectedProductIds']);

    expect($product->fresh()->status)->toBe(ProductStatus::Draft);
});

it('checks each product permission and validates direct domain calls', function (): void {
    $viewer = $this->createStaff([], ['products.view']);
    $product = Product::factory()->draft()->create();
    $this->actingAs($viewer);
    $result = app(SetSelectedProductStatus::class)->handle([$product->id, 999999], 'active');
    expect($result)->toBe(['updated' => 0, 'unchanged' => 0, 'skipped' => 2, 'failed' => 0]);

    $manager = $this->createStaff([], ['products.view', 'products.update']);
    $this->actingAs($manager);
    $action = app(SetSelectedProductStatus::class);
    expect(fn () => $action->handle([$product->id], 'published'))->toThrow(ValidationException::class)
        ->and(fn () => $action->handle(array_fill(0, 501, $product->id), 'active'))->toThrow(ValidationException::class)
        ->and($product->fresh()->status)->toBe(ProductStatus::Draft);
});

it('reports a failed product independently and rolls back that product write', function (): void {
    $staff = $this->createStaff([], ['products.view', 'products.update']);
    $failed = Product::factory()->draft()->create();
    $succeeded = Product::factory()->draft()->create();
    Product::saving(static function (Product $product) use ($failed): void {
        if ($product->id === $failed->id) {
            throw new RuntimeException('Simulated product write failure');
        }
    });

    Livewire::actingAs($staff)->test(Index::class)
        ->set('selectedProductIds', [(string) $failed->id, (string) $succeeded->id])
        ->call('bulkSetStatus', 'active')
        ->assertSet('bulkResult', ['updated' => 1, 'unchanged' => 0, 'skipped' => 0, 'failed' => 1]);

    expect($failed->fresh()->status)->toBe(ProductStatus::Draft)
        ->and($succeeded->fresh()->status)->toBe(ProductStatus::Active)
        ->and(AuditLog::query()->where('action', 'product.status_changed')->count())->toBe(1);
});

it('rolls back a product status write if its audit record fails', function (): void {
    $staff = $this->createStaff([], ['products.view', 'products.update']);
    $product = Product::factory()->draft()->create();
    AuditLog::creating(static function (): void {
        throw new RuntimeException('Simulated audit failure');
    });

    Livewire::actingAs($staff)->test(Index::class)
        ->set('selectedProductIds', [(string) $product->id])
        ->call('bulkSetStatus', 'active')
        ->assertSet('bulkResult', ['updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 1]);

    expect($product->fresh()->status)->toBe(ProductStatus::Draft)
        ->and(AuditLog::query()->where('action', 'product.status_changed')->exists())->toBeFalse();
});

it('does not allow the browser to fabricate bulk outcome counts', function (): void {
    $staff = $this->createStaff([], ['products.view', 'products.update']);
    expect(fn () => Livewire::actingAs($staff)->test(Index::class)
        ->set('bulkResult', ['updated' => 500, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0]))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});
