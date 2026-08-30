<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Orders\CancelUnpaidOrder;
use App\Agovena\Orders\UnpaidOrderCancelSource;
use App\Agovena\Payments\ApplyNormalizedPaymentStatus;
use App\Agovena\Payments\Contracts\CancelsPayments;
use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\Gateways\ManualPaymentGateway;
use App\Agovena\Payments\HandlePaymentWebhook;
use App\Agovena\Payments\InitiateGatewayPayment;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Payments\PaymentInitiationResult;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Payments\RefundRequest;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Events\PaymentRecorded;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Support\CreatesStaff;
use Tests\Support\FakeWebhookGateway;

uses(CreatesStaff::class);

function placePendingOrderPayment(): Payment
{
    config(['agovena.payments.allow_development_instant_pay' => false]);
    if (! app(PaymentGatewayRegistry::class)->has('manual')) {
        app(PaymentGatewayRegistry::class)->register(app(ManualPaymentGateway::class));
    }

    $product = Product::factory()->active()->create(['price_amount' => 2500]);
    app(CartService::class)->add($product->id, 1);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'Pay Buyer',
        'customer_email' => 'pay@example.test',
        'payment_method' => 'manual',
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

test('manual payment gateway can still refund when registered in core tests', function () {
    $gateway = app(ManualPaymentGateway::class);
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

test('already paid manual payment retries do not replay paid fulfillment events', function () {
    $payment = placePendingOrderPayment();
    $staff = $this->createStaff();
    $action = app(RecordManualPayment::class);

    $action->handle($payment->order, $staff, 'MANUAL-1');
    Event::fake([OrderPaid::class]);

    $action->handle($payment->fresh()->order, $staff, 'MANUAL-1');

    Event::assertNotDispatched(OrderPaid::class);
    Event::assertNotDispatched(PaymentRecorded::class);
});

test('initiate gateway payment creates a payment attempt without polluting order', function () {
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

test('completed gateway initiation settles local payment and order exactly once', function () {
    $payment = placePendingOrderPayment();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('completed-gateway');
    $gateway->shouldReceive('initiate')->once()->andReturn(PaymentInitiationResult::completed('completed-1'));
    app(PaymentGatewayRegistry::class)->register($gateway);
    Event::fake([OrderPaid::class, PaymentRecorded::class]);

    $attempt = app(InitiateGatewayPayment::class)->handle(
        $payment,
        'completed-gateway',
        returnUrl: 'https://example.test/return',
        cancelUrl: 'https://example.test/cancel',
        idempotencyKey: 'completed-attempt-1',
    );
    $again = app(InitiateGatewayPayment::class)->handle(
        $payment,
        'completed-gateway',
        returnUrl: 'https://example.test/return',
        cancelUrl: 'https://example.test/cancel',
        idempotencyKey: 'completed-attempt-1',
    );

    expect($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Succeeded)
        ->and($again->id)->toBe($attempt->id)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Paid);
    Event::assertDispatchedTimes(PaymentRecorded::class, 1);
    Event::assertDispatchedTimes(OrderPaid::class, 1);
});

test('provider cancellation failure keeps the order pending for manual reconciliation', function () {
    $payment = placePendingOrderPayment();
    $gateway = Mockery::mock(PaymentGateway::class, CancelsPayments::class);
    $gateway->shouldReceive('id')->andReturn('failing-cancel');
    $gateway->shouldReceive('cancel')->once()->andThrow(new RuntimeException('provider unavailable'));
    app(PaymentGatewayRegistry::class)->register($gateway);
    $payment->update(['method' => 'failing-cancel']);

    expect(fn () => app(CancelUnpaidOrder::class)->handle(
        $payment->order->fresh(['invoice', 'payment']),
        UnpaidOrderCancelSource::Customer,
    ))->toThrow(ValidationException::class);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Pending)
        ->and($payment->fresh()->reconciliation_status)->toBe('manual_review')
        ->and($payment->fresh()->reconciliation_meta['reason'] ?? null)->toBe('provider_cancel_failed');
});

test('scheduler provider cancellation failure keeps the order payable for reconciliation', function () {
    $payment = placePendingOrderPayment();
    $gateway = Mockery::mock(PaymentGateway::class, CancelsPayments::class);
    $gateway->shouldReceive('id')->andReturn('scheduler-failing-cancel');
    $gateway->shouldReceive('cancel')->once()->andThrow(new RuntimeException('provider unavailable'));
    app(PaymentGatewayRegistry::class)->register($gateway);
    $payment->update(['method' => 'scheduler-failing-cancel']);

    $result = app(CancelUnpaidOrder::class)->handle(
        $payment->order->fresh(['invoice', 'payment']),
        UnpaidOrderCancelSource::Scheduler,
    );

    expect($result->status)->toBe(OrderStatus::Pending)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->reconciliation_status)->toBe('manual_review');
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
    Event::fake([OrderPaid::class]);
    $second = app(HandlePaymentWebhook::class)->handle('fake-webhook', $request);

    expect($first->duplicate)->toBeFalse()
        ->and($second->duplicate)->toBeTrue()
        ->and($second->event->id)->toBe($first->event->id)
        ->and(PaymentWebhookEvent::query()->count())->toBe(1)
        ->and($first->event->payload['token'] ?? null)->toBe('[REDACTED]')
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Succeeded)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Paid);
    Event::assertNotDispatched(OrderPaid::class);
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

test('duplicate webhook payload cannot retarget a paid replay to another payment', function () {
    $gateway = new FakeWebhookGateway;
    app(PaymentGatewayRegistry::class)->register($gateway);

    $firstPayment = placePendingOrderPayment();
    $firstAttempt = PaymentAttempt::query()->create([
        'payment_id' => $firstPayment->id,
        'order_id' => $firstPayment->order_id,
        'gateway_id' => 'fake-webhook',
        'status' => PaymentAttemptStatus::Processing,
        'external_id' => 'ext-retarget-a',
        'amount' => $firstPayment->amount,
        'currency' => $firstPayment->currency,
        'initiated_at' => now(),
    ]);
    $secondPayment = placePendingOrderPayment();
    $secondAttempt = PaymentAttempt::query()->create([
        'payment_id' => $secondPayment->id,
        'order_id' => $secondPayment->order_id,
        'gateway_id' => 'fake-webhook',
        'status' => PaymentAttemptStatus::Processing,
        'external_id' => 'ext-retarget-b',
        'amount' => $secondPayment->amount,
        'currency' => $secondPayment->currency,
        'initiated_at' => now(),
    ]);

    $firstRequest = Request::create('/webhooks/payments/fake-webhook', 'POST', [
        'event_id' => 'evt-retarget-1',
        'payment_id' => $firstAttempt->external_id,
        'status' => 'paid',
    ], server: ['HTTP_X_WEBHOOK_SECRET' => 'test-secret']);
    app(HandlePaymentWebhook::class)->handle('fake-webhook', $firstRequest);

    Event::fake([OrderPaid::class]);
    $duplicateRequest = Request::create('/webhooks/payments/fake-webhook', 'POST', [
        'event_id' => 'evt-retarget-1',
        'payment_id' => $secondAttempt->external_id,
        'status' => 'paid',
    ], server: ['HTTP_X_WEBHOOK_SECRET' => 'test-secret']);
    app(HandlePaymentWebhook::class)->handle('fake-webhook', $duplicateRequest);

    expect($secondPayment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($secondPayment->fresh()->order->status)->toBe(OrderStatus::Pending);
    Event::assertNotDispatched(OrderPaid::class);
});

test('a late paid status does not downgrade a refunded payment', function () {
    $payment = placePendingOrderPayment();
    $attempt = PaymentAttempt::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $payment->order_id,
        'gateway_id' => 'manual',
        'status' => PaymentAttemptStatus::Processing,
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'idempotency_key' => 'refunded-paid-race-1',
        'initiated_at' => now(),
    ]);
    $payment->update(['status' => PaymentStatus::Refunded]);

    $result = app(ApplyNormalizedPaymentStatus::class)->handle($attempt, PaymentStatus::Paid);

    expect($result->applied)->toBeFalse()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Processing);
});

