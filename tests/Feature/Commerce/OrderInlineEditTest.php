<?php

declare(strict_types=1);

use App\Agovena\Auth\ConfirmsRecentPassword;
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
        ...$attributes,
    ]);
    Payment::factory()->for($order)->create(['status' => PaymentStatus::Pending]);

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

it('shows inline edit controls only to staff who may update orders', function (): void {
    $order = inlineOrder();

    Livewire::actingAs($this->createStaff())->test(Show::class, ['order' => $order])
        ->assertSee('wire:click="startEditDetails"', false)
        ->assertSee('wire:click="startEditAddresses"', false)
        ->assertSee(route('admin.customers.show', $order->customer_id), false);

    Livewire::actingAs($this->createStaff([], ['orders.view']))->test(Show::class, ['order' => $order])
        ->assertDontSee('wire:click="startEditDetails"', false)
        ->assertDontSee('wire:click="startEditAddresses"', false)
        ->call('startEditDetails')
        ->assertForbidden();
});

it('saves contact details and the due date from the order page', function (): void {
    $staff = $this->createStaff();
    $order = inlineOrder();

    Livewire::actingAs($staff)->test(Show::class, ['order' => $order])
        ->call('startEditDetails')
        ->assertSet('editingDetails', true)
        ->set('details.customerName', 'Snapshot Name')
        ->set('details.customerEmail', 'not-an-email')
        ->call('saveDetails')
        ->assertHasErrors(['details.customerEmail'])
        ->set('details.customerEmail', 'snapshot@example.test')
        ->set('details.dueAt', '2026-12-01T10:00')
        ->call('saveDetails')
        ->assertHasNoErrors()
        ->assertSet('editingDetails', false);

    expect($order->fresh())
        ->customer_name->toBe('Snapshot Name')
        ->customer_email->toBe('snapshot@example.test')
        ->and($order->fresh()->due_at?->format('Y-m-d H:i'))->toBe('2026-12-01 10:00');
});

it('changes the customer of an unpaid order after a recent password and reissues its invoice', function (): void {
    $staff = $this->createStaff();
    $order = inlineOrder();
    $invoice = app(IssueInvoiceFromOrder::class)->handle($order);
    $newOwner = Customer::factory()->create(['name' => 'Picked Customer', 'email' => 'picked@example.test']);

    $component = Livewire::actingAs($staff)->test(Show::class, ['order' => $order])
        ->call('startEditDetails')
        ->set('customerSearch', 'Picked')
        ->assertSee('Picked Customer (picked@example.test)')
        ->call('selectCustomer', $newOwner->id)
        ->assertSet('selectedCustomerId', $newOwner->id)
        ->assertSet('details.customerName', 'Picked Customer')
        ->assertSet('details.customerEmail', 'picked@example.test')
        ->call('saveDetails')
        ->assertSet('showingPasswordConfirmation', true);

    expect($order->fresh()->customer_id)->not->toBe($newOwner->id);

    confirmInlinePassword($staff);
    $component->call('saveDetails')->assertHasNoErrors()->assertSet('editingDetails', false);

    expect($order->fresh()->customer_id)->toBe($newOwner->id)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Void);
});

it('locks the customer of a paid order and rejects a forged change', function (): void {
    $staff = $this->createStaff();
    confirmInlinePassword($staff);
    $order = inlineOrder(['status' => OrderStatus::Paid]);
    $order->payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
    $other = Customer::factory()->create();

    Livewire::actingAs($staff)->test(Show::class, ['order' => $order->fresh()])
        ->call('startEditDetails')
        ->assertSee(__('admin.orders.edit_lock.paid'))
        ->assertDontSee('wire:model.live.debounce.300ms="customerSearch"', false)
        ->call('selectCustomer', $other->id)
        ->call('saveDetails')
        ->assertHasErrors(['order']);

    expect($order->fresh()->customer_id)->not->toBe($other->id);
});

it('edits both addresses inline and copies billing to shipping when asked', function (): void {
    $staff = $this->createStaff();
    $order = inlineOrder();

    Livewire::actingAs($staff)->test(Show::class, ['order' => $order])
        ->call('startEditAddresses')
        ->assertSet('editingAddresses', true)
        ->set('details.billing.line1', 'Inline street 9')
        ->set('details.billing.city', 'Ghent')
        ->set('details.billing.country', 'be')
        ->set('details.shippingSameAsBilling', true)
        ->call('saveAddresses')
        ->assertHasNoErrors()
        ->assertSet('editingAddresses', false);

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

it('shows which lines are recurring and links their subscription', function (): void {
    $staff = $this->createStaff();
    $order = inlineOrder();
    $product = Product::factory()->create(['name' => 'Hosting Plan']);
    $item = OrderItem::factory()->for($order)->create(['product_id' => $product->id, 'label' => 'Hosting Plan']);
    $subscription = Subscription::query()->create([
        'number' => 'SUB-INLINE-0001',
        'customer_id' => $order->customer_id,
        'customer_email' => $order->customer_email,
        'customer_name' => $order->customer_name,
        'product_id' => $product->id,
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

    Livewire::actingAs($staff)->test(Show::class, ['order' => $order])
        ->assertSee(__('admin.orders.inline.recurring'))
        ->assertSee('3 × '.__('subscriptions::interval.month'))
        ->assertSee(route('admin.subscriptions.show', $subscription), false)
        ->assertSee(__('admin.orders.inline.subscription', ['number' => 'SUB-INLINE-0001']));
});

it('lists the linked invoices clearly in the main column, newest first, including replaced ones', function (): void {
    $staff = $this->createStaff();
    $order = inlineOrder();
    $first = app(IssueInvoiceFromOrder::class)->handle($order);
    $first->forceFill(['created_at' => now()->subDay()])->save();
    $replacement = app(ReissueOrderInvoice::class)->handle($order, $staff, 'test');

    $html = Livewire::actingAs($staff)->test(Show::class, ['order' => $order])->html();

    $items = strpos($html, 'id="order-items-heading"');
    $invoices = strpos($html, 'id="order-invoices-heading"');
    $addresses = strpos($html, 'id="order-addresses-heading"');
    $side = strpos($html, 'class="ag-order-layout__side"');

    expect($invoices)->toBeGreaterThan($items)
        ->and($invoices)->toBeLessThan($addresses)
        ->and($invoices)->toBeLessThan($side)
        ->and(strpos($html, $replacement->number))->toBeLessThan(strpos($html, $first->number))
        ->and($html)->toContain('href="'.route('admin.invoices.show', $replacement).'"')
        ->and($html)->toContain('ag-badge ag-badge--info">'.__('admin.invoices.status.issued'))
        ->and($html)->toContain('ag-badge ag-badge--muted">'.__('admin.invoices.status.void'))
        ->and($html)->not->toContain('id="documents-heading"');
});
