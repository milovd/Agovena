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
use App\Agovena\Payments\StartOrderPayment;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\Request;
use Tests\Support\FakePaddleApi;
use Tests\Support\FakeTebexApi;

function enableFirstPartyPaddle(?FakePaddleApi $api = null): FakePaddleApi
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

function enableFirstPartyTebex(?FakeTebexApi $api = null): FakeTebexApi
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

function placeFirstPartyOrder(string $paymentMethod, int $productId): Payment
{
    $product = Product::factory()->active()->create(['id' => $productId, 'price_amount' => 2500]);
    app(CartService::class)->add($product->id, 1);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'First Party Buyer',
        'customer_email' => 'first-party@example.test',
        'payment_method' => $paymentMethod,
        'billing' => AddressData::fromArray([
            'name' => 'First Party Buyer',
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
    ]);

    return $order->payment()->firstOrFail();
}

test('paddle checkout redirects and signed paid webhook completes payment', function (): void {
    $api = enableFirstPartyPaddle();
    $payment = placeFirstPartyOrder('paddle:paddle', 1);
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'paddle:paddle',
        'https://example.test/return',
        'https://example.test/cancel',
        'paddle-start-1',
    );

    expect($attempt->redirect_url)->toBe('https://checkout.paddle.test/txn_test')
        ->and($attempt->status)->toBe(PaymentAttemptStatus::Processing)
        ->and($api->transactionCalls)->toBe(1);

    $body = json_encode([
        'event_id' => 'evt_paddle_test',
        'event_type' => 'transaction.paid',
        'data' => [
            'id' => 'txn_test',
            'status' => 'paid',
            'currency_code' => 'EUR',
            'details' => ['totals' => ['grand_total' => '2500'], 'line_items' => [['price_id' => 'pri_test', 'quantity' => 1]]],
            'custom_data' => ['order_id' => (string) $payment->order_id, 'payment_id' => (string) $payment->id],
        ],
    ], JSON_THROW_ON_ERROR);
    $timestamp = time();
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

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

test('tebex checkout creates a mapped package basket', function (): void {
    $api = enableFirstPartyTebex();
    $payment = placeFirstPartyOrder('tebex:tebex', 2);
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'tebex:tebex',
        'https://example.test/return',
        'https://example.test/cancel',
        'tebex-start-1',
    );

    expect($attempt->redirect_url)->toBe('https://checkout.tebex.test/basket-ident')
        ->and($attempt->status)->toBe(PaymentAttemptStatus::Processing)
        ->and($api->basketCalls)->toBe(1);
});

test('tebex signed completed webhook completes a matching payment', function (): void {
    enableFirstPartyTebex();
    $payment = placeFirstPartyOrder('tebex:tebex', 2);
    $attempt = app(StartOrderPayment::class)->handle($payment->order, 'tebex:tebex', 'https://example.test/return', 'https://example.test/cancel', 'tebex-webhook-1');
    $body = json_encode([
        'id' => 'evt_tebex_test',
        'type' => 'payment.completed',
        'subject' => [
            'transaction_id' => $attempt->external_id,
            'price_paid' => ['amount' => 25.0, 'currency' => 'EUR'],
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

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});
