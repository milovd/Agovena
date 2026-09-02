<?php

declare(strict_types=1);

use App\Agovena\Auth\ConfirmsRecentPassword;
use App\Agovena\Invoices\IssueInvoiceFromOrder;
use App\Agovena\Invoices\LinkInvoiceToOrder;
use App\Agovena\Invoices\UnlinkInvoiceFromOrder;
use App\Agovena\Orders\DeleteOrder;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Admin\Orders\Edit as OrderEdit;
use App\Livewire\Admin\Orders\Index as OrdersIndex;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('staff can edit an open order contact and address snapshot', function () {
    $staff = $this->createStaff([], ['orders.view', 'orders.update']);
    $order = Order::factory()->create([
        'customer_name' => 'Old name',
        'customer_email' => 'old@example.test',
        'billing_name' => 'Old name',
        'billing_line1' => 'Old street 1',
        'billing_city' => 'Oldtown',
        'billing_postal_code' => '1000 AA',
        'billing_country' => 'NL',
    ]);
    $invoice = app(IssueInvoiceFromOrder::class)->handle($order);

    Livewire::actingAs($staff)
        ->test(OrderEdit::class, ['order' => $order])
        ->set('customerName', 'New name')
        ->set('customerEmail', 'new@example.test')
        ->set('billingName', 'New name')
        ->set('billingLine1', 'New street 2')
        ->set('billingCity', 'Newtown')
        ->set('billingPostalCode', '2000 BB')
        ->set('billingCountry', 'BE')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee(__('admin.orders.flash.updated'), false);

    expect($order->fresh())
        ->customer_name->toBe('New name')
        ->customer_email->toBe('new@example.test')
        ->billing_line1->toBe('New street 2')
        ->billing_city->toBe('Newtown')
        ->billing_country->toBe('BE')
        ->and($invoice->fresh()->customer_name)->toBe('Old name');
});

test('staff can correct contact details on a paid order without rewriting its invoice', function () {
    $staff = $this->createStaff([], ['orders.view', 'orders.update']);
    $order = Order::factory()->create([
        'status' => OrderStatus::Paid,
        'customer_name' => 'Old name',
        'customer_email' => 'old@example.test',
    ]);
    $invoice = app(IssueInvoiceFromOrder::class)->handle($order);

    Livewire::actingAs($staff)
        ->test(OrderEdit::class, ['order' => $order])
        ->set('customerName', 'Corrected name')
        ->set('customerEmail', 'corrected@example.test')
        ->call('save')
        ->assertHasNoErrors();

    expect($order->fresh()->customer_name)->toBe('Corrected name')
        ->and($invoice->fresh()->customer_name)->toBe('Old name');
});

test('staff with update permission can open the order edit route', function () {
    $staff = $this->createStaff([], ['orders.view', 'orders.update']);
    $order = Order::factory()->create();

    $this->actingAs($staff)
        ->get(route('admin.orders.edit', $order))
        ->assertOk()
        ->assertSee(__('admin.orders.edit_title', ['number' => $order->number]), false)
        ->assertSee('class="admin-breadcrumbs"', false)
        ->assertSee('class="ag-back"', false)
        ->assertSee('href="'.route('admin.orders.show', $order).'"', false)
        ->assertSee('wire:model="customerName"', false);
});

test('order detail keeps the important information in a compact address section', function () {
    $staff = $this->createStaff([], ['orders.view']);
    $order = Order::factory()->create([
        'billing_name' => 'Ada Guest',
        'billing_line1' => 'Market street 1',
        'billing_city' => 'Brussels',
        'billing_postal_code' => '1000 AA',
        'billing_country' => 'BE',
        'shipping_name' => 'Ada Guest',
        'shipping_line1' => 'Market street 1',
        'shipping_city' => 'Brussels',
        'shipping_postal_code' => '1000 AA',
        'shipping_country' => 'BE',
    ]);

    $this->actingAs($staff)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('ag-order-addresses', false)
        ->assertSee(__('admin.orders.show.billing_address'), false)
        ->assertSee(__('admin.orders.show.shipping_address'), false)
        ->assertSee('Market street 1', false);
});

