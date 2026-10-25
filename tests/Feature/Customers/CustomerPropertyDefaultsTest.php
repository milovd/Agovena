<?php

declare(strict_types=1);

use App\Agovena\Customer\Properties\CustomerAddressProperties;
use App\Livewire\Admin\Customers\Properties;
use App\Models\Customer;
use App\Models\CustomerPropertyDefinition;
use App\Models\CustomerPropertyValue;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

const DEFAULT_CUSTOMER_PROPERTY_KEYS = ['phone', 'company_name', 'country', 'address', 'address2', 'city', 'state', 'zip'];

function runDefaultCustomerPropertyRepair(): void
{
    $migration = require database_path('migrations/2026_10_25_120000_restore_default_customer_property_definitions.php');
    $migration->up();
}

it('keeps the default and merchant customer property definitions during a forced demo reseed', function (): void {
    $custom = CustomerPropertyDefinition::query()->create([
        'key' => 'vat_number', 'label' => 'VAT number', 'type' => 'text', 'is_active' => true,
    ]);
    $customer = Customer::factory()->create();
    CustomerPropertyValue::query()->create([
        'customer_id' => $customer->id,
        'definition_id' => CustomerPropertyDefinition::query()->where('key', 'phone')->value('id'),
        'value' => '+3200000000',
    ]);

    expect(Artisan::call('agovena:seed-demo', ['--force' => true, '--skip-accounts' => true]))->toBe(0, Artisan::output());

    // Definitions are store configuration, not demo data; customer values go with the customers.
    expect(CustomerPropertyDefinition::query()->whereIn('key', DEFAULT_CUSTOMER_PROPERTY_KEYS)->count())->toBe(8)
        ->and(CustomerPropertyDefinition::query()->whereKey($custom->id)->exists())->toBeTrue()
        ->and(CustomerPropertyValue::query()->where('customer_id', $customer->id)->exists())->toBeFalse();
});

it('restores all default customer properties when an earlier reseed wiped them', function (): void {
    CustomerPropertyDefinition::query()->delete();

    runDefaultCustomerPropertyRepair();
    runDefaultCustomerPropertyRepair();

    $definitions = CustomerPropertyDefinition::query()->whereIn('key', DEFAULT_CUSTOMER_PROPERTY_KEYS)->get()->keyBy('key');
    expect($definitions)->toHaveCount(8)
        ->and($definitions['country']->is_required)->toBeTrue()
        ->and($definitions['company_name']->show_on_invoice)->toBeTrue()
        ->and($definitions['address2']->is_required)->toBeFalse();
});

it('only restores missing system address properties when the merchant removed optional ones', function (): void {
    CustomerPropertyDefinition::query()->whereIn('key', ['phone', 'company_name', 'zip'])->delete();
    CustomerPropertyDefinition::query()->where('key', 'city')->update(['label' => 'Town', 'is_required' => false]);

    runDefaultCustomerPropertyRepair();

    expect(CustomerPropertyDefinition::query()->where('key', 'zip')->exists())->toBeTrue()
        ->and(CustomerPropertyDefinition::query()->whereIn('key', ['phone', 'company_name'])->exists())->toBeFalse()
        ->and(CustomerPropertyDefinition::query()->where('key', 'city')->first())
        ->label->toBe('Town')
        ->is_required->toBeFalse();
});

it('locks the key, type and existence of system address properties but keeps their settings editable', function (): void {
    $staff = $this->createStaff([], ['customers.view', 'customers.manage']);
    $country = CustomerPropertyDefinition::query()->where('key', 'country')->sole();

    expect(CustomerAddressProperties::SYSTEM_KEYS)->toBe(['country', 'address', 'address2', 'city', 'state', 'zip']);

    Livewire::actingAs($staff)->test(Properties::class)
        ->call('deleteProperty', $country->id)
        ->assertHasErrors(['property']);
    expect(CustomerPropertyDefinition::query()->whereKey($country->id)->exists())->toBeTrue();

    Livewire::actingAs($staff)->test(Properties::class)
        ->call('edit', $country->id)
        ->assertDontSee('wire:click="deleteProperty('.$country->id.')"', false)
        ->assertSee('id="property-type" class="ag-select" wire:model.live="type" disabled', false)
        ->set('key', 'land')
        ->set('type', 'text')
        ->set('label', 'Land')
        ->set('is_required', false)
        ->set('show_on_invoice', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($country->fresh())
        ->key->toBe('country')
        ->type->value->toBe('country')
        ->label->toBe('Land')
        ->is_required->toBeFalse()
        ->show_on_invoice->toBeFalse();

    Livewire::actingAs($staff)->test(Properties::class)
        ->call('toggleField', $country->id, 'is_required')
        ->assertHasNoErrors();
    expect($country->fresh()->is_required)->toBeTrue();
});

it('keeps phone and company as ordinary deletable properties and never renames any key on update', function (): void {
    $staff = $this->createStaff([], ['customers.view', 'customers.manage']);
    $phone = CustomerPropertyDefinition::query()->where('key', 'phone')->sole();

    Livewire::actingAs($staff)->test(Properties::class)
        ->call('edit', $phone->id)
        ->set('key', 'mobile')
        ->call('save')
        ->assertHasNoErrors();
    expect($phone->fresh()->key)->toBe('phone');

    Livewire::actingAs($staff)->test(Properties::class)
        ->call('deleteProperty', $phone->id)
        ->assertHasNoErrors();
    expect(CustomerPropertyDefinition::query()->whereKey($phone->id)->exists())->toBeFalse();
});

it('shows a delete row action only for properties that can be deleted', function (): void {
    $staff = $this->createStaff([], ['customers.view', 'customers.manage']);
    $definitions = CustomerPropertyDefinition::query()->get()->keyBy('key');

    $html = Livewire::actingAs($staff)->test(Properties::class)->html();

    expect(substr_count($html, 'ag-row-actions'))->toBe($definitions->count())
        ->and($html)->toContain('wire:click="deleteProperty('.$definitions['phone']->id.')"')
        ->and($html)->toContain('wire:click="deleteProperty('.$definitions['company_name']->id.')"')
        ->and($html)->toContain('wire:confirm="'.e(__('admin.customer_properties.delete_confirm')).'"');
    foreach (CustomerAddressProperties::SYSTEM_KEYS as $key) {
        expect($html)->not->toContain('wire:click="deleteProperty('.$definitions[$key]->id.')"');
    }
});

it('does not highlight table rows because a switch is on', function (): void {
    $css = file_get_contents(resource_path('css/admin/components/_table.css'));

    expect($css)->toContain('.ag-table tbody tr:has(> td input[type="checkbox"]:not([role="switch"]):checked)')
        ->not->toContain('.ag-table tbody tr:has(> td input[type="checkbox"]:checked)');
});

it('marks system address properties in the Admin table', function (): void {
    $staff = $this->createStaff([], ['customers.view', 'customers.manage']);

    $html = Livewire::actingAs($staff)->test(Properties::class)->html();

    expect(substr_count($html, 'ag-property-system-badge'))->toBe(count(CustomerAddressProperties::SYSTEM_KEYS))
        ->and(__('admin.customer_properties.system', [], 'nl'))->not->toBe('admin.customer_properties.system');
});
