<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Orders\CancelUnpaidOrder;
use App\Agovena\Orders\UnpaidOrderCancelSource;
use App\Agovena\Payments\ApplyNormalizedPaymentStatus;
use App\Agovena\Payments\CompleteDirectPayment;
use App\Agovena\Payments\Contracts\CancelsPayments;
use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\Gateways\ManualPaymentGateway;
use App\Agovena\Payments\HandlePaymentWebhook;
use App\Agovena\Payments\InitiateGatewayPayment;
use App\Agovena\Payments\PaymentGatewayCapabilities;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Payments\PaymentInitiationResult;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Payments\RecordRefund;
use App\Agovena\Payments\RefundRequest;
use App\Agovena\Payments\RefundResult;
use App\Agovena\Payments\StartOrderPayment;
use App\Agovena\Payments\WebhookPayload;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Events\OrderPaid;
use App\Events\PaymentRecorded;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Models\Product;
use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

test('stale open payment attempts are closed instead of returned for retry', function () {
    $payment = placePendingOrderPayment();
    $attempt = PaymentAttempt::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $payment->order_id,
        'gateway_id' => 'manual',
        'status' => PaymentAttemptStatus::Processing,
        'external_id' => 'stale-provider-attempt',
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'initiated_at' => now()->subHours(2),
    ]);

    $result = app(StartOrderPayment::class)->handle(
        $payment->order->fresh(['invoice', 'payment']),
        'manual',
        'https://store.test/return',
        'https://store.test/cancel',
    );

    expect($result->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($payment->fresh()->reconciliation_status)->toBe('manual_review');
});

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

test('gateway initiation refuses an already settled payment before provider call', function () {
    $order = Order::factory()->create();
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'method' => 'race-test',
        'status' => PaymentStatus::Paid,
        'amount' => 1500,
        'currency' => 'EUR',
        'paid_at' => now(),
    ]);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('race-test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities(webhooks: false));
    $gateway->shouldNotReceive('initiate');
    app(PaymentGatewayRegistry::class)->register($gateway);

    expect(fn () => app(InitiateGatewayPayment::class)->handle(
        $payment,
        'race-test',
        'https://store.test/return',
        'https://store.test/cancel',
    ))->toThrow(ValidationException::class);
});

test('gateway initiation revalidates payment state before provider call', function () {
    $order = Order::factory()->create();
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'method' => 'race-test',
        'status' => PaymentStatus::Pending,
        'amount' => 1500,
        'currency' => 'EUR',
    ]);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('race-test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities(webhooks: false));
    $gateway->shouldNotReceive('initiate');
    app(PaymentGatewayRegistry::class)->register($gateway);

    PaymentAttempt::created(static function (PaymentAttempt $attempt) use ($payment): void {
        if ((int) $attempt->payment_id === (int) $payment->id) {
            Payment::query()->whereKey($payment->id)->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
            ]);
        }
    });

    expect(fn () => app(InitiateGatewayPayment::class)->handle(
        $payment,
        'race-test',
        'https://store.test/return',
        'https://store.test/cancel',
    ))->toThrow(ValidationException::class);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and(PaymentAttempt::query()->where('payment_id', $payment->id)->value('status'))
        ->toBe(PaymentAttemptStatus::Failed);
});