test('an order can have multiple invoices and mutable invoices can be linked and unlinked', function () {
    $staff = $this->createStaff([], ['orders.view', 'invoices.view', 'invoices.manage']);
    $order = Order::factory()->create([
        'customer_name' => 'Invoice customer',
        'customer_email' => 'invoice@example.test',
    ]);
    $invoiceAttributes = [
        'status' => InvoiceStatus::Issued,
        'customer_name' => $order->customer_name,
        'customer_email' => $order->customer_email,
        'issued_at' => now()->toDateString(),
        'subtotal_amount' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'currency' => $order->currency,
    ];
    $first = Invoice::query()->create(['number' => 'INV-LINK-00001', ...$invoiceAttributes]);
    $second = Invoice::query()->create(['number' => 'INV-LINK-00002', ...$invoiceAttributes]);

    app(LinkInvoiceToOrder::class)->handle($first, $order, $staff);
    app(LinkInvoiceToOrder::class)->handle($second, $order, $staff);

    expect($order->fresh('invoices')->invoices)->toHaveCount(2)
        ->and($first->fresh()->order_id)->toBe($order->id)
        ->and($second->fresh()->order_id)->toBe($order->id);

    app(UnlinkInvoiceFromOrder::class)->handle($first->fresh(), $staff);

    expect($first->fresh()->order_id)->toBeNull()
        ->and($order->fresh('invoices')->invoices)->toHaveCount(1);
});

test('financially relevant invoices cannot be unlinked from an order', function () {
    $staff = $this->createStaff([], ['invoices.manage']);
    $order = Order::factory()->create([
        'customer_name' => 'Invoice customer',
        'customer_email' => 'invoice@example.test',
    ]);
    $invoice = Invoice::query()->create([
        'number' => 'INV-LINK-00003',
        'status' => InvoiceStatus::Paid,
        'order_id' => $order->id,
        'customer_name' => $order->customer_name,
        'customer_email' => $order->customer_email,
        'issued_at' => now()->toDateString(),
        'paid_at' => now(),
        'subtotal_amount' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'currency' => $order->currency,
    ]);

    expect(fn () => app(UnlinkInvoiceFromOrder::class)->handle($invoice, $staff))
        ->toThrow(ValidationException::class);

    expect($invoice->fresh()->order_id)->toBe($order->id);
});

test('order and invoice detail pages expose the invoice relationship in both directions', function () {
    $staff = $this->createStaff([], ['orders.view', 'invoices.view', 'invoices.manage']);
    $order = Order::factory()->create([
        'customer_name' => 'Invoice customer',
        'customer_email' => 'invoice@example.test',
    ]);
    $attributes = [
        'status' => InvoiceStatus::Issued,
        'order_id' => $order->id,
        'customer_name' => $order->customer_name,
        'customer_email' => $order->customer_email,
        'issued_at' => now()->toDateString(),
        'subtotal_amount' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'currency' => $order->currency,
    ];
    $first = Invoice::query()->create(['number' => 'INV-LINK-00006', ...$attributes]);
    $second = Invoice::query()->create(['number' => 'INV-LINK-00007', ...$attributes]);

    $this->actingAs($staff)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee($first->number, false)
        ->assertSee($second->number, false)
        ->assertSee(route('admin.invoices.show', $first), false);

    $this->get(route('admin.invoices.show', $first))
        ->assertOk()
        ->assertSee($order->number, false)
        ->assertSee(route('admin.orders.show', $order), false);
});

