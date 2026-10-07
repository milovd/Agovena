<?php

declare(strict_types=1);

use App\Enums\ProductOptionType;
use App\Livewire\Admin\Products\OptionsEditor;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionChoice;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('staff can create and edit configurable options for one product', function (): void {
    $staff = $this->createStaff();
    $product = Product::factory()->create();

    Livewire::actingAs($staff)
        ->test(OptionsEditor::class, ['productId' => $product->id])
        ->call('create')
        ->set('key', 'region')
        ->set('label', 'Region')
        ->set('type', ProductOptionType::Select->value)
        ->set('choicesText', "eu:Europe\nus:United States")
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Region');

    $option = ProductOption::query()->where('product_id', $product->id)->where('key', 'region')->firstOrFail();

    expect($option->choices()->orderBy('sort')->pluck('value')->all())->toBe(['eu', 'us']);

    Livewire::actingAs($staff)
        ->test(OptionsEditor::class, ['productId' => $product->id])
        ->call('edit', $option->id)
        ->set('label', 'Server region')
        ->set('choicesText', "eu:Europe\nus:United States\nap:Asia Pacific")
        ->call('save')
        ->assertHasNoErrors();

    expect($option->fresh()->label)->toBe('Server region')
        ->and($option->choices()->count())->toBe(3);
});

test('admin-configured options stay bound to their product and appear on that product storefront', function (): void {
    $staff = $this->createStaff();
    $product = Product::factory()->active()->create([
        'name' => 'Configured VPS',
        'slug' => 'admin-configured-vps',
    ]);
    $otherProduct = Product::factory()->active()->create([
        'name' => 'Other VPS',
        'slug' => 'other-vps',
    ]);

    Livewire::actingAs($staff)
        ->test(OptionsEditor::class, ['productId' => $product->id])
        ->call('create')
        ->set('key', 'region')
        ->set('label', 'Server region')
        ->set('type', ProductOptionType::Select->value)
        ->set('is_required', true)
        ->set('choicesText', "eu:Europe\nus:United States")
        ->call('save')
        ->assertHasNoErrors();

    $option = ProductOption::query()->where('product_id', $product->id)->firstOrFail();
    expect($option->key)->toBe('region')
        ->and(ProductOption::query()->where('product_id', $otherProduct->id)->exists())->toBeFalse()
        ->and($option->choices()->orderBy('sort')->pluck('value')->all())->toBe(['eu', 'us']);

    $this->get(route('storefront.product.configure', $product->slug))
        ->assertOk()
        ->assertSee('Server region')
        ->assertSee('Europe');

    $this->get(route('storefront.product.configure', $otherProduct->slug))
        ->assertOk()
        ->assertDontSee('Server region')
        ->assertDontSee('Europe');
});

test('editing an option from another product is rejected without moving that option', function (): void {
    $staff = $this->createStaff();
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $foreignOption = ProductOption::query()->create([
        'product_id' => $otherProduct->id,
        'key' => 'foreign_option',
        'label' => 'Other product option',
        'type' => ProductOptionType::Text,
        'is_required' => false,
        'is_active' => true,
        'sort' => 0,
        'price_adjustment_amount' => 0,
        'constraints' => [],
    ]);

    Livewire::actingAs($staff)
        ->test(OptionsEditor::class, ['productId' => $product->id])
        ->set('editingId', $foreignOption->id)
        ->set('key', 'attempted_option')
        ->set('label', 'Attempted overwrite')
        ->set('type', ProductOptionType::Text->value)
        ->call('save')
        ->assertHasErrors('editingId');

    expect($foreignOption->fresh()->product_id)->toBe($otherProduct->id)
        ->and($foreignOption->fresh()->key)->toBe('foreign_option')
        ->and(ProductOption::query()->where('product_id', $product->id)->exists())->toBeFalse();
});

test('product binding cannot be changed in the browser after mounting the options editor', function (): void {
    $staff = $this->createStaff();
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();

    expect(fn () => Livewire::actingAs($staff)
        ->test(OptionsEditor::class, ['productId' => $product->id])
        ->set('productId', $otherProduct->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('invalid configurable choices do not leave an unusable product option behind', function (): void {
    $staff = $this->createStaff();
    $product = Product::factory()->create();

    Livewire::actingAs($staff)
        ->test(OptionsEditor::class, ['productId' => $product->id])
        ->call('create')
        ->set('key', 'location_id')
        ->set('label', 'Location')
        ->set('type', ProductOptionType::Select->value)
        ->set('choicesText', '')
        ->call('save')
        ->assertHasErrors('choicesText');

    expect(ProductOption::query()->where('product_id', $product->id)->exists())->toBeFalse();
});

test('invalid configurable choices do not partially update an existing option', function (): void {
    $staff = $this->createStaff();
    $product = Product::factory()->create();
    $option = ProductOption::query()->create([
        'product_id' => $product->id,
        'key' => 'region',
        'label' => 'Region',
        'type' => ProductOptionType::Select,
        'is_required' => false,
        'is_active' => true,
        'sort' => 0,
        'price_adjustment_amount' => 0,
        'constraints' => [],
    ]);
    ProductOptionChoice::query()->create([
        'product_option_id' => $option->id,
        'value' => 'eu',
        'label' => 'Europe',
        'price_adjustment_amount' => 0,
        'sort' => 0,
        'is_active' => true,
    ]);

    Livewire::actingAs($staff)
        ->test(OptionsEditor::class, ['productId' => $product->id])
        ->call('edit', $option->id)
        ->set('label', 'New region label')
        ->set('choicesText', '')
        ->call('save')
        ->assertHasErrors('choicesText');

    expect($option->fresh()->label)->toBe('Region')
        ->and($option->choices()->pluck('value')->all())->toBe(['eu']);
});

test('staff without product update permission cannot open the product options editor', function (): void {
    $staff = $this->createStaff(permissions: ['products.view']);
    $product = Product::factory()->create();

    Livewire::actingAs($staff)
        ->test(OptionsEditor::class, ['productId' => $product->id])
        ->assertForbidden();
});