test('refund retry reuses a committed pending idempotency context after local finalization fails', function () {
    $order = Order::factory()->create();
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'method' => 'refund-test',
        'status' => PaymentStatus::Paid,
        'amount' => 1500,
        'currency' => 'EUR',
        'paid_at' => now(),
    ]);
    $staff = $this->createStaff();
    $baselineTransactionLevel = DB::transactionLevel();
    $providerLevels = [];
    $idempotencyKeys = [];
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('refund-test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities(
        refunds: true,
        partialRefunds: true,
    ));
    $gateway->shouldReceive('refund')->twice()->withArgs(function (RefundRequest $request) use (&$providerLevels, &$idempotencyKeys): bool {
        $providerLevels[] = DB::transactionLevel();
        $idempotencyKeys[] = $request->idempotencyKey;

        return true;
    })->andReturn(RefundResult::ok('provider-refund-1'));
    app(PaymentGatewayRegistry::class)->register($gateway);

    $failFinalization = true;
    Refund::saving(static function (Refund $refund) use (&$failFinalization): void {
        if ($failFinalization && $refund->status === RefundStatus::Completed) {
            $failFinalization = false;
            throw new RuntimeException('local finalization failed');
        }
    });

    expect(fn () => app(RecordRefund::class)->handle($payment, $staff, 1500, 'Retryable refund'))
        ->toThrow(RuntimeException::class, 'local finalization failed');

    $pending = Refund::query()->where('payment_id', $payment->id)->firstOrFail();
    expect($pending->status)->toBe(RefundStatus::Pending);

    $completed = app(RecordRefund::class)->handle($payment->fresh(), $staff, 1500, 'Retryable refund');

    expect($completed->status)->toBe(RefundStatus::Completed)
        ->and($providerLevels)->toBe([$baselineTransactionLevel, $baselineTransactionLevel])
        ->and($idempotencyKeys)->toHaveCount(2)
        ->and($idempotencyKeys[0])->toBe($idempotencyKeys[1])
        ->and(Refund::query()->where('payment_id', $payment->id)->count())->toBe(1);
});

test('active refund claim is returned without issuing a second provider call', function () {
    $order = Order::factory()->create();
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'method' => 'refund-claim-test',
        'status' => PaymentStatus::Paid,
        'amount' => 1500,
        'currency' => 'EUR',
        'paid_at' => now(),
    ]);
    $staff = $this->createStaff();
    $refund = Refund::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $order->id,
        'created_by' => $staff->id,
        'amount' => 1500,
        'currency' => 'EUR',
        'status' => RefundStatus::Processing,
        'reason' => 'Active claim',
        'provider_claimed_at' => now(),
    ]);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('refund-claim-test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities(
        refunds: true,
        partialRefunds: true,
    ));
    $gateway->shouldReceive('refund')->never()->andReturn(RefundResult::unknown());
    app(PaymentGatewayRegistry::class)->register($gateway);

    $result = app(RecordRefund::class)->handle($payment, $staff, 1500, 'Active claim');

    expect($result->id)->toBe($refund->id)
        ->and($result->status)->toBe(RefundStatus::Processing);
});

test('successful refund without provider reference is held for manual review', function () {
    $order = Order::factory()->create();
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'method' => 'refund-reference-test',
        'status' => PaymentStatus::Paid,
        'amount' => 1500,
        'currency' => 'EUR',
        'paid_at' => now(),
    ]);
    $staff = $this->createStaff();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('refund-reference-test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities(
        refunds: true,
        partialRefunds: true,
    ));
    $gateway->shouldReceive('refund')->once()->andReturn(RefundResult::ok(null));
    app(PaymentGatewayRegistry::class)->register($gateway);

    $result = app(RecordRefund::class)->handle($payment, $staff, 1500, 'Missing reference');

    expect($result->status)->toBe(RefundStatus::Pending)
        ->and($payment->fresh()->reconciliation_status)->toBe('manual_review');
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

