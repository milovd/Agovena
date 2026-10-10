<?php

declare(strict_types=1);

use App\Agovena\Physical\Enums\ShippingMethodType;
use App\Agovena\Physical\Http\Livewire\Admin\MethodsIndex;
use App\Agovena\Physical\Http\Livewire\Admin\ZonesIndex;
use App\Agovena\Physical\Models\ShippingMethod;
use App\Agovena\Physical\Models\ShippingZone;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('shipping method form retains real conditional inputs in a section card', function () {
    $staff = $this->createStaff();
    $page = Livewire::actingAs($staff)->test(MethodsIndex::class)
        ->assertSee('c-shipping__form', false)
        ->assertSee('wire:submit="save"', false)
        ->assertSee('wire:model="name"', false)
        ->assertSee('wire:model="code"', false)
        ->assertSee('wire:model.live="type"', false)
        ->assertSee('wire:model="zone_id"', false)
        ->assertSee('wire:model="currency"', false)
        ->assertSee('wire:model="amount"', false)
        ->assertSee('wire:model="min_subtotal"', false)
        ->assertSee('wire:model="is_active"', false)
        ->assertSee('href="'.route('admin.shipping.zones').'"', false)
        ->assertDontSee('wire:model="tiers_json"', false);

    $page->set('type', 'price')->assertSee(__('shipping::admin.price_tiers_hint'))
        ->assertSee('wire:model="tiers_json"', false)
        ->assertDontSee('wire:model="amount"', false);
    $page->set('type', 'weight')->assertSee(__('shipping::admin.weight_tiers_hint'))
        ->assertSee('wire:model="tiers_json"', false);
});

test('shipping method list has readable identity, details, status and named delete action', function () {
    $staff = $this->createStaff();
    $zone = ShippingZone::query()->create(['name' => 'Benelux', 'countries' => ['NL', 'BE'], 'is_active' => true, 'sort' => 1]);
    $method = ShippingMethod::query()->create([
        'name' => 'Standard parcel', 'code' => 'standard-parcel', 'type' => ShippingMethodType::Zone,
        'zone_id' => $zone->id, 'config' => ['amount' => 695], 'currency' => 'EUR',
        'is_active' => true, 'sort' => 1,
    ]);

    $html = Livewire::actingAs($staff)->test(MethodsIndex::class)->html();
    expect($html)->toContain('c-shipping__table')
        ->toContain('Standard parcel')->toContain('standard-parcel')
        ->toContain(__('shipping::admin.types.zone'))->toContain('Benelux')
        ->toContain('data-label="'.__('shipping::admin.type').'"')
        ->toContain('data-label="'.__('shipping::admin.zone').'"')
        ->toContain('data-label="'.__('common.status').'"')
        ->toContain('ag-badge--success')
        ->toContain('wire:click="delete('.$method->id.')"')
        ->toContain('wire:confirm="'.__('shipping::admin.delete_method_confirm', ['name' => $method->name]).'"')
        ->toContain('aria-label="'.__('common.delete').': Standard parcel"');
});

test('shipping zones use the same form and readable row composition', function () {
    $staff = $this->createStaff();
    $zone = ShippingZone::query()->create(['name' => 'Benelux', 'countries' => ['NL', 'BE'], 'is_active' => false, 'sort' => 1]);

    $page = Livewire::actingAs($staff)->test(ZonesIndex::class)
        ->assertSee('c-shipping__form', false)
        ->assertSee('wire:submit="save"', false)
        ->assertSee('wire:model="name"', false)
        ->assertSee('wire:model="countries"', false)
        ->assertSee('wire:model="is_active"', false)
        ->assertSee(__('shipping::admin.countries_hint'))
        ->assertSee('href="'.route('admin.shipping.methods').'"', false);

    expect($page->html())->toContain('c-shipping__table')
        ->toContain('Benelux')->toContain('NL, BE')
        ->toContain('data-label="'.__('shipping::admin.countries_column').'"')
        ->toContain('data-label="'.__('common.status').'"')
        ->toContain('ag-badge--muted')
        ->toContain('wire:click="delete('.$zone->id.')"')
        ->toContain('wire:confirm="'.__('shipping::admin.delete_zone_confirm', ['name' => $zone->name]).'"')
        ->toContain('aria-label="'.__('common.delete').': Benelux"');
});

test('shipping view-only staff see both lists and links without management presentation', function () {
    $staff = $this->createStaff([], ['shipping.view']);
    ShippingMethod::query()->create([
        'name' => 'Viewable method', 'code' => 'viewable-method', 'type' => ShippingMethodType::Flat,
        'config' => ['amount' => 0], 'currency' => 'EUR', 'is_active' => true, 'sort' => 1,
    ]);
    ShippingZone::query()->create(['name' => 'Viewable zone', 'countries' => ['BE'], 'is_active' => true, 'sort' => 1]);

    Livewire::actingAs($staff)->test(MethodsIndex::class)
        ->assertSee('Viewable method')->assertSee('href="'.route('admin.shipping.zones').'"', false)
        ->assertDontSee('wire:submit="save"', false)->assertDontSee('wire:click="delete(', false);
    Livewire::actingAs($staff)->test(ZonesIndex::class)
        ->assertSee('Viewable zone')->assertSee('href="'.route('admin.shipping.methods').'"', false)
        ->assertDontSee('wire:submit="save"', false)->assertDontSee('wire:click="delete(', false);
});

test('shipping form validation remains wired to the existing Livewire save methods', function () {
    $staff = $this->createStaff();
    Livewire::actingAs($staff)->test(MethodsIndex::class)
        ->set('name', '')->set('code', '')->call('save')->assertHasErrors(['name' => 'required', 'code' => 'required']);
    Livewire::actingAs($staff)->test(ZonesIndex::class)
        ->set('name', '')->set('countries', '')->call('save')->assertHasErrors(['name' => 'required', 'countries' => 'required']);
});
