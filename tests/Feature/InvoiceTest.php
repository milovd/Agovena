<?php

declare(strict_types=1);

use App\Agovena\Auth\ConfirmsRecentPassword;
use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Invoices\DeleteInvoice;
use App\Agovena\Invoices\IssueInvoiceFromOrder;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Settings\SettingsRepository;
use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceItemKind;
use App\Enums\InvoiceStatus;
use App\Enums\ProductOptionType;
use App\Livewire\Admin\Invoices\Edit as AdminInvoiceEdit;
use App\Livewire\Admin\Invoices\Index as AdminInvoicesIndex;
use App\Livewire\Admin\Invoices\Show as AdminInvoiceShow;
use App\Livewire\Customer\Account\CreditNoteShow;
use App\Livewire\Customer\Account\InvoiceShow;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionChoice;
use Illuminate\Validation\ValidationException;
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

    $this->actingAs($intruder->user)
        ->get(route('customer.invoices.pdf', $invoice))
        ->assertNotFound();

    $this->actingAs($intruder->user)
        ->get(route('customer.invoices.print', $invoice))
        ->assertNotFound();
});

test('customer cannot view another customers credit note or pdf', function () {
    $owner = Customer::factory()->create();
    $intruder = Customer::factory()->create();
    $invoice = Invoice::query()->create([
        'number' => 'INV-TEST-CN-00001',
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
    $creditNote = CreditNote::query()->create([
        'number' => 'CN-TEST-00001',
        'status' => CreditNoteStatus::Issued,
        'invoice_id' => $invoice->id,
        'customer_id' => $owner->id,
        'customer_name' => $owner->name,
        'customer_email' => $owner->email,
        'issued_at' => now()->toDateString(),
        'reason' => 'Test credit',
        'subtotal_amount' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'currency' => 'EUR',
    ]);

    Livewire::actingAs($owner->user)
        ->test(CreditNoteShow::class, ['creditNote' => $creditNote])
        ->assertOk()
        ->assertSee('CN-TEST-00001');

    Livewire::actingAs($intruder->user)
        ->test(CreditNoteShow::class, ['creditNote' => $creditNote])
        ->assertNotFound();

    $this->actingAs($intruder->user)
        ->get(route('customer.credit-notes.pdf', $creditNote))
        ->assertNotFound();

    $this->actingAs($intruder->user)
        ->get(route('customer.credit-notes.print', $creditNote))
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
        ->assertSee(__('admin.invoices.title'))
        ->assertSee(route('admin.invoices.edit', $invoice), false)
        ->assertSee('confirmDelete('.$invoice->id.')', false);

    Livewire::actingAs($staff)
        ->test(AdminInvoiceShow::class, ['invoice' => $invoice])
        ->assertOk()
        ->assertSee(__('admin.invoices.edit_action'), false)
        ->assertSee(__('admin.invoices.delete_action'), false)
        ->assertSee(__('admin.invoices.print'))
        ->assertSee(__('admin.invoices.download_pdf'))
        ->assertDontSee('window.print()', false);
});

test('staff can edit invoice administrative details without changing financial lines', function () {
    $staff = $this->createStaff([], ['invoices.view', 'invoices.update']);
    $invoice = Invoice::query()->create([
        'number' => 'INV-EDIT-00001',
        'status' => InvoiceStatus::Issued,
        'customer_name' => 'Old name',
        'customer_email' => 'old@example.test',
        'billing_name' => 'Old name',
        'billing_line1' => 'Old street 1',
        'billing_city' => 'Oldtown',
        'billing_postal_code' => '1000 AA',
        'billing_country' => 'NL',
        'merchant_name' => 'Old merchant',
        'issued_at' => '2026-09-01',
        'due_at' => '2026-09-30',
        'subtotal_amount' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'currency' => 'EUR',
    ]);
    $item = InvoiceItem::query()->create([
        'invoice_id' => $invoice->id,
        'kind' => InvoiceItemKind::Product,
        'label' => 'Service',
        'quantity' => 1,
        'unit_amount' => 1000,
        'line_total_amount' => 1000,
        'currency' => 'EUR',
    ]);

    $this->actingAs($staff)
        ->get(route('admin.invoices.edit', $invoice))
        ->assertOk()
        ->assertSee(__('admin.invoices.edit_title', ['number' => $invoice->number]), false)
        ->assertSee('class="admin-breadcrumbs"', false)
        ->assertSee('class="ag-back"', false)
        ->assertSee('href="'.route('admin.invoices.show', $invoice).'"', false);

    Livewire::actingAs($staff)
        ->test(AdminInvoiceEdit::class, ['invoice' => $invoice])
        ->set('customerName', 'New name')
        ->set('customerEmail', 'new@example.test')
        ->set('billingLine1', 'New street 2')
        ->set('billingCity', 'Newtown')
        ->set('billingCountry', 'BE')
        ->set('merchantName', 'New merchant')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee(__('admin.invoices.updated'), false);

    expect($invoice->fresh())
        ->customer_name->toBe('New name')
        ->customer_email->toBe('new@example.test')
        ->billing_line1->toBe('New street 2')
        ->billing_city->toBe('Newtown')
        ->billing_country->toBe('BE')
        ->merchant_name->toBe('New merchant')
        ->total_amount->toBe(1000)
        ->and($item->fresh()->line_total_amount)->toBe(1000);
});

test('staff without invoice update permission cannot edit an invoice', function () {
    $staff = $this->createStaff([], ['invoices.view']);
    $invoice = Invoice::query()->create([
        'number' => 'INV-EDIT-00002',
        'status' => InvoiceStatus::Issued,
        'customer_name' => 'Invoice customer',
        'customer_email' => 'invoice@example.test',
        'issued_at' => now()->toDateString(),
        'subtotal_amount' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'currency' => 'EUR',
    ]);

    Livewire::actingAs($staff)
        ->test(AdminInvoiceEdit::class, ['invoice' => $invoice])
        ->assertForbidden();
});

test('deleting an unpaid invoice requires recent password and removes its line items', function () {
    $staff = $this->createStaff([], ['invoices.view', 'invoices.delete']);
    $invoice = Invoice::query()->create([
        'number' => 'INV-DELETE-00001',
        'status' => InvoiceStatus::Issued,
        'customer_name' => 'Invoice customer',
        'customer_email' => 'invoice@example.test',
        'issued_at' => now()->toDateString(),
        'subtotal_amount' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'currency' => 'EUR',
    ]);
    $item = InvoiceItem::query()->create([
        'invoice_id' => $invoice->id,
        'kind' => InvoiceItemKind::Product,
        'label' => 'Service',
        'quantity' => 1,
        'unit_amount' => 1000,
        'line_total_amount' => 1000,
        'currency' => 'EUR',
    ]);

    Livewire::actingAs($staff)
        ->test(AdminInvoicesIndex::class)
        ->call('confirmDelete', $invoice->id)
        ->call('deleteInvoice')
        ->assertSet('showingPasswordConfirmation', true);

    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $staff->id,
    ]);

    Livewire::actingAs($staff)
        ->test(AdminInvoicesIndex::class)
        ->call('confirmDelete', $invoice->id)
        ->call('deleteInvoice')
        ->assertSet('showingPasswordConfirmation', false);

    expect(Invoice::query()->whereKey($invoice->id)->exists())->toBeFalse()
        ->and(InvoiceItem::query()->whereKey($item->id)->exists())->toBeFalse();
});

