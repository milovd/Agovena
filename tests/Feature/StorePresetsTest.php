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

    expect($enabled)->toContain('inventory', 'shipping', 'digital-delivery', 'digital', 'subscriptions')
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

test('installing a preset additively keeps existing setups and skips already enabled modules', function () {
    installAndEnableModule('inventory');
    installAndEnableModule('shipping');

    $apply = app(ApplyStorePresets::class);
    $apply->installPreset('physical', []);
    expect($apply->selected())->toBe(['physical'])
        ->and(app(ModuleManager::class)->isEnabled('inventory'))->toBeTrue()
        ->and(app(ModuleManager::class)->isEnabled('shipping'))->toBeTrue();

    $enabled = $apply->installPreset('digital', ['physical']);

    expect($apply->selected())->toBe(['physical', 'digital'])
        ->and($enabled)->toContain('digital-delivery', 'subscriptions')
        ->and(app(ModuleManager::class)->isEnabled('inventory'))->toBeTrue()
        ->and(app(ModuleManager::class)->isEnabled('shipping'))->toBeTrue()
        ->and(app(ModuleManager::class)->isEnabled('digital-delivery'))->toBeTrue()
        ->and(app(ModuleManager::class)->isEnabled('subscriptions'))->toBeTrue();

    expect($apply->installPreset('digital', ['physical', 'digital']))->toBe([]);
});

test('custom module install adds one module without replacing existing setups', function () {
    installAndEnableModule('inventory');
    installAndEnableModule('shipping');

    $apply = app(ApplyStorePresets::class);
    $apply->installPreset('physical', []);

    expect($apply->installCustomModule('events', ['physical'], []))->toBeTrue()
        ->and($apply->selected())->toContain('physical', 'custom')
        ->and($apply->selectedModules())->toBe(['events'])
        ->and(app(ModuleManager::class)->isEnabled('inventory'))->toBeTrue()
        ->and(app(ModuleManager::class)->isEnabled('events'))->toBeTrue();
});

test('uninstalling a preset removes it from settings and disables exclusive modules', function () {
    $apply = app(ApplyStorePresets::class);
    $apply->installPreset('physical', []);
    $apply->installPreset('digital', ['physical']);

    expect($apply->selected())->toBe(['physical', 'digital'])
        ->and(app(ModuleManager::class)->isEnabled('digital-delivery'))->toBeTrue()
        ->and(app(ModuleManager::class)->isEnabled('subscriptions'))->toBeTrue();

    $disabled = $apply->uninstallPreset('digital', ['physical', 'digital']);

    expect($disabled)->toBe(['digital-delivery', 'subscriptions'])
        ->and($apply->selected())->toBe(['physical'])
        ->and(app(ModuleManager::class)->isEnabled('inventory'))->toBeTrue()
        ->and(app(ModuleManager::class)->isEnabled('shipping'))->toBeTrue()
        ->and(app(ModuleManager::class)->isEnabled('digital-delivery'))->toBeFalse()
        ->and(app(ModuleManager::class)->isEnabled('subscriptions'))->toBeFalse();
});

test('uninstalling a preset keeps modules shared with other active setups', function () {
    $apply = app(ApplyStorePresets::class);
    $apply->installPreset('digital', []);
    $apply->installPreset('downloadable', ['digital']);

    $disabled = $apply->uninstallPreset('digital', ['digital', 'downloadable']);

    expect($disabled)->toBe(['digital-delivery'])
        ->and($apply->selected())->toBe(['downloadable'])
        ->and(app(ModuleManager::class)->isEnabled('subscriptions'))->toBeTrue()
        ->and(app(ModuleManager::class)->isEnabled('digital'))->toBeTrue()
        ->and(app(ModuleManager::class)->isEnabled('digital-delivery'))->toBeFalse();
});

test('uninstalling custom setup clears custom modules and disables unshared ones', function () {
    $apply = app(ApplyStorePresets::class);
    $apply->installPreset('physical', []);
    $apply->installCustomModule('events', ['physical'], []);

    expect($apply->selectedModules())->toBe(['events']);

    $disabled = $apply->uninstallPreset('custom', ['physical', 'custom'], ['events']);

    expect($disabled)->toBe(['events'])
        ->and($apply->selected())->toBe(['physical'])
        ->and($apply->selectedModules())->toBe([])
        ->and(app(ModuleManager::class)->isEnabled('events'))->toBeFalse()
        ->and(app(ModuleManager::class)->isEnabled('inventory'))->toBeTrue();
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
