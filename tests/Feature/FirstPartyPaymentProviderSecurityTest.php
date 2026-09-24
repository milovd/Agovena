<?php

declare(strict_types=1);

use Agovena\Extensions\Paddle\PaddleApi;
use Agovena\Extensions\Tebex\TebexApi;
use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Payments\HandlePaymentWebhook;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Payments\RefundRequest;
use App\Agovena\Payments\StartOrderPayment;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Product;
use App\Models\Refund;
use Illuminate\Http\Request;
use Tests\Support\FakePaddleApi;
use Tests\Support\FakeTebexApi;

function enableSecurityPaddle(?FakePaddleApi $api = null): FakePaddleApi
{
    app(ExtensionManager::class)->discover();
    $api ??= new FakePaddleApi;
    app()->instance(PaddleApi::class, $api);
    installAndEnableExtension('paddle');
    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('paddle', 'api_key', '[REDACTED]', secret: true);
    $settings->set('paddle', 'webhook_secret', '[REDACTED]', secret: true);
    $settings->set('paddle', 'sandbox', true);

    return $api;
}

function enableSecurityTebex(?FakeTebexApi $api = null): FakeTebexApi
{
    app(ExtensionManager::class)->discover();
    $api ??= new FakeTebexApi;
    app()->instance(TebexApi::class, $api);
    installAndEnableExtension('tebex');
    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('tebex', 'project_id', 'project-test');
    $settings->set('tebex', 'secret_key', '[REDACTED]', secret: true);
    $settings->set('tebex', 'webhook_secret', '[REDACTED]', secret: true);

    return $api;
}

function placeSecurityOrder(string $paymentMethod, int $productId): Payment
{
    $product = Product::factory()->active()->create(['id' => $productId, 'price_amount' => 2500]);
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'Security Buyer',
        'customer_email' => 'security@example.test',
        'payment_method' => $paymentMethod,
        'billing' => AddressData::fromArray([
            'name' => 'Security Buyer',
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
    ]);

    return $order->payment()->firstOrFail();
}

it('rejects a Paddle webhook when the signature is invalid', function (): void {
    enableSecurityPaddle();
    $request = Request::create(
        '/webhooks/payments/paddle',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_PADDLE-SIGNATURE' => 'ts='.time().';h1=invalid'],
        '{"event_id":"evt_test","event_type":"transaction.paid","data":{"id":"txn_test"}}',
    );

    expect(app(PaymentGatewayRegistry::class)->get('paddle')->verifyWebhook($request))->toBeFalse();
});

it('rejects a Tebex webhook when the signature is invalid', function (): void {
    enableSecurityTebex();
    $request = Request::create(
        '/webhooks/payments/tebex',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X-SIGNATURE' => 'invalid'],
        '{"id":"evt_test","type":"payment.completed","subject":{"transaction_id":"tbx-test"}}',
    );

    expect(app(PaymentGatewayRegistry::class)->get('tebex')->verifyWebhook($request))->toBeFalse();
});

test('tebex answers the signed validation webhook without persisting a payment event', function (): void {
    enableSecurityTebex();
    $body = json_encode(['id' => 'validation-test', 'type' => 'validation.webhook'], JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', hash('sha256', $body), '[REDACTED]');
    $request = Request::create(
        '/webhooks/payments/tebex',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X-SIGNATURE' => $signature],
        $body,
    );

    $response = app(PaymentGatewayRegistry::class)->get('tebex')->webhookValidationResponse($request);

    expect($response)->toBe(['id' => 'validation-test'])
        ->and(PaymentWebhookEvent::query()->count())->toBe(0);
});

test('tebex creates a custom checkout without package identifiers', function (): void {
    $api = enableSecurityTebex();
    $payment = placeSecurityOrder('tebex:tebex', 3);

    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'tebex:tebex',
        'https://example.test/return',
        'https://example.test/cancel',
        'tebex-custom-checkout',
    );

    expect($attempt->status)->toBe(PaymentAttemptStatus::Processing)
        ->and($api->checkoutCalls)->toBe(1)
        ->and($api->checkoutPayloads[0]['items'][0]['package']['custom']['agovena_product_id'] ?? null)->toBe('3')
        ->and(array_key_exists('id', $api->checkoutPayloads[0]['items'][0]['package']))->toBeFalse();
});