test('paid invoices cannot be deleted', function () {
    $staff = $this->createStaff([], ['invoices.delete']);
    $invoice = Invoice::query()->create([
        'number' => 'INV-DELETE-00002',
        'status' => InvoiceStatus::Paid,
        'customer_name' => 'Invoice customer',
        'customer_email' => 'invoice@example.test',
        'issued_at' => now()->toDateString(),
        'paid_at' => now(),
        'subtotal_amount' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'currency' => 'EUR',
    ]);

    expect(fn () => app(DeleteInvoice::class)->handle($invoice, $staff))
        ->toThrow(ValidationException::class);

    expect(Invoice::query()->whereKey($invoice->id)->exists())->toBeTrue();
});

test('invoices with credit notes cannot be deleted', function () {
    $staff = $this->createStaff([], ['invoices.delete']);
    $invoice = Invoice::query()->create([
        'number' => 'INV-DELETE-00003',
        'status' => InvoiceStatus::Issued,
        'customer_name' => 'Invoice customer',
        'customer_email' => 'invoice@example.test',
        'issued_at' => now()->toDateString(),
        'subtotal_amount' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'currency' => 'EUR',
    ]);
    CreditNote::query()->create([
        'number' => 'CN-DELETE-00001',
        'status' => CreditNoteStatus::Issued,
        'invoice_id' => $invoice->id,
        'customer_name' => 'Invoice customer',
        'customer_email' => 'invoice@example.test',
        'issued_at' => now()->toDateString(),
        'reason' => 'Correction',
        'subtotal_amount' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'currency' => 'EUR',
    ]);

    expect(fn () => app(DeleteInvoice::class)->handle($invoice, $staff))
        ->toThrow(ValidationException::class);

    expect(Invoice::query()->whereKey($invoice->id)->exists())->toBeTrue();
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
