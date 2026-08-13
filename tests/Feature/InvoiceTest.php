<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Invoices\IssueInvoiceFromOrder;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Settings\SettingsRepository;
use App\Enums\InvoiceStatus;
use App\Enums\ProductOptionType;
use App\Livewire\Admin\Invoices\Index as AdminInvoicesIndex;
use App\Livewire\Admin\Invoices\Show as AdminInvoiceShow;
use App\Livewire\Customer\Account\InvoiceShow;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionChoice;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('paid order issues an invoice with sequential number', function () {
    $product = Product::factory()->active()->create(['price_amount' => 2500]);
    app(CartService::class)->add($product->id, 2);

    $customer = Customer::factory()->create();
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
    ]);

    $invoice = Invoice::query()->where('order_id', $order->id)->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->paid_at)->toBeNull();

    $staff = $this->createStaff();
    app(RecordManualPayment::class)->handle($order, $staff, 'PAY-1');

    $invoice = $invoice->fresh();

    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->paid_at)->not->toBeNull()
        ->and($invoice->total_amount)->toBe(5000)
        ->and($invoice->items)->toHaveCount(1)
        ->and($invoice->number)->toStartWith('INV-');

    $again = app(IssueInvoiceFromOrder::class)->handle($order->fresh());
    expect($again->id)->toBe($invoice->id);
});

test('customer can view own invoice but not another customers', function () {
    $owner = Customer::factory()->create();
    $intruder = Customer::factory()->create();
    $invoice = Invoice::query()->create([
        'number' => 'INV-TEST-00001',
        'status' => InvoiceStatus::Paid,
        'order_id' => null,
        'customer_id' => $owner->id,
        'customer_name' => $owner->name,
        'customer_email' => $owner->email,
        'issued_at' => now()->toDateString(),
        'subtotal_amount' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'currency' => 'EUR',
    ]);

    Livewire::actingAs($owner->user)
        ->test(InvoiceShow::class, ['invoice' => $invoice])
        ->assertOk()
        ->assertSee('INV-TEST-00001')
        ->assertSee(__('customer.account.print_invoice'))
        ->assertSee(__('customer.account.download_invoice_pdf'))
        ->assertDontSee('window.print()', false);

    Livewire::actingAs($intruder->user)
        ->test(InvoiceShow::class, ['invoice' => $invoice])
        ->assertNotFound();
});

test('owner can open invoices admin', function () {
    $staff = $this->createStaff();
    $invoice = Invoice::query()->create([
        'number' => 'INV-TEST-00002',
        'status' => InvoiceStatus::Paid,
        'customer_name' => 'Admin Invoice Customer',
        'customer_email' => 'invoice@example.test',
        'issued_at' => now()->toDateString(),
        'subtotal_amount' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'currency' => 'EUR',
    ]);

    Livewire::actingAs($staff)
        ->test(AdminInvoicesIndex::class)
        ->assertOk()
        ->assertSee(__('admin.invoices.title'));

    Livewire::actingAs($staff)
        ->test(AdminInvoiceShow::class, ['invoice' => $invoice])
        ->assertOk()
        ->assertSee(__('admin.invoices.print'))
        ->assertSee(__('admin.invoices.download_pdf'))
        ->assertDontSee('window.print()', false);
});

test('invoice snapshots seller identity product options and stays immutable', function () {
    app(SettingsRepository::class)->set('store', 'seller_name', 'Snapshot Merchant');
    app(SettingsRepository::class)->set('store', 'seller_address', "Street 9\nAmsterdam");

    $product = Product::factory()->active()->create(['name' => 'VPS', 'price_amount' => 2000]);
    ProductOption::query()->create([
        'product_id' => $product->id,
        'key' => 'ram',
        'label' => 'RAM',
        'type' => ProductOptionType::Select,
        'is_required' => true,
        'is_active' => true,
        'sort' => 1,
        'price_adjustment_amount' => 0,
    ]);
    $option = ProductOption::query()->where('product_id', $product->id)->first();
    ProductOptionChoice::query()->create([
        'product_option_id' => $option->id,
        'value' => '8gb',
        'label' => '8 GB',
        'price_adjustment_amount' => 500,
        'sort' => 1,
        'is_active' => true,
    ]);

    app(CartService::class)->add($product->id, 1, ['ram' => '8gb']);
    $customer = Customer::factory()->create();
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
    ]);

    $staff = $this->createStaff();
    app(RecordManualPayment::class)->handle($order, $staff, 'PAY-SNAP');
    $invoice = Invoice::query()->where('order_id', $order->id)->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->merchant_name)->toBe('Snapshot Merchant')
        ->and($invoice->merchant_address)->toBe("Street 9\nAmsterdam")
        ->and($invoice->paid_at)->not->toBeNull()
        ->and($invoice->items->first()->options_snapshot)->not->toBeEmpty();

    $product->update(['name' => 'Changed later']);
    app(SettingsRepository::class)->set('store', 'seller_name', 'New Merchant');

    $invoice->refresh();
    expect($invoice->merchant_name)->toBe('Snapshot Merchant')
        ->and($invoice->items->first()->label)->toBe('VPS');

    $this->actingAs($customer->user)
        ->get(route('customer.invoices.pdf', $invoice))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($staff)
        ->get(route('admin.invoices.print', $invoice))
        ->assertOk()
        ->assertSee('Snapshot Merchant', false)
        ->assertSee('8 GB', false);
});
