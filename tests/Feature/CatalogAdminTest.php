<?php

use App\Agovena\Catalog\DeleteProduct;
use App\Enums\ProductStatus;
use App\Livewire\Admin\Products\Create;
use App\Livewire\Admin\Products\Edit;
use App\Livewire\Admin\Products\Index;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('staff with permission can create and publish a product', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff);

    Livewire::test(Create::class)
        ->set('name', 'Starter Kit')
        ->set('sku', 'SKU-STARTER-1')
        ->set('description', 'A generic product')
        ->set('status', 'active')
        ->set('price', '25.00')
        ->set('currency', 'EUR')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::query()->where('slug', 'starter-kit')->first();

    expect($product)->not->toBeNull()
        ->and($product->status)->toBe(ProductStatus::Active)
        ->and($product->price_amount)->toBe(2500)
        ->and($product->sku)->toBe('SKU-STARTER-1');
});

test('product form uses task focused tabs instead of one long page', function () {
    Livewire::actingAs($this->createStaff())
        ->test(Create::class)
        ->assertSee('ag-product-tabs', false)
        ->assertSee('role="tablist"', false)
        ->assertSee(__('admin.products.tabs.details'))
        ->assertSee(__('admin.products.tabs.pricing'))
        ->assertDontSee(__('admin.products.tabs.automation'));
});

test('staff without create permission cannot create products', function () {
    $staff = $this->createStaff([], ['products.view', 'dashboard.view']);

    $this->actingAs($staff)
        ->get('/admin/products/create')
        ->assertForbidden();
});

test('staff can update product price without affecting historical orders later', function () {
    $staff = $this->createStaff();
    $product = Product::factory()->active()->create(['price_amount' => 1000]);

    $this->actingAs($staff);

    Livewire::test(Edit::class, ['product' => $product])
        ->set('price', '99,99')
        ->call('save')
        ->assertHasNoErrors();

    expect($product->refresh()->price_amount)->toBe(9999);
});

test('staff can update product presentation and specifications', function () {
    $staff = $this->createStaff();
    $product = Product::factory()->active()->create();

    $this->actingAs($staff);

    Livewire::test(Edit::class, ['product' => $product])
        ->set('subtitle', 'Clear case for MagSafe phones')
        ->set('show_details', true)
        ->set('show_specifications', false)
        ->set('specRows', [
            ['label' => 'Material', 'value' => 'Polycarbonate'],
            ['label' => '', 'value' => ''],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $product->refresh();

    expect($product->subtitle)->toBe('Clear case for MagSafe phones')
        ->and($product->show_specifications)->toBeFalse()
        ->and($product->specifications)->toBe([
            ['label' => 'Material', 'value' => 'Polycarbonate'],
        ]);
});

test('physical selling preset enables available fulfillment capabilities', function () {
    $staff = $this->createStaff();
    $product = Product::factory()->create();
    installAndEnableModules(['inventory', 'shipping']);

    $this->actingAs($staff);

    Livewire::test(Edit::class, ['product' => $product])
        ->call('applyPreset', 'physical')
        ->assertSet('capabilityEnabled.physical', true)
        ->assertSet('capabilityEnabled.inventory', true)
        ->assertSet('capabilityEnabled.shippable', true);
});

test('product list supports search and status filter', function () {
    $staff = $this->createStaff();
    Product::factory()->active()->create(['name' => 'Alpha Phone', 'sku' => 'ALP-1']);
    Product::factory()->draft()->create(['name' => 'Beta Case', 'sku' => 'BET-1']);

    $this->actingAs($staff);

    Livewire::test(Index::class)
        ->set('search', 'Alpha')
        ->assertSee('Alpha Phone')
        ->assertDontSee('Beta Case')
        ->set('search', '')
        ->set('status', 'draft')
        ->assertSee('Beta Case')
        ->assertDontSee('Alpha Phone');
});

test('unreferenced product can be permanently deleted', function () {
    $staff = $this->createStaff();
    $product = Product::factory()->draft()->create();

    $this->actingAs($staff);

    Livewire::test(Index::class)
        ->call('confirmDelete', $product->id)
        ->call('deleteProduct')
        ->assertHasNoErrors();

    expect(Product::query()->whereKey($product->id)->exists())->toBeFalse();
});

test('product referenced by order items cannot be deleted', function () {
    $staff = $this->createStaff();
    $product = Product::factory()->active()->create();

    OrderItem::query()->create([
        'order_id' => Order::factory()->create()->id,
        'product_id' => $product->id,
        'label' => $product->name,
        'quantity' => 1,
        'unit_amount' => $product->price_amount,
        'line_total_amount' => $product->price_amount,
        'currency' => $product->currency,
    ]);

    expect(fn () => app(DeleteProduct::class)->handle($product))
        ->toThrow(ValidationException::class);

    expect(Product::query()->whereKey($product->id)->exists())->toBeTrue();
});

test('product gallery uploads reject svg files', function () {
    $staff = $this->createStaff();
    $product = Product::factory()->active()->create();

    Livewire::actingAs($staff)
        ->test(Edit::class, ['product' => $product])
        ->set('uploads', [UploadedFile::fake()->create('evil.svg', 20, 'image/svg+xml')])
        ->assertHasErrors(['uploads.0']);
});