test('payment initiation respects the shared order lifecycle lock', function () {
    $payment = placePendingOrderPayment();
    $lock = Cache::lock('agovena:payment-lifecycle:order:'.$payment->order_id, 120);
    expect($lock->get())->toBeTrue();

    try {
        expect(fn () => app(StartOrderPayment::class)->handle(
            $payment->order,
            'manual',
            returnUrl: 'https://example.test/return',
            cancelUrl: 'https://example.test/cancel',
            idempotencyKey: 'locked-attempt-1',
        ))->toThrow(ValidationException::class);
    } finally {
        $lock->release();
    }
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

test('completed gateway initiation records manual reconciliation when local settlement is blocked', function () {
    $payment = placePendingOrderPayment();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('blocked-completed-gateway');
    $gateway->shouldReceive('initiate')->once()->andReturnUsing(function () use ($payment): PaymentInitiationResult {
        $payment->order->invoice()->update(['status' => InvoiceStatus::Void]);

        return PaymentInitiationResult::completed('completed-blocked-1');
    });
    app(PaymentGatewayRegistry::class)->register($gateway);

    $attempt = app(InitiateGatewayPayment::class)->handle(
        $payment,
        'blocked-completed-gateway',
        returnUrl: 'https://example.test/return',
        cancelUrl: 'https://example.test/cancel',
        idempotencyKey: 'completed-blocked-attempt-1',
    );

    expect($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Succeeded)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->reconciliation_status)->toBe('manual_review')
        ->and($payment->fresh()->reconciliation_meta['reason'] ?? null)->toBe('completed_provider_payment_local_settlement_blocked');
});

test('completed gateway initiation preserves recovery state when local settlement throws', function () {
    $payment = placePendingOrderPayment();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('throwing-settlement-gateway');
    $gateway->shouldReceive('initiate')->once()->andReturn(PaymentInitiationResult::completed('completed-throwing-1'));
    app(PaymentGatewayRegistry::class)->register($gateway);
    Event::listen(OrderPaid::class, static function (): void {
        throw new RuntimeException('local settlement failed');
    });

    $attempt = app(InitiateGatewayPayment::class)->handle(
        $payment,
        'throwing-settlement-gateway',
        returnUrl: 'https://example.test/return',
        cancelUrl: 'https://example.test/cancel',
        idempotencyKey: 'completed-throwing-attempt-1',
    );

    expect($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Succeeded)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->fresh()->reconciliation_status)->toBe('manual_review')
        ->and($payment->fresh()->reconciliation_meta['reason'] ?? null)->toBe('completed_provider_payment_local_settlement_failed');
});

test('cancellation with an open attempt without provider id requires reconciliation', function () {
    $payment = placePendingOrderPayment();
    $payment->update(['method' => 'unknown-cancel']);
    PaymentAttempt::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $payment->order_id,
        'gateway_id' => 'unknown-cancel',
        'status' => PaymentAttemptStatus::Processing,
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'initiated_at' => now(),
    ]);
    $gateway = Mockery::mock(PaymentGateway::class, CancelsPayments::class);
    $gateway->shouldReceive('id')->andReturn('unknown-cancel');
    $gateway->shouldReceive('cancel')->never()->andReturn($payment);
    app(PaymentGatewayRegistry::class)->register($gateway);

    expect(fn () => app(CancelUnpaidOrder::class)->handle(
        $payment->order->fresh(['invoice', 'payment']),
        UnpaidOrderCancelSource::Customer,
    ))->toThrow(ValidationException::class);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->reconciliation_status)->toBe('manual_review')
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Pending);
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

test('provider cancellation runs without holding database row locks', function () {
    $payment = placePendingOrderPayment();
    $baselineTransactionLevel = DB::transactionLevel();
    $transactionLevel = null;
    $gateway = Mockery::mock(PaymentGateway::class, CancelsPayments::class);
    $gateway->shouldReceive('id')->andReturn('transaction-free-cancel');
    $gateway->shouldReceive('cancel')->once()->andReturnUsing(function () use ($payment, &$transactionLevel): Payment {
        $transactionLevel = DB::transactionLevel();

        return $payment;
    });
    app(PaymentGatewayRegistry::class)->register($gateway);
    $payment->update(['method' => 'transaction-free-cancel']);

    $result = app(CancelUnpaidOrder::class)->handle(
        $payment->order->fresh(['invoice', 'payment']),
        UnpaidOrderCancelSource::Customer,
    );

    expect($transactionLevel)->toBe($baselineTransactionLevel)
        ->and($result->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Cancelled);
});

test('provider cancellation without an explicit capability fails closed', function () {
    $payment = placePendingOrderPayment();
    $payment->update(['method' => 'fake-webhook']);
    app(PaymentGatewayRegistry::class)->register(new FakeWebhookGateway);

    expect(fn () => app(CancelUnpaidOrder::class)->handle(
        $payment->order->fresh(['invoice', 'payment']),
        UnpaidOrderCancelSource::Customer,
    ))->toThrow(ValidationException::class);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->reconciliation_status)->toBe('manual_review')
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Pending);
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

