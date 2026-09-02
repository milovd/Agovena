<?php

declare(strict_types=1);

use App\Agovena\Customer\Properties\CustomerPropertyService;
use App\Agovena\Invoices\IssueCreditNote;
use App\Enums\CustomerPropertyType;
use App\Enums\InvoiceStatus;
use App\Livewire\Customer\Account\OrderShow;
use App\Models\Customer;
use App\Models\CustomerPropertyDefinition;
use App\Models\Invoice;
use App\Models\Order;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('order and invoice api resources expose their immutable address and property snapshots', function () {
    $customer = Customer::factory()->create();
    $snapshot = [
        ['key' => 'vat_number', 'label' => 'VAT number', 'value' => 'NL111111111B01'],
        ['key' => 'address', 'label' => 'Address', 'value' => 'Order Street 1'],
    ];
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'customer_name' => 'Order Customer',
        'customer_email' => 'order-customer@example.test',
        'billing_name' => 'Billing Customer',
        'billing_company' => 'Billing BV',
        'billing_line1' => 'Order Street 1',
        'billing_line2' => 'Suite 2',
        'billing_city' => 'Amsterdam',
        'billing_region' => 'Noord-Holland',
        'billing_postal_code' => '1000 AA',
        'billing_country' => 'NL',
        'billing_phone' => '+31 20 123 4567',
        'shipping_name' => 'Shipping Customer',
        'shipping_company' => 'Shipping BV',
        'shipping_line1' => 'Delivery Road 9',
        'shipping_line2' => 'Dock 4',
        'shipping_city' => 'Rotterdam',
        'shipping_region' => 'Zuid-Holland',
        'shipping_postal_code' => '3000 BB',
        'shipping_country' => 'NL',
        'shipping_phone' => '+31 10 765 4321',
        'custom_properties_snapshot' => $snapshot,
    ]);
    $invoice = Invoice::query()->create([
        'number' => 'INV-SNAPSHOT-00001',
        'status' => InvoiceStatus::Issued,
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'customer_name' => 'Order Customer',
        'customer_email' => 'order-customer@example.test',
        'billing_name' => 'Billing Customer',
        'billing_company' => 'Billing BV',
        'billing_line1' => 'Order Street 1',
        'billing_line2' => 'Suite 2',
        'billing_city' => 'Amsterdam',
        'billing_region' => 'Noord-Holland',
        'billing_postal_code' => '1000 AA',
        'billing_country' => 'NL',
        'billing_phone' => '+31 20 123 4567',
        'custom_properties_snapshot' => $snapshot,
        'issued_at' => now()->toDateString(),
        'subtotal_amount' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'currency' => 'EUR',
    ]);

    Sanctum::actingAs($customer->user);

    $this->getJson('/api/v1/orders/'.$order->id)
        ->assertOk()
        ->assertJsonPath('data.customer.name', 'Order Customer')
        ->assertJsonPath('data.customer.email', 'order-customer@example.test')
        ->assertJsonPath('data.billing.company', 'Billing BV')
        ->assertJsonPath('data.billing.line1', 'Order Street 1')
        ->assertJsonPath('data.billing.phone', '+31 20 123 4567')
        ->assertJsonPath('data.shipping.line1', 'Delivery Road 9')
        ->assertJsonPath('data.shipping.postal_code', '3000 BB')
        ->assertJsonPath('data.custom_properties.0.value', 'NL111111111B01');

    Livewire::actingAs($customer->user)
        ->test(OrderShow::class, ['order' => $order])
        ->assertOk()
        ->assertSee('Delivery Road 9')
        ->assertSee('NL111111111B01');

    $this->getJson('/api/v1/invoices/'.$invoice->id)
        ->assertOk()
        ->assertJsonPath('data.customer.name', 'Order Customer')
        ->assertJsonPath('data.billing.company', 'Billing BV')
        ->assertJsonPath('data.billing.line2', 'Suite 2')
        ->assertJsonPath('data.billing.region', 'Noord-Holland')
        ->assertJsonPath('data.billing.phone', '+31 20 123 4567')
        ->assertJsonPath('data.custom_properties.0.value', 'NL111111111B01');
});

