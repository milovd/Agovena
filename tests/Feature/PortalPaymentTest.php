<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Payments\StartOrderPayment;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Customer\Account\OrderShow;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use Livewire\Livewire;

function pendingCustomerOrder(): array
{
    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 2500]);
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
    ]);

    return [$customer, $order->fresh(['payment', 'invoice'])];
}

test('checkout issues an unpaid invoice and keeps the order payable', function () {
    [, $order] = pendingCustomerOrder();

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->payment->status)->toBe(PaymentStatus::Pending)
        ->and($order->isAwaitingPayment())->toBeTrue()
        ->and(Invoice::query()->where('order_id', $order->id)->value('status'))->toBe(InvoiceStatus::Issued);
});

test('development pay now completes a pending order without trusting a return url', function () {
    config(['agovena.payments.allow_development_instant_pay' => true]);
    [$customer, $order] = pendingCustomerOrder();

    $attempt = app(StartOrderPayment::class)->handle(
        $order,
        'development',
        route('customer.orders.show', $order),
        route('customer.orders.show', $order),
    );

    expect($attempt->status->value)->toBe('succeeded')
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and(Invoice::query()->where('order_id', $order->id)->value('status'))->toBe(InvoiceStatus::Paid);
});

test('customer portal can pay a pending order with the development gateway', function () {
    config(['agovena.payments.allow_development_instant_pay' => true]);
    [$customer, $order] = pendingCustomerOrder();

    Livewire::actingAs($customer->user)
        ->test(OrderShow::class, ['order' => $order])
        ->set('pay_gateway', 'development')
        ->call('payNow')
        ->assertHasNoErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

test('manual initiate does not mark the order paid', function () {
    [$customer, $order] = pendingCustomerOrder();

    app(StartOrderPayment::class)->handle(
        $order,
        'manual',
        route('storefront.order.confirmation', $order),
        route('storefront.order.confirmation', $order),
        'retry-manual-1',
    );

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->payment->fresh()->status)->toBe(PaymentStatus::Pending);
});
