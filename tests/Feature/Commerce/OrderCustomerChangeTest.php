<?php

declare(strict_types=1);

use App\Agovena\Invoices\IssueInvoiceFromOrder;
use App\Agovena\Orders\ChangeOrderCustomer;
use App\Agovena\Orders\ReissueOrderInvoice;
use App\Agovena\Orders\UnpaidOrderEditGuard;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\DiscountRedemption;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function editableOrder(array $attributes = []): Order
{
    $email = 'old-'.Str::lower(Str::random(8)).'@example.test';
    $owner = Customer::factory()->create(['name' => 'Old Owner', 'email' => $email]);
    $order = Order::factory()->create([
        'customer_id' => $owner->id,
        'customer_name' => 'Old Owner',
        'customer_email' => $email,
        'status' => OrderStatus::Pending,
        'storefront_token' => 'old-token-'.Str::random(8),
        ...$attributes,
    ]);
    Payment::factory()->for($order)->create(['status' => PaymentStatus::Pending, 'amount' => $order->total_amount]);

    return $order->fresh(['payment']);
}

it('allows editing an unpaid order and explains every blocking state', function (): void {
    $guard = app(UnpaidOrderEditGuard::class);
    $order = editableOrder();
    expect($guard->blockingReason($order))->toBeNull();

    $paid = editableOrder();
    $paid->payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
    $paid->update(['status' => OrderStatus::Paid]);
    expect($guard->blockingReason($paid->fresh()))->toBe(__('admin.orders.edit_lock.paid'));

    $cancelled = editableOrder(['status' => OrderStatus::Cancelled]);
    expect($guard->blockingReason($cancelled))->toBe(__('admin.orders.edit_lock.closed'));

    $attempting = editableOrder();
    PaymentAttempt::query()->create([
        'payment_id' => $attempting->payment->id,
        'order_id' => $attempting->id,
        'gateway_id' => 'manual',
        'status' => PaymentAttemptStatus::Pending,
        'amount' => 100,
        'currency' => 'EUR',
    ]);
    expect($guard->blockingReason($attempting->fresh()))->toBe(__('admin.orders.edit_lock.payment_in_progress'));

    $credited = editableOrder(['credit_amount' => 500]);
    expect($guard->blockingReason($credited, forCustomerChange: true))->toBe(__('admin.orders.edit_lock.credit_applied'))
        ->and(__('admin.orders.edit_lock.credit_applied', [], 'nl'))->not->toBe('admin.orders.edit_lock.credit_applied');
});

it('replaces an issued unpaid invoice with a new one that follows the order', function (): void {
    $staff = $this->createStaff();
    $order = editableOrder();
    $first = app(IssueInvoiceFromOrder::class)->handle($order);
    $order->update(['customer_name' => 'Renamed']);

    $replacement = app(ReissueOrderInvoice::class)->handle($order->fresh(), $staff, 'customer_changed');

    expect($first->fresh()->status)->toBe(InvoiceStatus::Void)
        ->and($replacement->id)->not->toBe($first->id)
        ->and($replacement->number)->not->toBe($first->number)
        ->and($replacement->status)->toBe(InvoiceStatus::Issued)
        ->and($replacement->customer_name)->toBe('Renamed')
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(AuditLog::query()->where('action', 'invoice.reissued')->exists())->toBeTrue();
});

it('moves an unpaid order to another customer, rotates the pay link and reissues the invoice', function (): void {
    $staff = $this->createStaff();
    $order = editableOrder();
    $old = app(IssueInvoiceFromOrder::class)->handle($order);
    $redemption = DiscountRedemption::query()->create([
        'discount_code_id' => DiscountCode::query()->create(['code' => 'WELCOME', 'type' => 'fixed', 'value' => 100, 'currency' => 'EUR', 'is_active' => true])->id,
        'order_id' => $order->id,
        'customer_id' => $order->customer_id,
        'code' => 'WELCOME',
        'amount' => 100,
    ]);
    $newOwner = Customer::factory()->create(['name' => 'New Owner', 'email' => 'new@example.test']);

    $updated = app(ChangeOrderCustomer::class)->handle($order, $newOwner, $staff);

    expect($updated->customer_id)->toBe($newOwner->id)
        ->and($updated->customer_name)->toBe('New Owner')
        ->and($updated->customer_email)->toBe('new@example.test')
        ->and($updated->storefront_token)->not->toBe($order->storefront_token)
        ->and($redemption->fresh()->customer_id)->toBe($newOwner->id)
        ->and($old->fresh()->status)->toBe(InvoiceStatus::Void)
        ->and(Invoice::query()->where('order_id', $order->id)->where('status', InvoiceStatus::Issued)->sole()->customer_email)->toBe('new@example.test');

    $audit = AuditLog::query()->where('action', 'order.customer_changed')->sole();
    expect($audit->properties['from_customer_id'] ?? null)->toBe($order->customer_id)
        ->and($audit->properties['to_customer_id'] ?? null)->toBe($newOwner->id);
});

it('refuses to move a paid order or an order paid with account credit', function (): void {
    $staff = $this->createStaff();
    $newOwner = Customer::factory()->create();

    $paid = editableOrder(['status' => OrderStatus::Paid]);
    $paid->payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
    expect(fn () => app(ChangeOrderCustomer::class)->handle($paid->fresh(), $newOwner, $staff))
        ->toThrow(ValidationException::class);

    $credited = editableOrder(['credit_amount' => 300]);
    expect(fn () => app(ChangeOrderCustomer::class)->handle($credited, $newOwner, $staff))
        ->toThrow(ValidationException::class);

    expect($paid->fresh()->customer_id)->not->toBe($newOwner->id)
        ->and($credited->fresh()->customer_id)->not->toBe($newOwner->id);
});

it('does nothing when the customer stays the same', function (): void {
    $staff = $this->createStaff();
    $order = editableOrder();
    $invoice = app(IssueInvoiceFromOrder::class)->handle($order);

    app(ChangeOrderCustomer::class)->handle($order, $order->customer, $staff);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Issued)
        ->and($order->fresh()->storefront_token)->toBe($order->storefront_token)
        ->and(AuditLog::query()->where('action', 'order.customer_changed')->exists())->toBeFalse();
});
