<?php

declare(strict_types=1);

use App\Agovena\Auth\ConfirmsRecentPassword;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Invoices\IssueInvoiceFromOrder;
use App\Agovena\Orders\ReissueOrderInvoice;
use App\Agovena\Recurring\Enums\SubscriptionInterval;
use App\Agovena\Recurring\Enums\SubscriptionStatus;
use App\Agovena\Recurring\Models\Subscription;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Admin\Orders\Show;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function inlineOrder(array $attributes = []): Order
{
    $email = 'owner-'.Str::lower(Str::random(6)).'@example.test';
    $owner = Customer::factory()->create(['name' => 'Inline Owner', 'email' => $email]);
    $order = Order::factory()->create([
        'customer_id' => $owner->id,
        'customer_name' => 'Inline Owner',
        'customer_email' => $email,
        'status' => OrderStatus::Pending,
        'currency' => 'EUR',
        ...$attributes,
    ]);
    Payment::factory()->for($order)->create(['status' => PaymentStatus::Pending, 'amount' => $order->total_amount]);

    return $order->fresh(['payment']);
}

function confirmInlinePassword(User $staff): void
{
    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $staff->id,
    ]);
}

it('redirects the old edit page to the order page', function (): void {
    $staff = $this->createStaff();
    $order = inlineOrder();

    $this->actingAs($staff)->get('/admin/orders/'.$order->id.'/edit')
        ->assertRedirect(route('admin.orders.show', $order));
});

it('shows the order as a form only to staff who may update orders', function (): void {
    $order = inlineOrder();

    Livewire::actingAs($this->createStaff())->test(Show::class, ['order' => $order])
        ->assertSee('wire:click="saveOrder"', false)
        ->assertSee('wire:click="openPicker(&#039;customer&#039;)"', false)
        ->assertSee('wire:model="details.billing.line1"', false)
        ->assertSee(route('admin.customers.show', $order->customer_id), false);

    Livewire::actingAs($this->createStaff([], ['orders.view']))->test(Show::class, ['order' => $order])
        ->assertDontSee('wire:click="saveOrder"', false)
        ->assertDontSee('openPicker(&#039;customer&#039;)', false)
        ->assertDontSee('wire:model="details.billing.line1"', false)
        ->call('saveOrder')
        ->assertForbidden();
});

it('shows the save bar once something changed and discards changes', function (): void {
    $order = inlineOrder();

    Livewire::actingAs($this->createStaff())->test(Show::class, ['order' => $order])
        ->assertDontSee('admin-order__savebar is-dirty', false)
        ->set('details.customerName', 'Changed')
        ->assertSee('admin-order__savebar is-dirty', false)
        ->call('resetForm')
        ->assertSet('details.customerName', 'Inline Owner')
        ->assertDontSee('admin-order__savebar is-dirty', false);
});

it('saves contact details and the due date from the order page', function (): void {
    $staff = $this->createStaff();
    $order = inlineOrder();

    Livewire::actingAs($staff)->test(Show::class, ['order' => $order])
        ->set('details.customerName', 'Snapshot Name')
        ->set('details.customerEmail', 'not-an-email')
        ->call('saveOrder')
        ->assertHasErrors(['details.customerEmail'])
        ->set('details.customerEmail', 'snapshot@example.test')
        ->set('details.dueAt', '2026-12-01T10:00')
        ->call('saveOrder')
        ->assertHasNoErrors()
        ->assertSee(__('admin.orders.inline.saved'));

    expect($order->fresh())
        ->customer_name->toBe('Snapshot Name')
        ->customer_email->toBe('snapshot@example.test')
        ->and($order->fresh()->due_at?->format('Y-m-d H:i'))->toBe('2026-12-01 10:00');
});

it('picks another customer with the search select and saves after a recent password', function (): void {
    $staff = $this->createStaff();
    $order = inlineOrder();
    $invoice = app(IssueInvoiceFromOrder::class)->handle($order);
    $newOwner = Customer::factory()->create(['name' => 'Picked Customer', 'email' => 'picked@example.test']);

    $component = Livewire::actingAs($staff)->test(Show::class, ['order' => $order])
        ->call('openPicker', 'customer')
        ->assertSee(__('admin.orders.inline.search'))
        ->set('pickerTerm', 'Picked')
        ->assertSee('Picked Customer (picked@example.test)')
        ->call('pick', 'customer', $newOwner->id)
        ->assertSet('customerId', $newOwner->id)
        ->assertSet('picker', null)
        ->assertSet('details.customerName', 'Picked Customer')
        ->assertSet('details.customerEmail', 'picked@example.test')
        ->call('saveOrder')
        ->assertSet('showingPasswordConfirmation', true);

    expect($order->fresh()->customer_id)->not->toBe($newOwner->id);

    confirmInlinePassword($staff);
    $component->call('saveOrder')->assertHasNoErrors()->assertSee(__('admin.orders.inline.saved_reissued'));

    expect($order->fresh()->customer_id)->toBe($newOwner->id)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Void);
});