test('unmatched webhook events are deferred until their payment attempt exists', function () {
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('unvalidated-webhook');
    $gateway->shouldReceive('label')->andReturn('Unvalidated webhook');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities(webhooks: true));
    $gateway->shouldReceive('verifyWebhook')->andReturnTrue();
    $gateway->shouldReceive('parseWebhook')->andReturn(new WebhookPayload(
        externalEventId: 'evt-before-attempt',
        externalPaymentId: 'ext-before-attempt',
        status: PaymentStatus::Failed,
        raw: ['event_id' => 'evt-before-attempt', 'payment_id' => 'ext-before-attempt', 'status' => 'failed'],
    ));
    app(PaymentGatewayRegistry::class)->register($gateway);

    $first = app(HandlePaymentWebhook::class)->handle('unvalidated-webhook', Request::create('/webhook', 'POST'));

    expect($first->event->processing_status)->toBe('deferred');

    $payment = placePendingOrderPayment();
    $payment->update(['method' => 'unvalidated-webhook']);
    $attempt = PaymentAttempt::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $payment->order_id,
        'gateway_id' => 'unvalidated-webhook',
        'status' => PaymentAttemptStatus::Processing,
        'external_id' => 'ext-before-attempt',
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'initiated_at' => now(),
    ]);

    $replayed = app(HandlePaymentWebhook::class)->reconcileDeferred($first->event);

    expect($replayed->event->processing_status)->toBe('processed')
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Failed);
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

test('webhook arriving before its payment attempt remains retryable', function () {
    $gateway = new FakeWebhookGateway;
    app(PaymentGatewayRegistry::class)->register($gateway);

    $payment = placePendingOrderPayment();
    $request = Request::create('/webhooks/payments/fake-webhook', 'POST', [
        'event_id' => 'evt-racing-attempt',
        'payment_id' => 'ext-racing-attempt',
        'status' => 'paid',
    ], server: ['HTTP_X_WEBHOOK_SECRET' => 'test-secret']);

    $first = app(HandlePaymentWebhook::class)->handle('fake-webhook', $request);
    $firstStatus = $first->event->processing_status;

    PaymentAttempt::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $payment->order_id,
        'gateway_id' => 'fake-webhook',
        'status' => PaymentAttemptStatus::Processing,
        'external_id' => 'ext-racing-attempt',
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'initiated_at' => now(),
    ]);

    $second = app(HandlePaymentWebhook::class)->handle('fake-webhook', $request);

    expect($firstStatus)->toBe('deferred')
        ->and($second->event->processing_status)->toBe('processed')
        ->and($second->duplicate)->toBeFalse()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Paid);
});

test('deferred payment webhooks can be reconciled after their attempt exists', function () {
    app(PaymentGatewayRegistry::class)->register(new FakeWebhookGateway);

    $payment = placePendingOrderPayment();
    $event = PaymentWebhookEvent::query()->create([
        'gateway_id' => 'fake-webhook',
        'external_event_id' => 'evt-deferred-recovery',
        'external_payment_id' => 'ext-deferred-recovery',
        'status' => 'paid',
        'processing_status' => 'deferred',
        'payload' => [
            'event_id' => 'evt-deferred-recovery',
            'payment_id' => 'ext-deferred-recovery',
            'status' => 'paid',
        ],
    ]);
    PaymentAttempt::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $payment->order_id,
        'gateway_id' => 'fake-webhook',
        'status' => PaymentAttemptStatus::Processing,
        'external_id' => 'ext-deferred-recovery',
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'initiated_at' => now(),
    ]);

    expect(Artisan::call('agovena:reconcile-payment-webhooks'))->toBe(0)
        ->and($event->fresh()->processing_status)->toBe('processed')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Paid);
});

test('webhooks without an external payment id remain deferred', function () {
    app(PaymentGatewayRegistry::class)->register(new FakeWebhookGateway);

    $result = app(HandlePaymentWebhook::class)->handle(
        'fake-webhook',
        Request::create('/webhooks/payments/fake-webhook', 'POST', [
            'event_id' => 'evt-missing-payment-id',
            'status' => 'paid',
        ], server: ['HTTP_X_WEBHOOK_SECRET' => 'test-secret']),
    );

    expect($result->event->processing_status)->toBe('deferred')
        ->and($result->event->processed_at)->toBeNull()
        ->and(app(HandlePaymentWebhook::class)->reconcileDeferred($result->event)->event->processing_status)
        ->toBe('deferred');
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

test('normalized provider failure closes the aggregate payment', function () {
    $payment = placePendingOrderPayment();
    $attempt = PaymentAttempt::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $payment->order_id,
        'gateway_id' => 'manual',
        'status' => PaymentAttemptStatus::Processing,
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'idempotency_key' => 'failure-status-key',
        'initiated_at' => now(),
    ]);

    app(ApplyNormalizedPaymentStatus::class)->handle($attempt, PaymentStatus::Failed);

    expect($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Failed);
});

