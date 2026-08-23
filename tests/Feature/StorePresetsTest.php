<?php

declare(strict_types=1);

use App\Agovena\Modules\ModuleManager;
use App\Agovena\Store\ApplyStorePresets;
use App\Agovena\Store\StorePresetCatalog;
use App\Livewire\Admin\Store\Presets as StorePresets;
use App\Models\AgovenaModule;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('store presets enable the union of modules without disabling others', function () {
    expect(app(ModuleManager::class)->isEnabled('inventory'))->toBeFalse()
        ->and(app(ModuleManager::class)->isEnabled('digital-delivery'))->toBeFalse()
        ->and(app(ModuleManager::class)->isEnabled('digital'))->toBeFalse();

    $enabled = app(ApplyStorePresets::class)->handle(['physical', 'digital', 'downloadable']);

    expect($enabled)->toContain('inventory', 'shipping', 'digital-delivery', 'digital')
        ->and(app(ModuleManager::class)->isEnabled('inventory'))->toBeTrue()
        ->and(app(ModuleManager::class)->isEnabled('shipping'))->toBeTrue()
        ->and(app(ModuleManager::class)->isEnabled('digital-delivery'))->toBeTrue()
        ->and(app(ModuleManager::class)->isEnabled('digital'))->toBeTrue()
        ->and(app(ApplyStorePresets::class)->selected())->toBe(['physical', 'digital', 'downloadable']);

    installAndEnableModule('subscriptions');
    app(ApplyStorePresets::class)->handle(['physical']);

    expect(app(ModuleManager::class)->isEnabled('subscriptions'))->toBeTrue()
        ->and(app(ModuleManager::class)->isEnabled('digital-delivery'))->toBeTrue()
        ->and(app(ModuleManager::class)->isEnabled('digital'))->toBeTrue();
});

test('custom preset enables no modules and core still works with zero modules', function () {
    $enabled = app(ApplyStorePresets::class)->handle(['custom']);

    expect($enabled)->toBe([])
        ->and(AgovenaModule::query()->where('enabled', true)->count())->toBe(0)
        ->and(collect(app(StorePresetCatalog::class)->all())->pluck('id')->all())
        ->toContain('events', 'downloadable');
});

test('staff can apply store presets from admin', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(StorePresets::class)
        ->set('selected', ['hosting'])
        ->call('apply')
        ->assertHasNoErrors();

    expect(app(ModuleManager::class)->isEnabled('provisioning'))->toBeTrue()
        ->and(app(ModuleManager::class)->isEnabled('subscriptions'))->toBeTrue();
});