it('locks the customer of a paid order and refuses a forged pick', function (): void {
    $staff = $this->createStaff();
    confirmInlinePassword($staff);
    $order = inlineOrder(['status' => OrderStatus::Paid]);
    $order->payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
    $other = Customer::factory()->create();

    Livewire::actingAs($staff)->test(Show::class, ['order' => $order->fresh()])
        ->assertSee(__('admin.orders.edit_lock.paid'))
        ->assertDontSee('openPicker(&#039;customer&#039;)', false)
        ->assertDontSee('wire:click="addLine"', false)
        ->call('pick', 'customer', $other->id)
        ->assertHasErrors(['customer'])
        ->assertSet('customerId', $order->customer_id);

    expect($order->fresh()->customer_id)->not->toBe($other->id);
});

it('adds a line with a product search, prefills its price and recalculates the order', function (): void {
    $staff = $this->createStaff();
    $existing = Product::factory()->active()->create(['price_amount' => 1000, 'currency' => 'EUR', 'name' => 'Existing Plan']);
    $order = inlineOrder(['subtotal_amount' => 1000, 'total_amount' => 1000, 'discount_amount' => 0, 'shipping_amount' => 0]);
    OrderItem::factory()->for($order)->create(['product_id' => $existing->id, 'label' => 'Existing Plan', 'quantity' => 1, 'unit_amount' => 1000, 'line_total_amount' => 1000, 'currency' => 'EUR']);
    $added = Product::factory()->active()->create(['price_amount' => 2500, 'currency' => 'EUR', 'name' => 'Zeta Hosting']);

    Livewire::actingAs($staff)->test(Show::class, ['order' => $order->fresh()])
        ->call('addLine')
        ->assertSet('picker', 'line.1')
        ->set('pickerTerm', 'Zeta')
        ->assertSee('Zeta Hosting')
        ->call('pick', 'line.1', $added->id)
        ->assertSet('lines.1.product_id', $added->id)
        ->assertSet('lines.1.price', '25.00')
        ->set('lines.1.quantity', '2')
        ->set('lines.0.price', '12.50')
        ->call('saveOrder')
        ->assertHasNoErrors();

    $fresh = $order->fresh(['items', 'payment']);
    expect($fresh->items)->toHaveCount(2)
        ->and($fresh->subtotal_amount)->toBe(1250 + 5000)
        ->and($fresh->payment->amount)->toBe($fresh->total_amount);
});

it('removes a line and validates line input before saving', function (): void {
    $staff = $this->createStaff();
    $product = Product::factory()->active()->create(['price_amount' => 1000, 'currency' => 'EUR']);
    $order = inlineOrder();
    OrderItem::factory()->for($order)->count(2)->create(['product_id' => $product->id, 'quantity' => 1, 'unit_amount' => 1000, 'line_total_amount' => 1000, 'currency' => 'EUR']);

    Livewire::actingAs($staff)->test(Show::class, ['order' => $order->fresh()])
        ->set('lines.0.quantity', '0')
        ->set('lines.1.price', 'abc')
        ->call('saveOrder')
        ->assertHasErrors(['lines.0.quantity', 'lines.1.price'])
        ->call('resetForm')
        ->call('removeLine', 1)
        ->call('saveOrder')
        ->assertHasNoErrors();

    expect($order->fresh()->items)->toHaveCount(1);
});

it('edits both addresses in place and copies billing to shipping when asked', function (): void {
    $staff = $this->createStaff();
    $order = inlineOrder();

    Livewire::actingAs($staff)->test(Show::class, ['order' => $order])
        ->set('details.billing.line1', 'Inline street 9')
        ->set('details.billing.city', 'Ghent')
        ->set('details.billing.country', 'be')
        ->set('details.shippingSameAsBilling', true)
        ->call('saveOrder')
        ->assertHasNoErrors();

    expect($order->fresh())
        ->billing_line1->toBe('Inline street 9')
        ->billing_country->toBe('BE')
        ->shipping_line1->toBe('Inline street 9')
        ->shipping_city->toBe('Ghent');
});