test('invoice documents and account views read the invoice snapshot rather than customer properties', function () {
    $customer = Customer::factory()->create([
        'name' => 'Current Customer Name',
        'email' => 'current@example.test',
    ]);
    $invoice = Invoice::query()->create([
        'number' => 'INV-SNAPSHOT-00002',
        'status' => InvoiceStatus::Issued,
        'customer_id' => $customer->id,
        'customer_name' => 'Historical Customer Name',
        'customer_email' => 'historical@example.test',
        'billing_name' => 'Historical Billing Name',
        'billing_company' => 'Historical BV',
        'billing_line1' => 'Historical Street 4',
        'billing_line2' => 'Floor 3',
        'billing_city' => 'Utrecht',
        'billing_region' => 'Utrecht',
        'billing_postal_code' => '3500 CC',
        'billing_country' => 'NL',
        'billing_phone' => '+31 30 111 2222',
        'custom_properties_snapshot' => [
            ['key' => 'vat_number', 'label' => 'VAT number', 'value' => 'NL222222222B02'],
        ],
        'issued_at' => now()->toDateString(),
        'subtotal_amount' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'currency' => 'EUR',
    ]);

    $this->actingAs($customer->user)
        ->get(route('customer.invoices.print', $invoice))
        ->assertOk()
        ->assertSee('Historical Billing Name')
        ->assertSee('Historical BV')
        ->assertSee('Historical Street 4')
        ->assertSee('Floor 3')
        ->assertSee('Utrecht')
        ->assertSee('+31 30 111 2222')
        ->assertSee('NL222222222B02')
        ->assertDontSee('Current Customer Name');
});

test('credit note keeps and exposes the invoice snapshot', function () {
    $customer = Customer::factory()->create();
    $invoice = Invoice::query()->create([
        'number' => 'INV-SNAPSHOT-00003',
        'status' => InvoiceStatus::Paid,
        'customer_id' => $customer->id,
        'customer_name' => 'Historical Customer',
        'customer_email' => 'historical@example.test',
        'billing_name' => 'Historical Billing',
        'billing_line1' => 'Historical Street 5',
        'billing_city' => 'Eindhoven',
        'billing_postal_code' => '5600 DD',
        'billing_country' => 'NL',
        'custom_properties_snapshot' => [
            ['key' => 'vat_number', 'label' => 'VAT number', 'value' => 'NL333333333B03'],
        ],
        'issued_at' => now()->toDateString(),
        'subtotal_amount' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'currency' => 'EUR',
    ]);
    $creditNote = app(IssueCreditNote::class)->handle(
        $invoice,
        $this->createStaff(),
        'Snapshot test',
    );

    Sanctum::actingAs($customer->user);

    $this->getJson('/api/v1/credit-notes/'.$creditNote->id)
        ->assertOk()
        ->assertJsonPath('data.customer.name', 'Historical Customer')
        ->assertJsonPath('data.billing.line1', 'Historical Street 5')
        ->assertJsonPath('data.custom_properties.0.value', 'NL333333333B03');

    $this->actingAs($customer->user)
        ->get(route('customer.credit-notes.print', $creditNote))
        ->assertOk()
        ->assertSee('Historical Billing')
        ->assertSee('NL333333333B03');
});

test('internal customer properties are excluded from public document snapshots', function () {
    $customer = Customer::factory()->create();
    $definition = CustomerPropertyDefinition::query()->create([
        'key' => 'internal_reference',
        'label' => 'Internal reference',
        'type' => CustomerPropertyType::Text,
        'is_required' => false,
        'sort' => 999,
        'is_active' => true,
        'show_on_invoice' => true,
        'staff_editable' => true,
        'internal_only' => true,
    ]);

    $properties = app(CustomerPropertyService::class);
    $properties->save($customer, [$definition], ['internal_reference' => 'staff-secret'], 'staff');

    expect($properties->snapshot($customer))
        ->not->toContain(['key' => 'internal_reference', 'label' => 'Internal reference', 'value' => 'staff-secret']);
});