test('paid status processing does not replay fulfillment when payment and order are already paid', function () {
    $payment = placePendingOrderPayment();
    $attempt = PaymentAttempt::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $payment->order_id,
        'gateway_id' => 'manual',
        'status' => PaymentAttemptStatus::Succeeded,
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'idempotency_key' => 'already-paid-replay-1',
        'initiated_at' => now(),
        'completed_at' => now(),
    ]);
    $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
    $payment->order()->update(['status' => OrderStatus::Paid]);
    Event::fake([OrderPaid::class, PaymentRecorded::class]);

    app(ApplyNormalizedPaymentStatus::class)->handle($attempt, PaymentStatus::Paid);

    Event::assertNotDispatched(OrderPaid::class);
    Event::assertNotDispatched(PaymentRecorded::class);
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

test('paid webhook after unpaid cancel does not resurrect the order', function () {
    $gateway = new FakeWebhookGateway;
    app(PaymentGatewayRegistry::class)->register($gateway);

    $payment = placePendingOrderPayment();
    PaymentAttempt::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $payment->order_id,
        'gateway_id' => 'fake-webhook',
        'status' => PaymentAttemptStatus::Processing,
        'external_id' => 'ext-cancel-race-1',
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'initiated_at' => now(),
    ]);

    app(CancelUnpaidOrder::class)->handle(
        $payment->order->fresh(['invoice', 'payment']),
        UnpaidOrderCancelSource::Customer,
    );

    $request = Request::create('/webhooks/payments/fake-webhook', 'POST', [
        'event_id' => 'evt-after-cancel',
        'payment_id' => 'ext-cancel-race-1',
        'status' => 'paid',
    ], server: ['HTTP_X_WEBHOOK_SECRET' => 'test-secret']);

    $result = app(HandlePaymentWebhook::class)->handle('fake-webhook', $request);

    expect($result->event->processing_status)->toBe('ignored')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Cancelled)
        ->and($payment->fresh()->order->invoice?->status)->toBe(InvoiceStatus::Void);
});

test('paid status retry repairs an order left pending after a payment commit', function () {
    $payment = placePendingOrderPayment();
    $attempt = PaymentAttempt::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $payment->order_id,
        'gateway_id' => 'manual',
        'status' => PaymentAttemptStatus::Processing,
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'idempotency_key' => 'recovery-key-1',
        'initiated_at' => now(),
    ]);
    $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
    Event::fake([OrderPaid::class, PaymentRecorded::class]);

    app(ApplyNormalizedPaymentStatus::class)->handle($attempt, PaymentStatus::Paid);

    expect($payment->fresh()->order->status)->toBe(OrderStatus::Paid)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Succeeded);
    Event::assertDispatched(OrderPaid::class);
});
