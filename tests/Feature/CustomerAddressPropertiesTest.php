<?php

declare(strict_types=1);

use App\Agovena\Api\ApiTokenAbilities;
use App\Agovena\Auth\ConfirmsRecentPassword;
use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Customer\Properties\CustomerPropertyService;
use App\Enums\CustomerPropertyType;
use App\Livewire\Admin\Customers\Index as CustomersIndex;
use App\Livewire\Admin\Customers\Show as AdminCustomerShow;
use App\Livewire\Customer\Account\Addresses;
use App\Livewire\Customer\Auth\Register;
use App\Livewire\Storefront\CheckoutPage;
use App\Models\Customer;
use App\Models\CustomerPropertyDefinition;
use App\Models\Product;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

/** @return Collection<int, CustomerPropertyDefinition> */
function paymenterAddressDefinitions(): Collection
{
    return CustomerPropertyDefinition::query()
        ->whereIn('key', [
            'phone',
            'company_name',
            'country',
            'address',
            'address2',
            'city',
            'state',
            'zip',
        ])
        ->orderBy('sort')
        ->get();
}

test('Paymenter address fields are customer properties used during registration', function () {
    $definitions = paymenterAddressDefinitions()->keyBy('key');

    expect($definitions->keys()->all())->toBe([
        'phone',
        'company_name',
        'country',
        'address',
        'address2',
        'city',
        'state',
        'zip',
    ])
        ->and($definitions['country']->type)->toBe(CustomerPropertyType::Country)
        ->and($definitions['phone']->is_required)->toBeTrue()
        ->and($definitions['address2']->is_required)->toBeFalse()
        ->and($definitions['company_name']->show_on_invoice)->toBeTrue()
        ->and($definitions['phone']->show_on_invoice)->toBeFalse();

    Livewire::test(Register::class)
        ->set('name', 'Ada Customer')
        ->set('email', 'ada-address-properties@example.com')
        ->set('password', 'password-secret')
        ->set('password_confirmation', 'password-secret')
        ->set('propertyValues.phone', '+31 20 123 4567')
        ->set('propertyValues.company_name', 'Analytical Engines BV')
        ->set('propertyValues.country', 'NL')
        ->set('propertyValues.address', 'Engine Street 1')
        ->set('propertyValues.address2', 'Unit 2')
        ->set('propertyValues.city', 'Amsterdam')
        ->set('propertyValues.state', 'Noord-Holland')
        ->set('propertyValues.zip', '1000 AA')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('customer.verification.notice'));

    $customer = Customer::query()->where('email', 'ada-address-properties@example.com')->firstOrFail();
    $values = app(CustomerPropertyService::class)->valuesMap($customer);

    expect($values)->toMatchArray([
        'phone' => '+31 20 123 4567',
        'company_name' => 'Analytical Engines BV',
        'country' => 'NL',
        'address' => 'Engine Street 1',
        'address2' => 'Unit 2',
        'city' => 'Amsterdam',
        'state' => 'Noord-Holland',
        'zip' => '1000 AA',
    ]);
});

test('checkout hydrates billing from customer properties and snapshots them for invoices', function () {
    $customer = Customer::factory()->create([
        'name' => 'Property Buyer',
        'email' => 'property-buyer@example.com',
    ]);
    $product = Product::factory()->active()->create(['price_amount' => 1000]);
    app(CartService::class)->add($product->id, 1);

    app(CustomerPropertyService::class)->save(
        $customer,
        paymenterAddressDefinitions(),
        [
            'phone' => '+31 10 555 0101',
            'company_name' => 'Property Commerce BV',
            'country' => 'NL',
            'address' => 'Property Lane 10',
            'address2' => 'Floor 2',
            'city' => 'Rotterdam',
            'state' => 'Zuid-Holland',
            'zip' => '3000 BB',
        ],
        'customer',
    );

    Livewire::actingAs($customer->user)
        ->test(CheckoutPage::class)
        ->assertSet('billing_name', 'Property Buyer')
        ->assertSet('billing_company', 'Property Commerce BV')
        ->assertSet('billing_line1', 'Property Lane 10')
        ->assertSet('billing_line2', 'Floor 2')
        ->assertSet('billing_city', 'Rotterdam')
        ->assertSet('billing_region', 'Zuid-Holland')
        ->assertSet('billing_postal_code', '3000 BB')
        ->assertSet('billing_country', 'NL')
        ->assertSet('billing_phone', '+31 10 555 0101');

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => AddressData::fromArray([
            'name' => 'Property Buyer',
            'company' => 'Checkout Company BV',
            'line1' => 'Checkout Lane 5',
            'line2' => 'Suite 4',
            'city' => 'Utrecht',
            'region' => 'Utrecht',
            'postal_code' => '3500 CC',
            'country' => 'NL',
            'phone' => '+31 30 111 2222',
        ]),
    ]);

    expect(collect($order->custom_properties_snapshot)->pluck('key')->all())->toBe([
        'company_name',
        'country',
        'address',
        'city',
        'state',
        'zip',
    ])
        ->and(app(CustomerPropertyService::class)->valuesMap($customer->fresh()))->toMatchArray([
            'company_name' => 'Checkout Company BV',
            'country' => 'NL',
            'address' => 'Checkout Lane 5',
            'address2' => 'Suite 4',
            'city' => 'Utrecht',
            'state' => 'Utrecht',
            'zip' => '3500 CC',
            'phone' => '+31 30 111 2222',
        ]);
});

