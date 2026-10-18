<?php

declare(strict_types=1);

use App\Agovena\Settings\SettingsRepository;
use App\Livewire\Admin\Taxes\Index;
use App\Models\TaxRate;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('view-only tax staff keep rate facts without exposing a forged edit form', function (): void {
    $viewer = $this->createStaff(permissions: ['taxes.view']);
    $rate = TaxRate::query()->create([
        'name' => 'Sample override',
        'rate_bps' => 2100,
        'country' => 'BE',
        'region' => null,
        'is_active' => true,
        'is_disabled' => false,
        'applies_to_shipping' => false,
    ]);

    Livewire::actingAs($viewer)
        ->test(Index::class)
        ->set('showForm', true)
        ->set('editingId', $rate->id)
        ->assertSee($rate->name)
        ->assertDontSee('wire:submit="save"', false)
        ->assertDontSee('wire:click="create"', false)
        ->assertDontSee('wire:click="edit(', false)
        ->assertDontSee('wire:click="delete(', false);
});

test('tax rate rows keep every fact and confirmed manager action available on mobile', function (string $locale): void {
    app()->setLocale($locale);
    $manager = $this->createStaff();
    $rate = TaxRate::query()->create([
        'name' => 'Sample override',
        'rate_bps' => 2100,
        'country' => 'BE',
        'region' => null,
        'is_active' => true,
        'is_disabled' => false,
        'applies_to_shipping' => true,
    ]);

    Livewire::actingAs($manager)
        ->test(Index::class)
        ->assertSee('ag-table--mobile-cards ag-table--taxes', false)
        ->assertSee('role="region"', false)
        ->assertSee('tabindex="0"', false)
        ->assertSee('data-label="'.__('admin.taxes.country').'"', false)
        ->assertSee('data-label="'.__('common.name').'"', false)
        ->assertSee('data-label="'.__('admin.taxes.rate').'"', false)
        ->assertSee('data-label="'.__('admin.taxes.source').'"', false)
        ->assertSee('data-label="'.__('admin.taxes.shipping').'"', false)
        ->assertSee('data-label="'.__('common.status').'"', false)
        ->assertSee('ag-badge--success', false)
        ->assertSee('data-label="'.__('common.actions').'"', false)
        ->assertSee('wire:click="edit('.$rate->id.')"', false)
        ->assertSee('wire:click="delete('.$rate->id.')" wire:confirm="'.__('admin.taxes.delete_confirm').'"', false);
})->with(['en', 'nl']);

test('empty and filtered tax lists show readable localized guidance without a clipped table', function (string $locale): void {
    app()->setLocale($locale);
    app(SettingsRepository::class)->set('store', 'tax_enabled', true);
    app(SettingsRepository::class)->set('store', 'automatic_tax_rates', true);
    $manager = $this->createStaff();

    Livewire::actingAs($manager)
        ->test(Index::class)
        ->assertSee('class="ag-empty" role="status"', false)
        ->assertSee(__('admin.taxes.empty'), false)
        ->assertDontSee(__('admin.taxes.list_help'))
        ->assertDontSee('<table', false);

    TaxRate::query()->create([
        'name' => 'Sample override',
        'rate_bps' => 2100,
        'country' => 'BE',
        'region' => null,
        'is_active' => true,
        'is_disabled' => false,
        'applies_to_shipping' => false,
    ]);

    Livewire::actingAs($manager)
        ->test(Index::class)
        ->set('filter', 'no-such-country')
        ->assertSee('class="ag-empty" role="status"', false)
        ->assertSee('wire:model.live.debounce.300ms="filter"', false)
        ->assertSee(__('admin.taxes.filter_empty'), false)
        ->assertDontSee('<table', false);
})->with(['en', 'nl']);

test('tax empty guidance follows enabled and automatic settings', function (string $locale, bool $enabled, bool $automatic, string $key): void {
    app()->setLocale($locale);
    app(SettingsRepository::class)->set('store', 'tax_enabled', $enabled);
    app(SettingsRepository::class)->set('store', 'automatic_tax_rates', $automatic);
    $manager = $this->createStaff();

    Livewire::actingAs($manager)
        ->test(Index::class)
        ->assertSee(__('admin.taxes.'.$key))
        ->assertDontSee(__('admin.taxes.empty_text'));
})->with([
    ['en', false, true, 'empty_tax_disabled'],
    ['nl', false, true, 'empty_tax_disabled'],
    ['en', true, false, 'empty_manual'],
    ['nl', true, false, 'empty_manual'],
]);

test('view-only staff cannot trigger the automatic tax lookup through a forged field update', function (): void {
    $viewer = $this->createStaff(permissions: ['taxes.view']);

    Livewire::actingAs($viewer)
        ->test(Index::class)
        ->set('country', 'B')
        ->assertForbidden();
});

test('tax management keeps its existing create edit and confirmed delete behavior', function (): void {
    $manager = $this->createStaff();
    $rate = TaxRate::query()->create([
        'name' => 'Sample override',
        'rate_bps' => 2100,
        'country' => 'BE',
        'region' => null,
        'is_active' => false,
        'is_disabled' => false,
        'applies_to_shipping' => false,
    ]);

    Livewire::actingAs($manager)
        ->test(Index::class)
        ->assertSee('ag-badge--muted', false)
        ->call('create')
        ->assertSee('wire:submit="save"', false)
        ->call('cancel')
        ->call('edit', $rate->id)
        ->assertSet('name', 'Sample override')
        ->set('name', 'Renamed override')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Renamed override')
        ->call('delete', $rate->id)
        ->assertDontSee('Renamed override');

    expect(TaxRate::query()->find($rate->id))->toBeNull();
});

test('view-only tax staff cannot invoke protected edit or delete actions', function (): void {
    $viewer = $this->createStaff(permissions: ['taxes.view']);
    $rate = TaxRate::query()->create([
        'name' => 'Sample override',
        'rate_bps' => 2100,
        'country' => 'BE',
        'region' => null,
        'is_active' => false,
        'is_disabled' => false,
        'applies_to_shipping' => false,
    ]);

    Livewire::actingAs($viewer)->test(Index::class)->call('edit', $rate->id)->assertForbidden();
    Livewire::actingAs($viewer)->test(Index::class)->call('delete', $rate->id)->assertForbidden();
    expect(TaxRate::query()->find($rate->id))->not->toBeNull();
});

test('tax controls and overrides share the Admin section and form hierarchy', function (): void {
    $manager = $this->createStaff();

    Livewire::actingAs($manager)->test(Index::class)
        ->assertSee('admin-page--taxes ag-list-page', false)
        ->assertSee('ag-tax-settings', false)
        ->assertSee('ag-tax-settings__field', false)
        ->assertSee(__('admin.taxes.settings_title'))
        ->assertDontSee(__('admin.taxes.workflow_help'))
        ->call('create')
        ->assertSee('ag-catalog-form__heading', false)
        ->assertSee('ag-grid ag-grid--2', false)
        ->assertSee('wire:model="name"', false)
        ->assertSee('wire:model.number="rate_bps"', false)
        ->assertSee('wire:model.live="country"', false)
        ->assertSee('wire:model="region"', false)
        ->assertSee('wire:model="applies_to_shipping"', false)
        ->assertSee('wire:model.live="is_disabled"', false)
        ->assertSee('wire:model="is_active"', false);
});