test('paddle paid webhook with mismatched amount is ignored', function (): void {
    enableSecurityPaddle();
    $payment = placeSecurityOrder('paddle:card', 1);
    $attempt = app(StartOrderPayment::class)->handle($payment->order, 'paddle:card', 'https://example.test/return', 'https://example.test/cancel', 'paddle-mismatch');
    $timestamp = time();
    $body = json_encode([
        'event_id' => 'evt_paddle_mismatch',
        'event_type' => 'transaction.paid',
        'data' => [
            'id' => $attempt->external_id,
            'status' => 'paid',
            'currency_code' => 'EUR',
            'details' => ['totals' => ['grand_total' => '1'], 'line_items' => [['price_id' => 'pri_test']]],
            'custom_data' => ['order_id' => (string) $payment->order_id, 'payment_id' => (string) $payment->id],
        ],
    ], JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $timestamp.':'.$body, '[REDACTED]');

    app(HandlePaymentWebhook::class)->handle('paddle', Request::create(
        '/webhooks/payments/paddle',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_PADDLE-SIGNATURE' => 'ts='.$timestamp.';h1='.$signature],
        $body,
    ));

    expect($payment->fresh()->status)->not->toBe(PaymentStatus::Paid)
        ->and($attempt->fresh()->status)->not->toBe(PaymentAttemptStatus::Succeeded);
});