test('unknown gateway outcomes block new provider attempts pending reconciliation', function () {
    $payment = placePendingOrderPayment();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('unknown-outcome-gateway');
    $gateway->shouldReceive('initiate')->once()->andThrow(new RuntimeException('provider timeout after acceptance'));
    app(PaymentGatewayRegistry::class)->register($gateway);

    $first = app(StartOrderPayment::class)->handle(
        $payment->order,
        'unknown-outcome-gateway',
        'https://example.test/return',
        'https://example.test/cancel',
        'unknown-outcome-1',
    );
    $same = app(StartOrderPayment::class)->handle(
        $payment->order,
        'unknown-outcome-gateway',
        'https://example.test/return',
        'https://example.test/cancel',
        'unknown-outcome-1',
    );
    expect($first->id)->toBe($same->id)
        ->and($first->fresh()->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($first->fresh()->response_meta['provider_outcome'] ?? null)->toBe('unknown')
        ->and($payment->fresh()->reconciliation_status)->toBe('manual_review');

    expect(fn () => app(StartOrderPayment::class)->handle(
        $payment->order,
        'unknown-outcome-gateway',
        'https://example.test/return',
        'https://example.test/cancel',
        'unknown-outcome-2',
    ))->toThrow(ValidationException::class);
});

test('stale pending gateway initiation is closed for manual reconciliation', function () {
    $payment = placePendingOrderPayment();
    $attempt = PaymentAttempt::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $payment->order_id,
        'gateway_id' => 'manual',
        'status' => PaymentAttemptStatus::Pending,
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'idempotency_key' => 'stale-initiation-key',
        'initiated_at' => now()->subSeconds(901),
    ]);

    $result = app(InitiateGatewayPayment::class)->handle(
        $payment,
        'manual',
        'https://example.test/return',
        'https://example.test/cancel',
        'stale-initiation-key',
    );

    expect($result->id)->toBe($attempt->id)
        ->and($result->fresh()->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($payment->fresh()->reconciliation_status)->toBe('manual_review');
});

test('direct completion cannot resurrect cancelled or expired payments', function () {
    foreach ([PaymentStatus::Cancelled, PaymentStatus::Expired] as $status) {
        $payment = placePendingOrderPayment();
        $payment->update(['status' => $status]);

        expect(fn () => app(CompleteDirectPayment::class)->handle(
            $payment->order,
            'manual',
        ))->toThrow(ValidationException::class);
        expect($payment->fresh()->status)->toBe($status);
    }
});

test('refunded payment cannot be downgraded by a partial refund status', function () {
    $payment = placePendingOrderPayment();
    $payment->update(['status' => PaymentStatus::Refunded]);
    $attempt = PaymentAttempt::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $payment->order_id,
        'gateway_id' => 'manual',
        'status' => PaymentAttemptStatus::Succeeded,
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'idempotency_key' => 'refund-monotonicity-key',
        'initiated_at' => now(),
    ]);

    $result = app(ApplyNormalizedPaymentStatus::class)->handle($attempt, PaymentStatus::PartiallyRefunded);

    expect($result->applied)->toBeFalse()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
});