test('a pending order with a paid invoice cannot accept another invoice or be deleted', function () {
    $staff = $this->createStaff([], ['invoices.manage', 'orders.delete']);
    $order = Order::factory()->create([
        'customer_name' => 'Invoice customer',
        'customer_email' => 'invoice@example.test',
    ]);
    $attributes = [
        'customer_name' => $order->customer_name,
        'customer_email' => $order->customer_email,
        'issued_at' => now()->toDateString(),
        'subtotal_amount' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'currency' => $order->currency,
    ];
    $paid = Invoice::query()->create([
        'number' => 'INV-LINK-00004',
        'status' => InvoiceStatus::Paid,
        'order_id' => $order->id,
        'paid_at' => now(),
        ...$attributes,
    ]);
    $candidate = Invoice::query()->create([
        'number' => 'INV-LINK-00005',
        'status' => InvoiceStatus::Issued,
        ...$attributes,
    ]);

    expect(fn () => app(LinkInvoiceToOrder::class)->handle($candidate, $order, $staff))
        ->toThrow(ValidationException::class);
    expect(fn () => app(DeleteOrder::class)->handle($order, $staff))
        ->toThrow(ValidationException::class);

    expect($paid->fresh()->order_id)->toBe($order->id)
        ->and($candidate->fresh()->order_id)->toBeNull();
});

test('staff without update permission cannot open order editing', function () {
    $staff = $this->createStaff([], ['orders.view']);
    $order = Order::factory()->create();

    Livewire::actingAs($staff)
        ->test(OrderEdit::class, ['order' => $order])
        ->assertForbidden();
});

test('deleting an open order also deletes its invoice and invoice items', function () {
    $staff = $this->createStaff([], ['orders.delete']);
    $order = Order::factory()->create();
    $payment = Payment::query()->create([
        'order_id' => $order->id,
        'amount' => $order->total_amount,
        'currency' => $order->currency,
        'method' => 'manual',
        'status' => PaymentStatus::Pending,
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'quantity' => 1,
        'unit_amount' => 2500,
        'line_total_amount' => 2500,
    ]);
    $invoice = app(IssueInvoiceFromOrder::class)->handle($order->fresh());

    app(DeleteOrder::class)->handle($order, $staff);

    expect(Order::query()->whereKey($order->id)->exists())->toBeFalse()
        ->and(Invoice::query()->whereKey($invoice->id)->exists())->toBeFalse()
        ->and(InvoiceItem::query()->where('invoice_id', $invoice->id)->exists())->toBeFalse()
        ->and(Payment::query()->whereKey($payment->id)->exists())->toBeFalse();
});

test('paid orders cannot be deleted and keep their invoice', function () {
    $staff = $this->createStaff([], ['orders.delete']);
    $order = Order::factory()->create(['status' => OrderStatus::Paid]);
    $invoice = app(IssueInvoiceFromOrder::class)->handle($order);

    expect(fn () => app(DeleteOrder::class)->handle($order, $staff))
        ->toThrow(ValidationException::class);

    expect(Order::query()->whereKey($order->id)->exists())->toBeTrue()
        ->and(Invoice::query()->whereKey($invoice->id)->exists())->toBeTrue();
});

test('deleting from the order list requires recent password confirmation', function () {
    $staff = $this->createStaff([], ['orders.delete', 'orders.view']);
    $order = Order::factory()->create();
    $invoice = app(IssueInvoiceFromOrder::class)->handle($order);

    Livewire::actingAs($staff)
        ->test(OrdersIndex::class)
        ->call('confirmDelete', $order->id)
        ->call('deleteOrder')
        ->assertSet('showingPasswordConfirmation', true);

    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $staff->id,
    ]);

    Livewire::actingAs($staff)
        ->test(OrdersIndex::class)
        ->call('confirmDelete', $order->id)
        ->call('deleteOrder')
        ->assertSet('showingPasswordConfirmation', false);

    expect(Order::query()->whereKey($order->id)->exists())->toBeFalse()
        ->and(Invoice::query()->whereKey($invoice->id)->exists())->toBeFalse();
});