test('tebex completed webhook with mismatched amount is ignored', function (): void {
    enableSecurityTebex();
    $payment = placeSecurityOrder('tebex:tebex', 2);
    $attempt = app(StartOrderPayment::class)->handle($payment->order, 'tebex:tebex', 'https://example.test/return', 'https://example.test/cancel', 'tebex-mismatch');
    $body = json_encode([
        'id' => 'evt_tebex_mismatch',
        'type' => 'payment.completed',
        'subject' => [
            'transaction_id' => $attempt->external_id,
            'price_paid' => ['amount' => 0.01, 'currency' => 'EUR'],
            'products' => [['id' => 12345, 'quantity' => 1, 'custom' => ['agovena_product_id' => (string) $payment->order->items->first()->product_id, 'agovena_order_item_id' => (string) $payment->order->items->first()->id]]],
            'custom' => ['order_id' => (string) $payment->order_id, 'payment_id' => (string) $payment->id],
        ],
    ], JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', hash('sha256', $body), '[REDACTED]');

    app(HandlePaymentWebhook::class)->handle('tebex', Request::create(
        '/webhooks/payments/tebex',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X-SIGNATURE' => $signature],
        $body,
    ));

    expect($payment->fresh()->status)->not->toBe(PaymentStatus::Paid)
        ->and($attempt->fresh()->status)->not->toBe(PaymentAttemptStatus::Succeeded);
});

test('duplicate Tebex webhooks are idempotent and retain their replay ledger entry', function (): void {
    enableSecurityTebex();
    $payment = placeSecurityOrder('tebex:tebex', 2);
    $attempt = app(StartOrderPayment::class)->handle($payment->order, 'tebex:tebex', 'https://example.test/return', 'https://example.test/cancel', 'tebex-duplicate');
    $body = json_encode([
        'id' => 'evt_tebex_duplicate',
        'type' => 'payment.completed',
        'subject' => [
            'transaction_id' => $attempt->external_id,
            'price_paid' => ['amount' => 25.0, 'currency' => 'EUR'],
            'products' => [['id' => 12345, 'quantity' => 1, 'custom' => ['agovena_product_id' => (string) $payment->order->items->first()->product_id, 'agovena_order_item_id' => (string) $payment->order->items->first()->id]]],
            'custom' => ['order_id' => (string) $payment->order_id, 'payment_id' => (string) $payment->id],
        ],
    ], JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', hash('sha256', $body), '[REDACTED]');
    $request = fn (): Request => Request::create(
        '/webhooks/payments/tebex',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X-SIGNATURE' => $signature],
        $body,
    );

    $first = app(HandlePaymentWebhook::class)->handle('tebex', $request());
    $second = app(HandlePaymentWebhook::class)->handle('tebex', $request());

    expect($first->duplicate)->toBeFalse()
        ->and($second->duplicate)->toBeTrue()
        ->and(PaymentWebhookEvent::query()->where('gateway_id', 'tebex')->where('external_event_id', 'evt_tebex_duplicate')->count())->toBe(1)
        ->and(PaymentWebhookEvent::query()->where('external_event_id', 'evt_tebex_duplicate')->value('retention_exempt'))->toBeTrue();
});

test('tebex refund webhook amount mismatches remain deferred', function (): void {
    enableSecurityTebex();
    $payment = placeSecurityOrder('tebex:tebex', 2);
    $attempt = app(StartOrderPayment::class)->handle($payment->order, 'tebex:tebex', 'https://example.test/return', 'https://example.test/cancel', 'tebex-refund-webhook');
    $attempt->update(['external_id' => 'tbx-refund-webhook']);
    $payment->update(['status' => PaymentStatus::Paid]);
    $refund = Refund::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $payment->order_id,
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'status' => RefundStatus::Processing,
        'provider_reference' => null,
        'provider_claimed_at' => now(),
        'reason' => 'Mismatch test',
    ]);
    $body = json_encode([
        'id' => 'evt_tebex_refund_mismatch',
        'type' => 'payment.refunded',
        'subject' => [
            'transaction_id' => 'tbx-refund-webhook',
            'status' => ['id' => 2, 'description' => 'Refund'],
            'price_paid' => ['amount' => 0.01, 'currency' => 'EUR'],
        ],
    ], JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', hash('sha256', $body), '[REDACTED]');

    app(HandlePaymentWebhook::class)->handle('tebex', Request::create(
        '/webhooks/payments/tebex',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X-SIGNATURE' => $signature],
        $body,
    ));

    expect($refund->fresh()->status)->toBe(RefundStatus::Processing)
        ->and(PaymentWebhookEvent::query()->where('external_event_id', 'evt_tebex_refund_mismatch')->value('processing_status'))->toBe('deferred');
});

test('paddle supports partial adjustments while Tebex keeps its full-refund boundary', function (): void {
    $paddleApi = enableSecurityPaddle();
    $paddleApi->transaction['details'] = [
        'line_items' => [['id' => 'txnitm_test']],
    ];
    $paddlePayment = placeSecurityOrder('paddle:card', 1);
    $paddleAttempt = app(StartOrderPayment::class)->handle($paddlePayment->order, 'paddle:card', 'https://example.test/return', 'https://example.test/cancel', 'paddle-refund-start');
    $tebexApi = enableSecurityTebex();
    $tebexPayment = placeSecurityOrder('tebex:tebex', 2);
    $tebexAttempt = app(StartOrderPayment::class)->handle($tebexPayment->order, 'tebex:tebex', 'https://example.test/return', 'https://example.test/cancel', 'tebex-refund-start');

    $paddleResult = app(PaymentGatewayRegistry::class)->get('paddle')->refund(new RefundRequest($paddlePayment, 1, 'EUR'));
    $tebexResult = app(PaymentGatewayRegistry::class)->get('tebex')->refund(new RefundRequest($tebexPayment, 1, 'EUR'));

    expect($paddleResult->success)->toBeTrue()
        ->and($tebexResult->success)->toBeFalse()
        ->and($paddleApi->transactionCalls)->toBe(1)
        ->and($paddleApi->lastAdjustmentRequest['type'] ?? null)->toBe('partial')
        ->and($paddleApi->lastAdjustmentRequest['items'][0]['item_id'] ?? null)->toBe('txnitm_test')
        ->and($paddleApi->lastAdjustmentRequest['items'][0]['amount'] ?? null)->toBe('1')
        ->and($tebexApi->checkoutCalls)->toBe(1)
        ->and($paddleAttempt->external_id)->toBe('txn_test')
        ->and($tebexAttempt->external_id)->toBe('basket-ident');
});

test('paddle adjustment webhooks use the transaction id for payment lookup', function (): void {
    enableSecurityPaddle();
    $payment = placeSecurityOrder('paddle:card', 1);
    $attempt = app(StartOrderPayment::class)->handle($payment->order, 'paddle:card', 'https://example.test/return', 'https://example.test/cancel', 'paddle-adjustment-lookup');
    $timestamp = time();
    $body = json_encode([
        'event_id' => 'evt_paddle_adjustment',
        'event_type' => 'adjustment.updated',
        'data' => [
            'id' => 'adj_test',
            'transaction_id' => $attempt->external_id,
            'action' => 'refund',
            'status' => 'approved',
        ],
    ], JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $timestamp.':'.$body, '[REDACTED]');
    $request = Request::create(
        '/webhooks/payments/paddle',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_PADDLE-SIGNATURE' => 'ts='.$timestamp.';h1='.$signature],
        $body,
    );
    $gateway = app(PaymentGatewayRegistry::class)->get('paddle');

    expect($gateway->verifyWebhook($request))->toBeTrue()
        ->and($gateway->parseWebhook($request)->externalPaymentId)->toBe($attempt->external_id);

    $payment->update(['status' => PaymentStatus::Paid]);
    $attempt->update(['status' => PaymentAttemptStatus::Succeeded]);
    Refund::query()->create([
        'payment_id' => $payment->id,
        'order_id' => $payment->order_id,
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'status' => RefundStatus::Processing,
        'provider_reference' => 'adj_test',
        'provider_claimed_at' => now(),
        'reason' => 'test',
    ]);
    app(HandlePaymentWebhook::class)->handle('paddle', $request);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and(Refund::query()->where('provider_reference', 'adj_test')->firstOrFail()->status)->toBe(RefundStatus::Completed);
});

test('tebex requires custom order and payment metadata before marking paid', function (): void {
    enableSecurityTebex();
    $payment = placeSecurityOrder('tebex:tebex', 2);
    $attempt = app(StartOrderPayment::class)->handle($payment->order, 'tebex:tebex', 'https://example.test/return', 'https://example.test/cancel', 'tebex-missing-custom');
    $body = json_encode([
        'id' => 'evt_tebex_missing_custom',
        'type' => 'payment.completed',
        'subject' => [
            'transaction_id' => $attempt->external_id,
            'price_paid' => ['amount' => 25.0, 'currency' => 'EUR'],
            'products' => [['id' => 12345, 'quantity' => 1, 'custom' => ['agovena_product_id' => (string) $payment->order->items->first()->product_id, 'agovena_order_item_id' => (string) $payment->order->items->first()->id]]],
        ],
    ], JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', hash('sha256', $body), '[REDACTED]');

    app(HandlePaymentWebhook::class)->handle('tebex', Request::create(
        '/webhooks/payments/tebex',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X-SIGNATURE' => $signature],
        $body,
    ));

    expect($payment->fresh()->status)->not->toBe(PaymentStatus::Paid)
        ->and($attempt->fresh()->status)->not->toBe(PaymentAttemptStatus::Succeeded);
});

test('first party gateways require an exact full refund in the payment currency', function (): void {
    enableSecurityPaddle();
    $paddlePayment = placeSecurityOrder('paddle:card', 1);
    app(StartOrderPayment::class)->handle($paddlePayment->order, 'paddle:card', 'https://example.test/return', 'https://example.test/cancel', 'paddle-refund-boundary');
    enableSecurityTebex();
    $tebexPayment = placeSecurityOrder('tebex:tebex', 2);
    app(StartOrderPayment::class)->handle($tebexPayment->order, 'tebex:tebex', 'https://example.test/return', 'https://example.test/cancel', 'tebex-refund-boundary');

    $paddleGateway = app(PaymentGatewayRegistry::class)->get('paddle');
    $tebexGateway = app(PaymentGatewayRegistry::class)->get('tebex');
    $paddleOverRefund = $paddleGateway->refund(new RefundRequest($paddlePayment, 2501, 'EUR'));
    $tebexWrongCurrency = $tebexGateway->refund(new RefundRequest($tebexPayment, 2500, 'USD'));

    expect($paddleOverRefund->success)->toBeFalse()
        ->and($tebexWrongCurrency->success)->toBeFalse();
});

test('first party gateways reject refunds without a provider reference', function (): void {
    $paddleApi = enableSecurityPaddle();
    $paddlePayment = placeSecurityOrder('paddle:card', 1);
    app(StartOrderPayment::class)->handle($paddlePayment->order, 'paddle:card', 'https://example.test/return', 'https://example.test/cancel', 'paddle-empty-refund');
    $paddleApi->adjustment = ['transaction_id' => 'txn_test'];

    $tebexApi = enableSecurityTebex();
    $tebexPayment = placeSecurityOrder('tebex:tebex', 2);
    app(StartOrderPayment::class)->handle($tebexPayment->order, 'tebex:tebex', 'https://example.test/return', 'https://example.test/cancel', 'tebex-empty-refund');
    $tebexApi->refund = [];

    $paddleResult = app(PaymentGatewayRegistry::class)->get('paddle')->refund(new RefundRequest($paddlePayment, 2500, 'EUR'));
    $tebexResult = app(PaymentGatewayRegistry::class)->get('tebex')->refund(new RefundRequest($tebexPayment, 2500, 'EUR'));

    expect($paddleResult->success)->toBeFalse()
        ->and($tebexResult->success)->toBeFalse();
});

test('paddle does not persist a processing attempt without a transaction id', function (): void {
    $api = enableSecurityPaddle();
    $api->transaction['id'] = '';
    $payment = placeSecurityOrder('paddle:card', 1);

    $attempt = app(StartOrderPayment::class)->handle($payment->order, 'paddle:card', 'https://example.test/return', 'https://example.test/cancel', 'paddle-empty-transaction');

    expect($attempt->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($attempt->external_id)->toBeNull();
});
