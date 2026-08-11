<?php

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Payments\CompleteDevelopmentPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Models\Order;
use App\Models\Payment;
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

    expect($order->payment->method)->toBe(PaymentMethod::Development)
        ->and($order->payment->status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->status->value)->toBe('paid');
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

test('development payment action fails closed in production', function () {
    config(['agovena.payments.allow_development_instant_pay' => true]);
    app()['env'] = 'production';

    $order = Order::factory()->create();
    Payment::factory()->create([
        'order_id' => $order->id,
        'amount' => 1000,
        'currency' => 'EUR',
        'method' => PaymentMethod::Development,
        'status' => PaymentStatus::Pending,
    ]);

    expect(fn () => app(CompleteDevelopmentPayment::class)->handle($order->fresh(['payment'])))
        ->toThrow(ValidationException::class);
});
