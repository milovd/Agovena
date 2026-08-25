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
use App\Models\Payment;
use App\Models\Product;
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
    $settings->set('paddle', 'price_map', ['1' => 'pri_test']);
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
    $settings->set('tebex', 'package_map', ['2' => '12345']);

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

test('tebex validates every package mapping before creating a basket', function (): void {
    $api = enableSecurityTebex();
    $payment = placeSecurityOrder('tebex:tebex', 3);

    app(StartOrderPayment::class)->handle(
        $payment->order,
        'tebex:tebex',
        'https://example.test/return',
        'https://example.test/cancel',
        'tebex-missing-map',
    );

    expect($api->basketCalls)->toBe(0);
});

test('paddle paid webhook with mismatched amount is ignored', function (): void {
    enableSecurityPaddle();
    $payment = placeSecurityOrder('paddle:paddle', 1);
    $attempt = app(StartOrderPayment::class)->handle($payment->order, 'paddle:paddle', 'https://example.test/return', 'https://example.test/cancel', 'paddle-mismatch');
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
            'products' => [['id' => 12345, 'quantity' => 1]],
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

test('paddle and tebex reject partial refunds at the capability boundary', function (): void {
    $paddleApi = enableSecurityPaddle();
    $paddlePayment = placeSecurityOrder('paddle:paddle', 1);
    $paddleAttempt = app(StartOrderPayment::class)->handle($paddlePayment->order, 'paddle:paddle', 'https://example.test/return', 'https://example.test/cancel', 'paddle-refund-start');
    $tebexApi = enableSecurityTebex();
    $tebexPayment = placeSecurityOrder('tebex:tebex', 2);
    $tebexAttempt = app(StartOrderPayment::class)->handle($tebexPayment->order, 'tebex:tebex', 'https://example.test/return', 'https://example.test/cancel', 'tebex-refund-start');

    $paddleResult = app(PaymentGatewayRegistry::class)->get('paddle')->refund(new RefundRequest($paddlePayment, 1, 'EUR'));
    $tebexResult = app(PaymentGatewayRegistry::class)->get('tebex')->refund(new RefundRequest($tebexPayment, 1, 'EUR'));

    expect($paddleResult->success)->toBeFalse()
        ->and($tebexResult->success)->toBeFalse()
        ->and($paddleApi->transactionCalls)->toBe(1)
        ->and($tebexApi->basketCalls)->toBe(1)
        ->and($paddleAttempt->external_id)->toBe('txn_test')
        ->and($tebexAttempt->external_id)->toBe('basket-ident');
});

test('paddle adjustment webhooks use the transaction id for payment lookup', function (): void {
    enableSecurityPaddle();
    $payment = placeSecurityOrder('paddle:paddle', 1);
    $attempt = app(StartOrderPayment::class)->handle($payment->order, 'paddle:paddle', 'https://example.test/return', 'https://example.test/cancel', 'paddle-adjustment-lookup');
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
    app(HandlePaymentWebhook::class)->handle('paddle', $request);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
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
            'products' => [['id' => 12345, 'quantity' => 1]],
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
    $paddlePayment = placeSecurityOrder('paddle:paddle', 1);
    app(StartOrderPayment::class)->handle($paddlePayment->order, 'paddle:paddle', 'https://example.test/return', 'https://example.test/cancel', 'paddle-refund-boundary');
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
    $paddlePayment = placeSecurityOrder('paddle:paddle', 1);
    app(StartOrderPayment::class)->handle($paddlePayment->order, 'paddle:paddle', 'https://example.test/return', 'https://example.test/cancel', 'paddle-empty-refund');
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
    $payment = placeSecurityOrder('paddle:paddle', 1);

    $attempt = app(StartOrderPayment::class)->handle($payment->order, 'paddle:paddle', 'https://example.test/return', 'https://example.test/cancel', 'paddle-empty-transaction');

    expect($attempt->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($attempt->external_id)->toBeNull();
});
