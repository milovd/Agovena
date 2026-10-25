<?php

declare(strict_types=1);

use App\Agovena\Customer\Properties\CustomerAddressProperties;
use App\Agovena\Customer\Properties\CustomerPropertyService;
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

it('does not recreate default properties the merchant deleted', function (): void {
    CustomerPropertyDefinition::query()->whereIn('key', ['phone', 'country', 'zip'])->delete();
    CustomerPropertyDefinition::query()->where('key', 'city')->update(['label' => 'Town', 'is_required' => false]);

    runDefaultCustomerPropertyRepair();

    expect(CustomerPropertyDefinition::query()->whereIn('key', ['phone', 'country', 'zip'])->exists())->toBeFalse()
        ->and(CustomerPropertyDefinition::query()->where('key', 'city')->first())
        ->label->toBe('Town')
        ->is_required->toBeFalse();
});

it('lets the merchant edit and delete every default property but never renames a key', function (): void {
    $staff = $this->createStaff([], ['customers.view', 'customers.manage']);
    $country = CustomerPropertyDefinition::query()->where('key', 'country')->sole();

    expect(method_exists(CustomerAddressProperties::class, 'isSystemKey'))->toBeFalse();

    Livewire::actingAs($staff)->test(Properties::class)
        ->call('edit', $country->id)
        ->assertSee('wire:click="deleteProperty('.$country->id.')"', false)
        ->assertDontSee('wire:model.live="type" disabled', false)
        ->set('key', 'land')
        ->set('label', 'Land')
        ->set('is_required', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($country->fresh())
        ->key->toBe('country')
        ->label->toBe('Land')
        ->is_required->toBeFalse();

    foreach (DEFAULT_CUSTOMER_PROPERTY_KEYS as $key) {
        $id = CustomerPropertyDefinition::query()->where('key', $key)->value('id');
        Livewire::actingAs($staff)->test(Properties::class)
            ->call('deleteProperty', $id)
            ->assertHasNoErrors();
    }
    expect(CustomerPropertyDefinition::query()->whereIn('key', DEFAULT_CUSTOMER_PROPERTY_KEYS)->exists())->toBeFalse();
});

it('falls back cleanly when the address properties were deleted', function (): void {
    $customer = Customer::factory()->create();
    CustomerPropertyDefinition::query()->whereIn('key', DEFAULT_CUSTOMER_PROPERTY_KEYS)->delete();

    expect(app(CustomerPropertyService::class)->addressFromProperties($customer))->toBeNull();
});

it('shows edit and delete row actions for every property, without a system badge', function (): void {
    $staff = $this->createStaff([], ['customers.view', 'customers.manage']);
    $definitions = CustomerPropertyDefinition::query()->get();

    $html = Livewire::actingAs($staff)->test(Properties::class)->html();

    expect(substr_count($html, 'ag-row-actions'))->toBe($definitions->count())
        ->and(substr_count($html, 'ag-icon-btn ag-icon-btn--danger'))->toBe($definitions->count())
        ->and($html)->toContain('wire:confirm="'.e(__('admin.customer_properties.delete_confirm')).'"')
        ->and($html)->not->toContain('ag-property-system-badge');
    foreach ($definitions as $definition) {
        expect($html)->toContain('wire:click="deleteProperty('.$definition->id.')"');
    }
});

it('does not highlight table rows because a switch is on', function (): void {
    $css = file_get_contents(resource_path('css/admin/components/_table.css'));

    expect($css)->toContain('.ag-table tbody tr:has(> td input[type="checkbox"]:not([role="switch"]):checked)')
        ->not->toContain('.ag-table tbody tr:has(> td input[type="checkbox"]:checked)');
});
