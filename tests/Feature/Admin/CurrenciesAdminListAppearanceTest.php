<?php

declare(strict_types=1);

use App\Livewire\Admin\Currencies\Index;
use App\Models\Currency;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('currency list keeps every fact and action labelled for mobile managers', function (string $locale): void {
    app()->setLocale($locale);
    $manager = $this->createStaff();
    $usd = Currency::query()->where('code', 'USD')->firstOrFail();

    Livewire::actingAs($manager)
        ->test(Index::class)
        ->assertSee('ag-table--mobile-cards', false)
        ->assertSee('role="region"', false)
        ->assertSee('tabindex="0"', false)
        ->assertSee('data-label="'.__('admin.currencies.code').'"', false)
        ->assertSee('data-label="'.__('common.name').'"', false)
        ->assertSee('data-label="'.__('admin.currencies.prefix_column').'"', false)
        ->assertSee('data-label="'.__('admin.currencies.suffix').'"', false)
        ->assertSee('data-label="'.__('admin.currencies.precision_column').'"', false)
        ->assertSee('data-label="'.__('admin.currencies.exchange_rate_column').'"', false)
        ->assertSee('data-label="'.__('common.preview').'"', false)
        ->assertSee('data-label="'.__('common.status').'"', false)
        ->assertSee('data-label="'.__('common.actions').'"', false)
        ->assertSee('wire:click="edit('.$usd->id.')"', false)
        ->assertSee('wire:click="setAsBase('.$usd->id.')"', false);
})->with(['en', 'nl']);

test('currency viewers retain facts but cannot expose management forms or actions', function (): void {
    $viewer = $this->createStaff(permissions: ['currencies.view']);
    $usd = Currency::query()->where('code', 'USD')->firstOrFail();

    Livewire::actingAs($viewer)
        ->test(Index::class)
        ->set('showForm', true)
        ->set('editingId', $usd->id)
        ->assertSee('data-label="'.__('admin.currencies.exchange_rate_column').'"', false)
        ->assertDontSee('wire:submit="save"', false)
        ->assertDontSee('wire:click="edit(', false)
        ->assertDontSee('wire:click="setAsBase(', false)
        ->assertDontSee('wire:click="syncRates"', false)
        ->assertDontSee('data-label="'.__('common.actions').'"', false);
});

test('an empty currency list explains the missing rows without an empty table', function (string $locale): void {
    app()->setLocale($locale);
    $manager = $this->createStaff();
    Currency::query()->delete();

    Livewire::actingAs($manager)
        ->test(Index::class)
        ->assertSee('class="ag-empty"', false)
        ->assertSee(__('admin.currencies.empty'))
        ->assertDontSee('<table', false);
})->with(['en', 'nl']);

test('an out-of-range currency page does not claim the catalogue is empty', function (string $locale): void {
    app()->setLocale($locale);
    $manager = $this->createStaff();

    Livewire::actingAs($manager)
        ->test(Index::class)
        ->set('paginators.page', 99)
        ->assertDontSee(__('admin.currencies.empty'))
        ->assertSee(__('admin.currencies.empty_page'))
        ->assertSee('wire:click="resetPage"', false)
        ->call('resetPage')
        ->assertSee('data-label="'.__('admin.currencies.code').'"', false);
})->with(['en', 'nl']);

test('view-only staff are not told to add an unavailable currency', function (string $locale): void {
    app()->setLocale($locale);
    $viewer = $this->createStaff(permissions: ['currencies.view']);
    Currency::query()->delete();

    Livewire::actingAs($viewer)
        ->test(Index::class)
        ->assertSee(__('admin.currencies.empty'))
        ->assertSee(__('admin.currencies.empty_viewer_text'))
        ->assertDontSee(__('admin.currencies.empty_text'))
        ->assertDontSee('wire:click="create"', false);
})->with(['en', 'nl']);

test('currency creators can open the new form but cannot edit or set the base currency', function (): void {
    $creator = $this->createStaff(permissions: ['currencies.view', 'currencies.create']);

    Livewire::actingAs($creator)
        ->test(Index::class)
        ->assertSee('wire:click="create"', false)
        ->assertDontSee('wire:click="edit(', false)
        ->assertDontSee('wire:click="setAsBase(', false)
        ->assertDontSee('data-label="'.__('common.actions').'"', false)
        ->call('create')
        ->assertSee('wire:submit="save"', false);
});

test('currency editors keep the edit form and sync without gaining base settings', function (): void {
    $editor = $this->createStaff(permissions: ['currencies.view', 'currencies.update']);
    $usd = Currency::query()->where('code', 'USD')->firstOrFail();

    Livewire::actingAs($editor)
        ->test(Index::class)
        ->assertSee('wire:click="syncRates"', false)
        ->assertDontSee('wire:click="create"', false)
        ->assertDontSee('wire:click="setAsBase(', false)
        ->call('edit', $usd->id)
        ->assertSee('wire:submit="save"', false);
});

test('base settings managers keep the confirmed base action without gaining currency editing', function (): void {
    $settingsManager = $this->createStaff(permissions: ['currencies.view', 'settings.update']);
    $usd = Currency::query()->where('code', 'USD')->firstOrFail();

    Livewire::actingAs($settingsManager)
        ->test(Index::class)
        ->assertSee('wire:click="setAsBase('.$usd->id.')" wire:confirm="'.__('admin.currencies.set_base_confirm', ['code' => $usd->code]).'"', false)
        ->assertSee('data-label="'.__('common.actions').'"', false)
        ->assertDontSee('wire:click="edit(', false)
        ->assertDontSee('wire:click="syncRates"', false)
        ->assertDontSee('wire:click="create"', false);
});

test('currency status badges distinguish active and inactive rows', function (): void {
    $manager = $this->createStaff();
    Currency::query()->where('code', 'USD')->firstOrFail()->update(['is_active' => false]);

    Livewire::actingAs($manager)
        ->test(Index::class)
        ->assertSee('ag-badge--success', false)
        ->assertSee('ag-badge--muted', false);
});

test('currency list and form use the shared Admin hierarchy without dropping editing fields', function (): void {
    $manager = $this->createStaff();

    Livewire::actingAs($manager)->test(Index::class)
        ->assertSee('admin-page--currencies ag-list-page', false)
        ->assertSee('ag-table--currencies', false)
        ->assertSee('ag-currency-identity', false)
        ->call('create')
        ->assertSee('ag-catalog-form__heading', false)
        ->assertSee('ag-grid ag-grid--2', false)
        ->assertSee('wire:model="code"', false)
        ->assertSee('wire:model="name"', false)
        ->assertSee('wire:model="prefix"', false)
        ->assertSee('wire:model="suffix"', false)
        ->assertSee('wire:model.number="precision"', false)
        ->assertSee('wire:model="exchange_rate"', false)
        ->assertSee('wire:model="is_active"', false);
});