test('order cancellation cancels open attempts for every gateway', function () {
    $payment = placePendingOrderPayment();
    $first = Mockery::mock(PaymentGateway::class, CancelsPayments::class);
    $first->shouldReceive('id')->andReturn('cancel-gateway-a');
    $first->shouldReceive('cancel')->twice()->withArgs(static fn (Payment $candidate, PaymentAttempt $attempt): bool => $candidate->is($payment) && $attempt->gateway_id === 'cancel-gateway-a')->andReturn($payment);
    $second = Mockery::mock(PaymentGateway::class, CancelsPayments::class);
    $second->shouldReceive('id')->andReturn('cancel-gateway-b');
    $second->shouldReceive('cancel')->once()->andReturn($payment);
    app(PaymentGatewayRegistry::class)->register($first);
    app(PaymentGatewayRegistry::class)->register($second);
    $payment->update(['method' => 'cancel-gateway-b']);

    foreach ([
        ['cancel-gateway-a', 'attempt-a1'],
        ['cancel-gateway-a', 'attempt-a2'],
        ['cancel-gateway-b', 'attempt-b'],
    ] as [$gatewayId, $key]) {
        PaymentAttempt::query()->create([
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'gateway_id' => $gatewayId,
            'status' => PaymentAttemptStatus::Processing,
            'external_id' => 'external-'.$key,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'idempotency_key' => $key,
            'initiated_at' => now(),
        ]);
    }

    app(CancelUnpaidOrder::class)->handle(
        $payment->order->fresh(['invoice', 'payment']),
        UnpaidOrderCancelSource::Customer,
    );

    expect(PaymentAttempt::query()->where('payment_id', $payment->id)->where('status', PaymentAttemptStatus::Cancelled)->count())->toBe(3);
});

test('deferred webhook reconciliation prioritizes events with external payment ids', function (): void {
    app(PaymentGatewayRegistry::class)->register(new FakeWebhookGateway);
    $payment = placePendingOrderPayment();
    $payment->update(['method' => 'fake-webhook']);
    PaymentAttempt::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $payment->order_id,
        'gateway_id' => 'fake-webhook',
        'status' => PaymentAttemptStatus::Processing,
        'external_id' => 'external-valid-3',
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'idempotency_key' => 'fairness-attempt-3',
        'initiated_at' => now(),
    ]);
    foreach ([1, 2] as $id) {
        PaymentWebhookEvent::query()->create([
            'gateway_id' => 'fake-webhook',
            'external_event_id' => 'missing-'.$id,
            'external_payment_id' => null,
            'status' => PaymentStatus::Paid->value,
            'processing_status' => 'deferred',
            'payload' => [],
        ]);
    }
    $valid = PaymentWebhookEvent::query()->create([
        'gateway_id' => 'fake-webhook',
        'external_event_id' => 'valid-3',
        'external_payment_id' => 'external-valid-3',
        'status' => PaymentStatus::Paid->value,
        'processing_status' => 'deferred',
        'payload' => [],
    ]);
    expect(Artisan::call('agovena:reconcile-payment-webhooks', ['--limit' => 2]))->toBe(0)
        ->and($valid->fresh()->processing_status)->toBe('processed')
        ->and(PaymentWebhookEvent::query()->whereNull('external_payment_id')->where('processing_status', 'deferred')->count())->toBe(2);
});

test('paid webhook after terminal cancellation requires payment reconciliation', function (): void {
    app(PaymentGatewayRegistry::class)->register(new FakeWebhookGateway);
    $payment = placePendingOrderPayment();
    $payment->order()->update(['status' => OrderStatus::Cancelled]);
    $payment->order->invoice()->update(['status' => InvoiceStatus::Void]);
    $attempt = PaymentAttempt::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $payment->order_id,
        'gateway_id' => 'fake-webhook',
        'status' => PaymentAttemptStatus::Processing,
        'external_id' => 'external-terminal-paid',
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'idempotency_key' => 'terminal-paid-attempt',
        'initiated_at' => now(),
    ]);

    $event = app(HandlePaymentWebhook::class)->handle('fake-webhook', Request::create(
        '/webhooks/payments/fake-webhook',
        'POST',
        [
            'event_id' => 'terminal-paid-event',
            'payment_id' => $attempt->external_id,
            'status' => 'paid',
        ],
        server: ['HTTP_X_WEBHOOK_SECRET' => 'test-secret'],
    ));

    expect($event->event->processing_status)->toBe('ignored')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->reconciliation_status)->toBe('manual_review')
        ->and($payment->fresh()->reconciliation_meta['reason'] ?? null)->toBe('paid_webhook_after_terminal_order');
});
