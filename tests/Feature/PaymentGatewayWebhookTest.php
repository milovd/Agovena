<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Payments\Gateways\ManualPaymentGateway;
use App\Agovena\Payments\HandlePaymentWebhook;
use App\Agovena\Payments\InitiateGatewayPayment;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Payments\RefundRequest;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Models\Product;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Support\FakeWebhookGateway;

function placePendingOrderPayment(): Payment
{
    $product = Product::factory()->active()->create(['price_amount' => 2500]);
    app(CartService::class)->add($product->id, 1);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'Pay Buyer',
        'customer_email' => 'pay@example.test',
        'billing' => AddressData::fromArray([
            'name' => 'Pay Buyer',
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
    ]);

    return $order->payment()->firstOrFail();
}

test('manual payment gateway registers via extension and supports refunds', function () {
    app(ExtensionManager::class)->enable('manual-payment');

    $gateway = app(PaymentGatewayRegistry::class)->get('manual');
    expect($gateway)->toBeInstanceOf(ManualPaymentGateway::class)
        ->and($gateway->capabilities()->refunds)->toBeTrue()
        ->and($gateway->capabilities()->partialRefunds)->toBeTrue();

    $payment = placePendingOrderPayment();
    $result = $gateway->refund(new RefundRequest(
        payment: $payment,
        amount: $payment->amount,
        currency: $payment->currency,
    ));

    expect($result->success)->toBeTrue();
});

test('initiate gateway payment creates a payment attempt without polluting order', function () {
    app(ExtensionManager::class)->enable('manual-payment');
    $payment = placePendingOrderPayment();

    $attempt = app(InitiateGatewayPayment::class)->handle(
        $payment,
        'manual',
        returnUrl: 'https://example.test/return',
        cancelUrl: 'https://example.test/cancel',
        idempotencyKey: 'attempt-key-1',
    );

    expect($attempt)->toBeInstanceOf(PaymentAttempt::class)
        ->and($attempt->gateway_id)->toBe('manual')
        ->and($attempt->status)->toBe(PaymentAttemptStatus::Processing)
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Pending)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending);

    $again = app(InitiateGatewayPayment::class)->handle(
        $payment,
        'manual',
        returnUrl: 'https://example.test/return',
        cancelUrl: 'https://example.test/cancel',
        idempotencyKey: 'attempt-key-1',
    );

    expect($again->id)->toBe($attempt->id)
        ->and(PaymentAttempt::query()->count())->toBe(1);
});

test('payment webhook rejects failed verification', function () {
    app(PaymentGatewayRegistry::class)->register(new FakeWebhookGateway);

    $request = Request::create('/webhooks/payments/fake-webhook', 'POST', [
        'event_id' => 'evt-1',
        'payment_id' => 'ext-1',
        'status' => 'paid',
    ], server: ['HTTP_X_WEBHOOK_SECRET' => 'wrong']);

    expect(fn () => app(HandlePaymentWebhook::class)->handle('fake-webhook', $request))
        ->toThrow(AccessDeniedHttpException::class);
});

test('payment webhook is idempotent for duplicate external event ids', function () {
    $gateway = new FakeWebhookGateway;
    app(PaymentGatewayRegistry::class)->register($gateway);

    $payment = placePendingOrderPayment();
    $attempt = PaymentAttempt::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $payment->order_id,
        'gateway_id' => 'fake-webhook',
        'status' => PaymentAttemptStatus::Processing,
        'external_id' => 'ext-pay-42',
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'initiated_at' => now(),
    ]);

    $payload = [
        'event_id' => 'evt-dup-1',
        'payment_id' => 'ext-pay-42',
        'status' => 'paid',
        'token' => 'should-be-redacted',
    ];

    $request = Request::create('/webhooks/payments/fake-webhook', 'POST', $payload, server: [
        'HTTP_X_WEBHOOK_SECRET' => 'test-secret',
    ]);

    $first = app(HandlePaymentWebhook::class)->handle('fake-webhook', $request);
    $second = app(HandlePaymentWebhook::class)->handle('fake-webhook', $request);

    expect($first->duplicate)->toBeFalse()
        ->and($second->duplicate)->toBeTrue()
        ->and($second->event->id)->toBe($first->event->id)
        ->and(PaymentWebhookEvent::query()->count())->toBe(1)
        ->and($first->event->payload['token'] ?? null)->toBe('[redacted]')
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Succeeded)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Paid);
});

test('stale failure webhook does not downgrade a paid payment', function () {
    $gateway = new FakeWebhookGateway;
    app(PaymentGatewayRegistry::class)->register($gateway);

    $payment = placePendingOrderPayment();
    PaymentAttempt::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $payment->order_id,
        'gateway_id' => 'fake-webhook',
        'status' => PaymentAttemptStatus::Processing,
        'external_id' => 'ext-stale-1',
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'initiated_at' => now(),
    ]);

    $paid = Request::create('/webhooks/payments/fake-webhook', 'POST', [
        'event_id' => 'evt-paid-1',
        'payment_id' => 'ext-stale-1',
        'status' => 'paid',
    ], server: ['HTTP_X_WEBHOOK_SECRET' => 'test-secret']);
    app(HandlePaymentWebhook::class)->handle('fake-webhook', $paid);

    $failed = Request::create('/webhooks/payments/fake-webhook', 'POST', [
        'event_id' => 'evt-failed-later',
        'payment_id' => 'ext-stale-1',
        'status' => 'failed',
    ], server: ['HTTP_X_WEBHOOK_SECRET' => 'test-secret']);
    app(HandlePaymentWebhook::class)->handle('fake-webhook', $failed);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Paid);
});

test('unprocessed webhook rows are completed on retry instead of skipped', function () {
    $gateway = new FakeWebhookGateway;
    app(PaymentGatewayRegistry::class)->register($gateway);

    $payment = placePendingOrderPayment();
    PaymentAttempt::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $payment->order_id,
        'gateway_id' => 'fake-webhook',
        'status' => PaymentAttemptStatus::Processing,
        'external_id' => 'ext-retry-1',
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'initiated_at' => now(),
    ]);

    PaymentWebhookEvent::query()->create([
        'gateway_id' => 'fake-webhook',
        'external_event_id' => 'evt-crash-1',
        'external_payment_id' => 'ext-retry-1',
        'status' => 'paid',
        'processing_status' => 'received',
        'payload' => [],
    ]);

    $request = Request::create('/webhooks/payments/fake-webhook', 'POST', [
        'event_id' => 'evt-crash-1',
        'payment_id' => 'ext-retry-1',
        'status' => 'paid',
    ], server: ['HTTP_X_WEBHOOK_SECRET' => 'test-secret']);

    $result = app(HandlePaymentWebhook::class)->handle('fake-webhook', $request);

    expect($result->duplicate)->toBeFalse()
        ->and($result->event->processing_status)->toBe('processed')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and(PaymentWebhookEvent::query()->where('external_event_id', 'evt-crash-1')->count())->toBe(1);
});

test('http webhook route returns 403 on verification failure', function () {
    app(PaymentGatewayRegistry::class)->register(new FakeWebhookGateway);

    $this->postJson(route('webhooks.payments', ['gateway' => 'fake-webhook']), [
        'event_id' => 'evt-http-1',
        'status' => 'paid',
    ], [
        'X-Webhook-Secret' => 'nope',
    ])->assertForbidden()
        ->assertJson(['ok' => false]);
});
