<?php

declare(strict_types=1);

use Agovena\Modules\Digital\Http\Livewire\Admin\AssetsIndex;
use Agovena\Modules\Digital\Models\DigitalAsset;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Models\Product;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('digital assets stay readable on mobile for view-only staff', function (): void {
    installAndEnableModule('downloads');
    app(SyncRegisteredPermissions::class)(force: true);
    $viewer = $this->createStaff(permissions: ['digital.view']);
    $product = Product::factory()->active()->create(['name' => 'Downloadable handbook']);
    app(ProductCapabilityManager::class)->enable($product, 'digital');
    DigitalAsset::query()->create([
        'product_id' => $product->id,
        'label' => 'Handbook',
        'disk' => 'local',
        'path' => 'digital/'.$product->id.'/handbook.pdf',
        'filename' => 'handbook.pdf',
        'download_limit' => 3,
        'is_active' => true,
    ]);

    Livewire::actingAs($viewer)
        ->test(AssetsIndex::class)
        ->assertSee('ag-table--mobile-cards', false)
        ->assertSee('data-label="'.__('digital::admin.filename').'"', false)
        ->assertSee('handbook.pdf')
        ->assertSee('data-label="'.__('digital::admin.download_limit').'"', false)
        ->assertDontSee('wire:submit="save"', false)
        ->assertDontSee('wire:click="delete(', false);
});

test('deleting a downloadable asset requires localized confirmation', function (string $locale): void {
    installAndEnableModule('downloads');
    app(SyncRegisteredPermissions::class)(force: true);
    app()->setLocale($locale);
    $manager = $this->createStaff();
    $product = Product::factory()->active()->create();
    app(ProductCapabilityManager::class)->enable($product, 'digital');
    $filename = 'handbook "edition" & <intro>.pdf';
    DigitalAsset::query()->create([
        'product_id' => $product->id,
        'label' => 'Handbook',
        'disk' => 'local',
        'path' => 'digital/'.$product->id.'/handbook.pdf',
        'filename' => $filename,
        'is_active' => true,
    ]);

    $confirmation = Lang::has('digital::admin.delete_confirm')
        ? __('digital::admin.delete_confirm', ['name' => $filename])
        : __('common.confirm_irreversible_delete', ['name' => $filename]);

    Livewire::actingAs($manager)
        ->test(AssetsIndex::class)
        ->assertSee('wire:confirm="'.e($confirmation).'"', false)
        ->assertSee('wire:click="delete(', false);
})->with(['en', 'nl']);
