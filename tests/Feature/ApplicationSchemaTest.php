<?php

declare(strict_types=1);

use App\Agovena\Installation\ApplicationSchemaStatus;
use App\Agovena\Installation\InstallationRequirements;
use App\Livewire\Admin\Customers\Properties as CustomerProperties;
use App\Models\CustomerPropertyDefinition;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\Support\SimulatesPendingSchema;

uses(CreatesStaff::class, SimulatesPendingSchema::class);

test('pending customer property migration is detected instead of assumed present', function () {
    expect(Schema::hasTable('customer_property_definitions'))->toBeTrue()
        ->and(app(ApplicationSchemaStatus::class)->isCurrent())->toBeTrue();

    $this->dropCustomerPropertySchema();
    app(ApplicationSchemaStatus::class)->refresh();

    expect(Schema::hasTable('customer_property_definitions'))->toBeFalse()
        ->and(app(ApplicationSchemaStatus::class)->pending())
        ->toContain('2026_08_13_090000_create_customer_custom_properties_tables');
});

test('customer properties admin does not 500 when the schema is pending', function () {
    $staff = $this->createStaff();
    $this->dropCustomerPropertySchema();
    app(ApplicationSchemaStatus::class)->refresh();

    $this->actingAs($staff)
        ->get(route('admin.customers.properties'))
        ->assertStatus(503)
        ->assertSee(__('schema.update_required.heading'), false)
        ->assertSee('php artisan agovena:upgrade', false)
        ->assertDontSee('SQLSTATE', false)
        ->assertDontSee('no such table', false);
});

test('registration and checkout do not 500 when custom property schema is pending', function () {
    $this->dropCustomerPropertySchema();
    app(ApplicationSchemaStatus::class)->refresh();

    $this->get(route('register'))
        ->assertStatus(503)
        ->assertSee(__('schema.update_required.heading'), false);

    $this->get(route('storefront.checkout'))
        ->assertStatus(503)
        ->assertDontSee('SQLSTATE', false);
});

test('doctor fails when application migrations are pending', function () {
    $this->dropCustomerPropertySchema();
    app(ApplicationSchemaStatus::class)->refresh();

    $this->artisan('agovena:doctor')->assertFailed();

    $migrations = collect(app(InstallationRequirements::class)->checks())
        ->firstWhere('id', 'migrations');

    expect($migrations)->not->toBeNull()
        ->and($migrations->passed)->toBeFalse()
        ->and($migrations->detail)->toContain('2026_08_13_090000_create_customer_custom_properties_tables');
});

test('agovena upgrade applies pending schema and restores the properties screen', function () {
    $staff = $this->createStaff();
    $this->dropCustomerPropertySchema();
    app(ApplicationSchemaStatus::class)->refresh();

    $this->artisan('agovena:upgrade')->assertSuccessful();

    expect(Schema::hasTable('customer_property_definitions'))->toBeTrue()
        ->and(app(ApplicationSchemaStatus::class)->isCurrent())->toBeTrue();

    $this->actingAs($staff)
        ->get(route('admin.customers.properties'))
        ->assertOk()
        ->assertSee(__('admin.customer_properties.title'), false)
        ->assertDontSee('SQLSTATE', false);

    Livewire::actingAs($staff)
        ->test(CustomerProperties::class)
        ->assertOk()
        ->call('create')
        ->set('label', 'VAT number')
        ->set('key', 'vat_number')
        ->set('type', 'text')
        ->call('save')
        ->assertHasNoErrors();

    expect(CustomerPropertyDefinition::query()->where('key', 'vat_number')->exists())->toBeTrue();
});

test('update-required page redirects once the schema is current', function () {
    $this->get(route('schema.update-required'))->assertRedirect(route('storefront.home'));
});
