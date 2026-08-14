<?php

declare(strict_types=1);

use Agovena\Modules\Inventory\InventoryService;
use Agovena\Modules\Subscriptions\Enums\RenewalStatus;
use Agovena\Modules\Subscriptions\Enums\SubscriptionInterval;
use Agovena\Modules\Subscriptions\Enums\SubscriptionStatus;
use Agovena\Modules\Subscriptions\Models\Subscription;
use Agovena\Modules\Subscriptions\Models\SubscriptionRenewal;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Payments\RecordRefund;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('subscription renewal period is unique per subscription', function () {
    app(ModuleManager::class)->enable('subscriptions');

    $subscription = Subscription::query()->create([
        'number' => 'SUB-CONC-1',
        'customer_email' => 'renew@example.test',
        'status' => SubscriptionStatus::Active,
        'interval' => SubscriptionInterval::Month,
        'interval_count' => 1,
        'price_amount' => 1000,
        'currency' => 'EUR',
        'quantity' => 1,
        'current_period_start' => now()->subMonth(),
        'current_period_end' => now(),
        'next_billing_at' => now(),
    ]);

    $start = now()->startOfSecond();
    $end = $start->copy()->addMonth();

    SubscriptionRenewal::query()->create([
        'subscription_id' => $subscription->id,
        'period_start' => $start,
        'period_end' => $end,
        'status' => RenewalStatus::Pending,
    ]);

    expect(fn () => SubscriptionRenewal::query()->create([
        'subscription_id' => $subscription->id,
        'period_start' => $start,
        'period_end' => $end,
        'status' => RenewalStatus::Pending,
    ]))->toThrow(UniqueConstraintViolationException::class)
        ->and(SubscriptionRenewal::query()->where('subscription_id', $subscription->id)->count())->toBe(1);
});

test('payment webhook events are unique per gateway and external id', function () {
    PaymentWebhookEvent::query()->create([
        'gateway_id' => 'fake-webhook',
        'external_event_id' => 'evt-unique-1',
        'status' => 'paid',
        'processing_status' => 'processed',
    ]);

    expect(fn () => PaymentWebhookEvent::query()->create([
        'gateway_id' => 'fake-webhook',
        'external_event_id' => 'evt-unique-1',
        'status' => 'paid',
        'processing_status' => 'received',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('payment attempts cannot share a provider external id on the same gateway', function () {
    $order = Order::factory()->create();
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'status' => PaymentStatus::Pending,
    ]);

    PaymentAttempt::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $order->id,
        'gateway_id' => 'fake-webhook',
        'status' => PaymentAttemptStatus::Processing,
        'external_id' => 'ext-shared-1',
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'initiated_at' => now(),
    ]);

    expect(fn () => PaymentAttempt::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $order->id,
        'gateway_id' => 'fake-webhook',
        'status' => PaymentAttemptStatus::Processing,
        'external_id' => 'ext-shared-1',
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'initiated_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('refunds cannot exceed the remaining refundable amount', function () {
    $staff = $this->createStaff();
    $order = Order::factory()->create();
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'method' => 'manual',
        'status' => PaymentStatus::Paid,
        'amount' => 1500,
        'paid_at' => now(),
    ]);

    app(RecordRefund::class)->handle($payment, $staff, 1500, 'Full refund');

    expect(fn () => app(RecordRefund::class)->handle($payment->fresh(), $staff, 1, 'Too much'))
        ->toThrow(ValidationException::class);
});

test('inventory cannot decrement below available stock', function () {
    app(ModuleManager::class)->enable('inventory');
    $product = Product::factory()->active()->create();
    app(ProductCapabilityManager::class)->enable($product, 'inventory');
    $inventory = app(InventoryService::class);
    $inventory->setQuantity($product, 1);

    $inventory->decrement($product, 1);

    expect(fn () => $inventory->decrement($product, 1))->toThrow(ValidationException::class)
        ->and($inventory->quantityFor($product->fresh()))->toBe(0);
});
