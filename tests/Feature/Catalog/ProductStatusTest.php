<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Livewire\Admin\Products\Index;
use App\Models\AuditLog;
use App\Models\Product;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('setting an unknown product status is a validation error, not a server error', function () {
    $product = Product::factory()->create(['status' => ProductStatus::Active]);
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->call('setStatus', $product->id, 'bogus')
        ->assertHasErrors(['status']);

    expect($product->fresh()->status)->toBe(ProductStatus::Active);
});

test('the delete dialog can set a product to draft instead', function () {
    $product = Product::factory()->create(['status' => ProductStatus::Active]);
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->call('draftAndClose', $product->id)
        ->assertHasNoErrors()
        ->assertSet('confirmingDeleteId', null);

    expect($product->fresh()->status)->toBe(ProductStatus::Draft);
});

test('changing a product status is saved and audited with before and after values', function () {
    $product = Product::factory()->create(['status' => ProductStatus::Active]);
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->call('setStatus', $product->id, 'draft')
        ->assertHasNoErrors();

    $log = AuditLog::query()->where('action', 'product.status_changed')->latest('id')->first();

    expect($product->fresh()->status)->toBe(ProductStatus::Draft)
        ->and($log)->not->toBeNull()
        ->and($log->before)->toMatchArray(['status' => 'active'])
        ->and($log->after)->toMatchArray(['status' => 'draft']);
});