test('customers index exposes edit and delete actions and delete anonymizes the account', function () {
    $staff = $this->createStaff();
    $customer = Customer::factory()->create([
        'name' => 'Delete Me',
        'email' => 'delete-me@example.com',
    ]);

    Livewire::actingAs($staff)
        ->test(CustomersIndex::class)
        ->assertSee(__('common.edit'), false)
        ->assertSee(__('common.delete'), false);

    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $staff->id,
    ]);

    Livewire::actingAs($staff)
        ->test(CustomersIndex::class)
        ->call('delete', $customer->id)
        ->assertHasNoErrors();

    expect($customer->fresh()->anonymized_at)->not->toBeNull()
        ->and($customer->fresh()->email)->toBe('deleted+'.$customer->id.'@anonymized.invalid');
});

test('account address editor saves the Paymenter properties as the primary address', function () {
    $customer = Customer::factory()->create([
        'name' => 'Address Property Customer',
        'email' => 'address-property@example.com',
    ]);

    Livewire::actingAs($customer->user)
        ->test(Addresses::class)
        ->set('propertyValues.phone', '+31 40 222 3333')
        ->set('propertyValues.company_name', 'Address Property BV')
        ->set('propertyValues.country', 'NL')
        ->set('propertyValues.address', 'Property Street 12')
        ->set('propertyValues.address2', 'Unit 3')
        ->set('propertyValues.city', 'Eindhoven')
        ->set('propertyValues.state', 'Noord-Brabant')
        ->set('propertyValues.zip', '5600 DD')
        ->set('is_default_billing', true)
        ->call('save')
        ->assertHasNoErrors();

    $customer->refresh();
    $values = app(CustomerPropertyService::class)->valuesMap($customer);

    expect($values)->toMatchArray([
        'phone' => '+31 40 222 3333',
        'company_name' => 'Address Property BV',
        'country' => 'NL',
        'address' => 'Property Street 12',
        'address2' => 'Unit 3',
        'city' => 'Eindhoven',
        'state' => 'Noord-Brabant',
        'zip' => '5600 DD',
    ])
        ->and($customer->addresses()->where('is_default_billing', true)->value('line1'))->toBe('Property Street 12');
});

test('address API reads and writes the Paymenter customer properties', function () {
    $customer = Customer::factory()->create([
        'name' => 'API Property Customer',
        'email' => 'api-property@example.com',
    ]);
    app(CustomerPropertyService::class)->save(
        $customer,
        paymenterAddressDefinitions(),
        [
            'phone' => '+31 50 444 5555',
            'country' => 'NL',
            'address' => 'API Street 20',
            'city' => 'Groningen',
            'state' => 'Groningen',
            'zip' => '9700 EE',
        ],
        'customer',
    );

    Sanctum::actingAs($customer->user, [ApiTokenAbilities::ADDRESSES_READ]);
    $this->getJson('/api/v1/addresses')
        ->assertOk()
        ->assertJsonPath('data.0.id', null)
        ->assertJsonPath('data.0.properties.address', 'API Street 20')
        ->assertJsonPath('data.0.properties.zip', '9700 EE');

    Sanctum::actingAs($customer->user, [ApiTokenAbilities::ADDRESSES_CREATE]);
    $this->postJson('/api/v1/addresses', [
        'name' => 'API Property Customer',
        'line1' => 'API Street 21',
        'city' => 'Groningen',
        'postal_code' => '9700 FF',
        'country' => 'NL',
        'properties' => [
            'address' => 'API Street 21',
            'city' => 'Groningen',
            'state' => 'Groningen',
            'zip' => '9700 FF',
            'country' => 'NL',
            'phone' => '+31 50 444 5556',
        ],
        'is_default_billing' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.properties.address', 'API Street 21');

    expect(app(CustomerPropertyService::class)->valuesMap($customer->fresh()))
        ->toMatchArray([
            'address' => 'API Street 21',
            'zip' => '9700 FF',
            'phone' => '+31 50 444 5556',
        ]);
});

test('staff customer-property edits synchronize the primary address card', function () {
    $staff = $this->createStaff();
    $customer = Customer::factory()->create([
        'name' => 'Staff Property Customer',
        'email' => 'staff-property@example.com',
    ]);

    Livewire::actingAs($staff)
        ->test(AdminCustomerShow::class, ['customer' => $customer])
        ->call('selectPanel', 'profile')
        ->set('propertyValues.phone', '+31 70 666 7777')
        ->set('propertyValues.company_name', 'Staff Property BV')
        ->set('propertyValues.country', 'NL')
        ->set('propertyValues.address', 'Staff Street 30')
        ->set('propertyValues.city', 'The Hague')
        ->set('propertyValues.state', 'Zuid-Holland')
        ->set('propertyValues.zip', '2500 GG')
        ->call('saveProperties')
        ->assertHasNoErrors();

    expect($customer->addresses()->where('is_default_billing', true)->value('line1'))->toBe('Staff Street 30')
        ->and($customer->addresses()->where('is_default_billing', true)->value('company'))->toBe('Staff Property BV');
});
