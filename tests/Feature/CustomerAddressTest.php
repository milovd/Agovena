<?php

declare(strict_types=1);

use App\Agovena\Customer\AddressData;
use App\Agovena\Customer\SaveCustomerAddress;
use App\Livewire\Customer\Account\Addresses;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Livewire\Livewire;

test('customer can save and delete an address', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer->user)
        ->test(Addresses::class)
        ->set('name', 'Ada Lovelace')
        ->set('line1', 'Analytical Engine 1')
        ->set('city', 'London')
        ->set('postal_code', 'SW1A 1AA')
        ->set('country', 'GB')
        ->set('is_default_billing', true)
        ->call('save')
        ->assertHasNoErrors();

    $address = CustomerAddress::query()->where('customer_id', $customer->id)->first();

    expect($address)->not->toBeNull()
        ->and($address->line1)->toBe('Analytical Engine 1')
        ->and($address->is_default_billing)->toBeTrue();

    Livewire::actingAs($customer->user)
        ->test(Addresses::class)
        ->call('delete', $address->id)
        ->assertHasNoErrors();

    expect(CustomerAddress::query()->whereKey($address->id)->exists())->toBeFalse();
});

test('setting a default billing address clears previous default', function () {
    $customer = Customer::factory()->create();
    $first = app(SaveCustomerAddress::class)->handle(
        $customer,
        AddressData::fromArray([
            'name' => 'First',
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
        ['is_default_billing' => true],
    );

    $second = app(SaveCustomerAddress::class)->handle(
        $customer,
        AddressData::fromArray([
            'name' => 'Second',
            'line1' => 'Street 2',
            'city' => 'Rotterdam',
            'postal_code' => '3000 BB',
            'country' => 'NL',
        ]),
        ['is_default_billing' => true],
    );

    expect($first->fresh()->is_default_billing)->toBeFalse()
        ->and($second->fresh()->is_default_billing)->toBeTrue();
});
