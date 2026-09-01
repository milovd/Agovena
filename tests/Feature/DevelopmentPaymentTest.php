<?php

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Payments\CompleteDevelopmentPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Events\OrderPaid;
use App\Events\PaymentRecorded;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Product;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('development instant payment completes order when enabled', function () {
    config(['agovena.payments.allow_development_instant_pay' => true]);

    $product = Product::factory()->create([
        'status' => ProductStatus::Active,
        'price_amount' => 1500,
        'currency' => 'EUR',
    ]);

    $cart = app(CartService::class);
    $cart->add($product->id, 1);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'Dev Buyer',
        'customer_email' => 'dev@example.com',
        'idempotency_key' => 'dev-pay-1',
        'payment_method' => PaymentMethod::Development->value,
    ]);

    expect($order->payment->method)->toBe('development')
        ->and($order->payment->status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->status->value)->toBe('paid')
        ->and(PaymentAttempt::query()->where('payment_id', $order->payment->id)->value('status')?->value)
        ->toBe('succeeded');
});

test('repeating development completion does not replay paid fulfillment events', function () {
    config(['agovena.payments.allow_development_instant_pay' => true]);

    $order = Order::factory()->create(['status' => 'paid']);
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'amount' => 1000,
        'currency' => 'EUR',
        'method' => 'development',
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);
    app(CompleteDevelopmentPayment::class)->handle($order->fresh(['payment']));
    Event::fake([OrderPaid::class, PaymentRecorded::class]);

    app(CompleteDevelopmentPayment::class)->handle($order->fresh(['payment']));

    Event::assertNotDispatched(OrderPaid::class);
    Event::assertNotDispatched(PaymentRecorded::class);
});

test('development payment is rejected when disabled', function () {
    config(['agovena.payments.allow_development_instant_pay' => false]);

    $product = Product::factory()->create(['status' => ProductStatus::Active]);
    $cart = app(CartService::class);
    $cart->add($product->id, 1);

    expect(fn () => app(PlaceOrder::class)->handle([
        'customer_name' => 'Dev Buyer',
        'customer_email' => 'dev@example.com',
        'payment_method' => PaymentMethod::Development->value,
    ]))->toThrow(ValidationException::class);
});

test('idempotent checkout retry resumes pending development settlement', function () {
    config(['agovena.payments.allow_development_instant_pay' => true]);

    $customer = Customer::factory()->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'status' => 'pending',
        'idempotency_key' => 'dev-retry-1',
        'idempotency_owner_hash' => hash('sha256', 'customer|'.$customer->id),
    ]);
    Payment::factory()->create([
        'order_id' => $order->id,
        'amount' => 1000,
        'currency' => 'EUR',
        'method' => 'development',
        'status' => PaymentStatus::Pending,
    ]);

    $retry = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'idempotency_key' => 'dev-retry-1',
        'payment_method' => PaymentMethod::Development->value,
    ]);

    expect($retry->fresh()->status->value)->toBe('paid')
        ->and($retry->fresh()->payment?->status)->toBe(PaymentStatus::Paid);
});

test('development payment action fails closed in production', function () {
    config(['agovena.payments.allow_development_instant_pay' => true]);
    app()['env'] = 'production';

    $order = Order::factory()->create();
    Payment::factory()->create([
        'order_id' => $order->id,
        'amount' => 1000,
        'currency' => 'EUR',
        'method' => 'development',
        'status' => PaymentStatus::Pending,
    ]);

    expect(fn () => app(CompleteDevelopmentPayment::class)->handle($order->fresh(['payment'])))
        ->toThrow(ValidationException::class);
});

test('development completion refuses a refunded payment', function () {
    config(['agovena.payments.allow_development_instant_pay' => true]);

    $order = Order::factory()->create();
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'amount' => 1000,
        'currency' => 'EUR',
        'method' => 'development',
        'status' => PaymentStatus::Refunded,
    ]);

    expect(fn () => app(CompleteDevelopmentPayment::class)->handle($order->fresh(['payment'])))
        ->toThrow(ValidationException::class);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
});
