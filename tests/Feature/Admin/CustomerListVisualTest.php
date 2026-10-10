<?php

declare(strict_types=1);

use App\Enums\CustomerPropertyType;
use App\Livewire\Admin\Customers\Index as CustomersIndex;
use App\Livewire\Admin\Customers\Properties as CustomerProperties;
use App\Models\Customer;
use App\Models\CustomerPropertyDefinition;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('customer rows retain data and filters with named icon actions and compact mobile details', function () {
    $staff = $this->createStaff();
    $customer = Customer::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada-list@example.test']);

    $page = Livewire::actingAs($staff)->test(CustomersIndex::class)
        ->assertSee('Ada Lovelace')
        ->assertSee('ada-list@example.test')
        ->assertSee('wire:model.live.debounce.300ms="search"', false)
        ->assertSee('wire:model.live="status"', false);

    $html = $page->html();
    expect($html)->toContain('ag-table--customers')
        ->toContain('data-label="'.__('admin.customers.spent_column').'"')
        ->toContain('wire:confirm="'.__('admin.customers.delete_confirm').'"')
        ->toContain('wire:click="delete('.$customer->id.')"')
        ->toContain('aria-label="'.__('admin.customers.edit_aria', ['name' => $customer->name]).'"')
        ->toContain('ag-icon-btn--danger')
        ->toContain('<svg');
});

test('customer action icons honor management permission and anonymized state', function () {
    $staff = $this->createStaff([], ['customers.view']);
    $customer = Customer::factory()->create(['name' => 'View Only Customer']);

    Livewire::actingAs($staff)->test(CustomersIndex::class)
        ->assertSee('aria-label="'.__('admin.customers.edit_aria', ['name' => $customer->name]).'"', false)
        ->assertDontSee('wire:click="delete('.$customer->id.')"', false)
        ->assertDontSee('wire:click="createUser"', false);

    $owner = $this->createStaff();
    $customer->user->forceFill(['anonymized_at' => now()])->save();
    Livewire::actingAs($owner)->test(CustomersIndex::class)
        ->assertDontSee('wire:click="delete('.$customer->id.')"', false);
});

test('account creation keeps all required inputs and role choices in a compact form section', function () {
    $staff = $this->createStaff();
    Livewire::actingAs($staff)->test(CustomersIndex::class)
        ->call('createUser')
        ->assertSee('ag-section ag-form ag-form--constrained ag-customer-form', false)
        ->assertSee('wire:submit="saveUser"', false)
        ->assertSee('wire:model="userName"', false)
        ->assertSee('wire:model="userEmail"', false)
        ->assertSee('wire:model="userPassword"', false)
        ->assertSee('wire:model="userRole"', false)
        ->assertSee('autocomplete="new-password"', false)
        ->assertSee('wire:click="cancelUser"', false);
});

test('populated property list keeps toggles and edit with a named icon action', function () {
    $staff = $this->createStaff();
    $property = CustomerPropertyDefinition::query()->create([
        'key' => 'tax_reference', 'label' => 'Tax reference', 'description' => 'For receipts',
        'type' => CustomerPropertyType::Text, 'options' => [], 'constraints' => [],
        'sort' => 1, 'is_active' => true, 'is_required' => false,
        'show_on_invoice' => true, 'customer_editable' => true, 'staff_editable' => true,
        'internal_only' => false, 'show_on_registration' => false, 'show_on_checkout' => false,
        'show_on_account' => true,
    ]);

    Livewire::actingAs($staff)->test(CustomerProperties::class)
        ->assertSee('Tax reference')
        ->assertSee('tax_reference')
        ->assertSee('data-label="'.__('admin.customer_properties.key').'"', false)
        ->assertSee('wire:click.prevent="toggleField('.$property->id.", 'customer_editable')\"", false)
        ->assertSee('wire:click.prevent="toggleField('.$property->id.", 'is_required')\"", false)
        ->assertSee('wire:click.prevent="toggleField('.$property->id.", 'show_on_invoice')\"", false)
        ->assertSee('class="ag-icon-btn" wire:click="edit('.$property->id.')"', false)
        ->assertSee('aria-label="'.__('common.edit').': Tax reference"', false);
});

test('property create and edit retain all fields and controls in the form system', function () {
    $staff = $this->createStaff();
    $page = Livewire::actingAs($staff)->test(CustomerProperties::class)->call('create');

    $page->assertSee('ag-customer-form', false)
        ->assertSee('wire:submit="save"', false)
        ->assertSee('wire:model="label"', false)
        ->assertSee('wire:model="key"', false)
        ->assertSee('wire:model.live="type"', false)
        ->assertSee('wire:model="validation"', false)
        ->assertSee('wire:model="description"', false)
        ->assertSee('wire:model="sort"', false);

    foreach (['show_on_registration', 'show_on_checkout', 'show_on_account', 'show_on_invoice', 'is_required', 'customer_editable', 'staff_editable', 'internal_only', 'is_active'] as $field) {
        $page->assertSee('wire:model="'.$field.'"', false);
    }

    $page->set('type', 'select')->assertSee('wire:click="addOption"', false);

    $existing = CustomerPropertyDefinition::query()->where('key', 'phone')->firstOrFail();
    $page->call('edit', $existing->id)
        ->assertSee('wire:model="key" required disabled', false)
        ->assertSee('wire:confirm="'.__('admin.customer_properties.delete_confirm').'"', false)
        ->assertSee('wire:click="delete('.$existing->id.')"', false);
});
