<?php

use App\Enums\ProductStatus;
use App\Livewire\Admin\Products\Create;
use App\Livewire\Admin\Products\Edit;
use App\Models\Product;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('staff with permission can create and publish a product', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff, 'staff');

    Livewire::test(Create::class)
        ->set('name', 'Starter Kit')
        ->set('description', 'A generic product')
        ->set('status', 'active')
        ->set('price_amount', '2500')
        ->set('currency', 'EUR')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::query()->where('slug', 'starter-kit')->first();

    expect($product)->not->toBeNull()
        ->and($product->status)->toBe(ProductStatus::Active)
        ->and($product->price_amount)->toBe(2500);
});

test('staff without create permission cannot create products', function () {
    $staff = $this->createStaff([], ['products.view', 'dashboard.view']);

    $this->actingAs($staff, 'staff')
        ->get('/admin/products/create')
        ->assertForbidden();
});

test('staff can update product price without affecting historical orders later', function () {
    $staff = $this->createStaff();
    $product = Product::factory()->active()->create(['price_amount' => 1000]);

    $this->actingAs($staff, 'staff');

    Livewire::test(Edit::class, ['product' => $product])
        ->set('price_amount', '9999')
        ->call('save')
        ->assertHasNoErrors();

    expect($product->refresh()->price_amount)->toBe(9999);
});

test('staff can update product presentation and specifications', function () {
    $staff = $this->createStaff();
    $product = Product::factory()->active()->create();

    $this->actingAs($staff, 'staff');

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
