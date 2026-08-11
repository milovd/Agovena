<?php

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Payments\RecordManualPayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Admin\Orders\Show;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('staff with permission can record manual payment', function () {
    $staff = $this->createStaff();
    $order = createPendingOrder();

    $payment = app(RecordManualPayment::class)->handle($order, $staff, 'BANK-1');

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->reference)->toBe('BANK-1')
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(Payment::query()->where('order_id', $order->id)->count())->toBe(1);
});

test('recording payment twice is idempotent', function () {
    $staff = $this->createStaff();
    $order = createPendingOrder();

    $action = app(RecordManualPayment::class);
    $first = $action->handle($order, $staff);
    $second = $action->handle($order->fresh(), $staff);

    expect($second->id)->toBe($first->id)
        ->and($second->status)->toBe(PaymentStatus::Paid)
        ->and(Payment::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('staff without payments.record cannot record payment', function () {
    $staff = $this->createStaff([], ['orders.view', 'dashboard.view', 'products.view']);
    $order = createPendingOrder();

    $this->actingAs($staff, 'staff');

    Livewire::test(Show::class, ['order' => $order])
        ->call('startRecordPayment')
        ->assertForbidden();
});

test('admin order detail shows record payment action for pending payments', function () {
    $staff = $this->createStaff();
    $order = createPendingOrder();

    $this->actingAs($staff, 'staff')
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('Record payment', false);
});

function createPendingOrder(): Order
{
    $product = Product::factory()->active()->create(['price_amount' => 1200]);
    $cart = app(CartService::class);
    $cart->clear();
    $cart->add($product->id, 1);

    return app(PlaceOrder::class)->handle([
        'customer_name' => 'Pay Me',
        'customer_email' => 'pay@example.com',
        'idempotency_key' => (string) Str::uuid(),
    ]);
}