it('deletes an order from the order page with the same guards as before', function (): void {
    $staff = $this->createStaff();
    $order = inlineOrder();

    Livewire::actingAs($staff)->test(Show::class, ['order' => $order])
        ->assertSee('wire:click="confirmDelete"', false)
        ->call('confirmDelete')
        ->call('deleteOrder')
        ->assertSet('showingPasswordConfirmation', true);
    expect(Order::query()->whereKey($order->id)->exists())->toBeTrue();

    confirmInlinePassword($staff);
    Livewire::actingAs($staff)->test(Show::class, ['order' => $order])
        ->call('confirmDelete')
        ->call('deleteOrder')
        ->assertRedirect(route('admin.orders.index'));
    expect(Order::query()->whereKey($order->id)->exists())->toBeFalse();
});

it('shows recurring lines from their subscription or from the product setup', function (): void {
    $staff = $this->createStaff();
    $order = inlineOrder();
    $hosting = Product::factory()->create(['name' => 'Hosting Plan']);
    $item = OrderItem::factory()->for($order)->create(['product_id' => $hosting->id, 'label' => 'Hosting Plan']);
    $subscription = Subscription::query()->create([
        'number' => 'SUB-INLINE-0001',
        'customer_id' => $order->customer_id,
        'customer_email' => $order->customer_email,
        'customer_name' => $order->customer_name,
        'product_id' => $hosting->id,
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'status' => SubscriptionStatus::Active,
        'interval' => SubscriptionInterval::Month,
        'interval_count' => 3,
        'price_amount' => 999,
        'currency' => 'EUR',
        'quantity' => 1,
        'current_period_start' => now(),
        'current_period_end' => now()->addMonths(3),
        'next_billing_at' => now()->addMonths(3),
    ]);
    $yearly = Product::factory()->create(['name' => 'Yearly Licence']);
    app(ProductCapabilityManager::class)->enable($yearly, 'subscribable', ['interval' => 'year', 'interval_count' => 1]);
    OrderItem::factory()->for($order)->create(['product_id' => $yearly->id, 'label' => 'Yearly Licence']);

    Livewire::actingAs($staff)->test(Show::class, ['order' => $order->fresh()])
        ->assertSee('3 × '.__('subscriptions::interval.month'))
        ->assertSee(route('admin.subscriptions.show', $subscription), false)
        ->assertSee(__('admin.orders.inline.subscription', ['number' => 'SUB-INLINE-0001']))
        ->assertSee('1 × '.__('subscriptions::interval.year'));
});

it('lists linked invoices newest first, adds one by searching and unlinks with an icon', function (): void {
    $staff = $this->createStaff();
    $order = inlineOrder();
    $first = app(IssueInvoiceFromOrder::class)->handle($order);
    $first->forceFill(['created_at' => now()->subDay()])->save();
    $replacement = app(ReissueOrderInvoice::class)->handle($order, $staff, 'test');

    $component = Livewire::actingAs($staff)->test(Show::class, ['order' => $order]);
    $html = $component->html();

    $items = strpos($html, 'id="order-items-heading"');
    $invoices = strpos($html, 'id="order-invoices-heading"');
    $addresses = strpos($html, 'id="order-addresses-heading"');

    expect($invoices)->toBeGreaterThan($items)
        ->and($invoices)->toBeLessThan($addresses)
        ->and(strpos($html, $replacement->number))->toBeLessThan(strpos($html, $first->number))
        ->and($html)->toContain('ag-badge ag-badge--info">'.__('admin.invoices.status.issued'))
        ->and($html)->toContain('ag-badge ag-badge--muted">'.__('admin.invoices.status.void'))
        ->and($html)->not->toContain('wire:click="startLinkInvoice"')
        // A void invoice is kept for the record, so this order no longer links or unlinks invoices.
        ->and($html)->not->toContain('wire:click="unlinkInvoice(');

    $fresh = inlineOrder();
    $own = app(IssueInvoiceFromOrder::class)->handle($fresh);
    $component = Livewire::actingAs($staff)->test(Show::class, ['order' => $fresh])
        ->assertSee('placeholder="'.e(__('admin.orders.inline.add_invoice')).'"', false)
        ->assertSee('aria-label="'.e(__('admin.orders.inline.unlink_invoice', ['number' => $own->number])).'"', false)
        ->call('unlinkInvoice', $own->id);
    expect($own->fresh()->order_id)->toBeNull();

    $component->set('invoiceSearch', $own->number)
        ->assertSee(__('admin.orders.inline.add_invoice_aria', ['number' => $own->number]))
        ->call('linkInvoice', $own->id)
        ->assertSet('invoiceSearch', '');
    expect($own->fresh()->order_id)->toBe($fresh->id);
});
