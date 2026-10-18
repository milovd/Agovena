<?php

use App\Livewire\Admin\Settings\Hub;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('settings forms group registered fields in a shared responsive section without losing controls', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Hub::class)
        ->assertSee('class="ag-card ag-form ag-form--constrained ag-settings-form"', false)
        ->assertSee('aria-labelledby="settings-group-', false)
        ->assertSee('ag-settings-form__header', false)
        ->assertSee('class="ag-settings-form__fields"', false)
        ->assertSee('wire:model="values.site_name"', false)
        ->assertSee('wire:model="values.base_currency"', false)
        ->assertSee('wire:submit="save"', false)
        ->set('tab', 'store')
        ->assertSee('class="ag-settings-form__fields"', false)
        ->assertSee('wire:model="values.customer_registration"', false)
        ->assertSee('wire:model="values.prices_include_tax"', false)
        ->assertSee('wire:submit="save"', false)
        ->set('tab', 'branding')
        ->assertSee('wire:model="uploads.logo_path"', false)
        ->assertSee('wire:model="uploads.favicon_path"', false);
});

test('view-only settings show the same card with non-editable values', function () {
    $viewer = $this->createStaff([], ['settings.view', 'dashboard.view']);

    Livewire::actingAs($viewer)
        ->test(Hub::class)
        ->assertSee('class="ag-card ag-form ag-form--constrained ag-settings-form"', false)
        ->assertSee('aria-labelledby="settings-group-', false)
        ->assertSee('wire:model="values.site_name"', false)
        ->assertSee('readonly', false)
        ->assertDontSee('wire:submit="save"', false);
});

test('branding upload help never recommends the SVG format rejected by validation', function () {
    foreach (['en', 'nl'] as $locale) {
        app()->setLocale($locale);
        expect(__('admin.settings.image_hint'))->not->toContain('SVG');
        expect(__('admin.settings.field_help.logo_path'))->not->toContain('SVG');
    }
});
