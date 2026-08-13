<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Customer\Properties\CustomerPropertyService;
use App\Agovena\Invoices\IssueInvoiceFromOrder;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Privacy\AnonymizeCustomer;
use App\Agovena\Privacy\ExportCustomerData;
use App\Enums\CustomerPropertyType;
use App\Livewire\Admin\Customers\Properties as CustomerProperties;
use App\Livewire\Customer\Auth\Register;
use App\Models\Customer;
use App\Models\CustomerPropertyDefinition;
use App\Models\Product;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function makeVatProperty(array $overrides = []): CustomerPropertyDefinition
{
    return CustomerPropertyDefinition::query()->create(array_merge([
        'key' => 'vat_number',
        'label' => 'VAT number',
        'type' => CustomerPropertyType::Text,
        'is_required' => true,
        'constraints' => ['max_length' => 32],
        'options' => [],
        'sort' => 1,
        'is_active' => true,
        'show_on_registration' => true,
        'show_on_checkout' => true,
        'show_on_account' => true,
        'show_on_invoice' => true,
        'customer_editable' => true,
        'staff_editable' => true,
        'internal_only' => false,
    ], $overrides));
}

test('staff can create a customer property and cannot use reserved keys', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(CustomerProperties::class)
        ->call('create')
        ->set('label', 'VAT number')
        ->set('key', 'email')
        ->set('type', 'text')
        ->call('save')
        ->assertHasErrors(['key']);

    Livewire::actingAs($staff)
        ->test(CustomerProperties::class)
        ->call('create')
        ->set('label', 'VAT number')
        ->set('key', 'vat_number')
        ->set('type', 'text')
        ->set('show_on_invoice', true)
        ->set('show_on_registration', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(CustomerPropertyDefinition::query()->where('key', 'vat_number')->exists())->toBeTrue();
});

test('registration collects required custom properties', function () {
    makeVatProperty();

    Livewire::test(Register::class)
        ->set('name', 'Ada Customer')
        ->set('email', 'ada-props@example.com')
        ->set('password', 'password-secret')
        ->set('password_confirmation', 'password-secret')
        ->call('register')
        ->assertHasErrors(['propertyValues.vat_number']);

    Livewire::test(Register::class)
        ->set('name', 'Ada Customer')
        ->set('email', 'ada-props@example.com')
        ->set('password', 'password-secret')
        ->set('password_confirmation', 'password-secret')
        ->set('propertyValues.vat_number', 'NL123456789B01')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('customer.verification.notice'));

    $customer = Customer::query()->where('email', 'ada-props@example.com')->first();
    $values = app(CustomerPropertyService::class)->valuesMap($customer);

    expect($customer)->not->toBeNull()
        ->and($values['vat_number'] ?? null)->toBe('NL123456789B01');
});

test('invoice visible properties are snapshotted and do not change later', function () {
    makeVatProperty(['show_on_registration' => false]);
    $customer = Customer::factory()->create();
    app(CustomerPropertyService::class)->save(
        $customer,
        CustomerPropertyDefinition::query()->get(),
        ['vat_number' => 'NL111111111B01'],
        'customer',
    );

    $product = Product::factory()->active()->create(['price_amount' => 1000]);
    app(CartService::class)->add($product->id, 1);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => AddressData::fromArray([
            'name' => $customer->name,
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
        'custom_properties' => ['vat_number' => 'NL111111111B01'],
    ]);

    expect($order->custom_properties_snapshot)->toBe([
        ['key' => 'vat_number', 'label' => 'VAT number', 'value' => 'NL111111111B01'],
    ]);

    $staff = $this->createStaff();
    app(RecordManualPayment::class)->handle($order, $staff, 'PAY-VAT');
    $invoice = app(IssueInvoiceFromOrder::class)->handle($order->fresh());

    app(CustomerPropertyService::class)->save(
        $customer,
        CustomerPropertyDefinition::query()->get(),
        ['vat_number' => 'NL999999999B01'],
        'customer',
    );

    expect($invoice->fresh()->custom_properties_snapshot)->toBe([
        ['key' => 'vat_number', 'label' => 'VAT number', 'value' => 'NL111111111B01'],
    ]);
});

test('gdpr export includes custom properties and anonymize wipes them', function () {
    makeVatProperty(['is_required' => false, 'show_on_registration' => false]);
    $customer = Customer::factory()->create();
    app(CustomerPropertyService::class)->save(
        $customer,
        CustomerPropertyDefinition::query()->get(),
        ['vat_number' => 'NL555'],
        'customer',
    );

    $export = app(ExportCustomerData::class)->handle($customer);
    expect($export['custom_properties'][0]['value'] ?? null)->toBe('NL555');

    app(AnonymizeCustomer::class)->handle($customer);

    expect(app(CustomerPropertyService::class)->valuesMap($customer->fresh()))->toBe([]);
});
