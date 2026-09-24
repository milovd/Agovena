<?php

declare(strict_types=1);

use Agovena\Extensions\Paddle\HttpPaddleApi;
use Agovena\Extensions\Paddle\PaddleProviderException;
use Agovena\Extensions\Tebex\HttpTebexApi;
use App\Agovena\Extensions\ExtensionManager;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    app(ExtensionManager::class)->discover();
});

it('creates Paddle transactions with checkout metadata and idempotency', function (): void {
    Http::fake([
        'https://sandbox-api.paddle.com/transactions' => Http::response([
            'data' => [
                'id' => 'txn_test',
                'status' => 'draft',
                'checkout' => ['url' => 'https://checkout.paddle.test/txn_test'],
            ],
        ]),
    ]);

    $response = (new HttpPaddleApi('[REDACTED]', sandbox: true))->createTransaction([
        'items' => [['price_id' => 'pri_test', 'quantity' => 1]],
        'custom_data' => ['order_id' => '42'],
    ], 'payment-attempt-42');

    expect($response['id'])->toBe('txn_test');
    Http::assertSent(function (HttpRequest $request): bool {
        return $request->method() === 'POST'
            && $request->url() === 'https://sandbox-api.paddle.com/transactions'
            && $request->header('Paddle-Version') === ['1']
            && $request->header('Idempotency-Key') === ['payment-attempt-42']
            && $request->data()['items'][0]['price_id'] === 'pri_test';
    });
});

it('previews Paddle transaction payment methods with location context', function (): void {
    Http::fake([
        'https://sandbox-api.paddle.com/transactions/preview' => Http::response([
            'data' => [
                'available_payment_methods' => ['card', 'ideal'],
            ],
        ]),
    ]);

    $response = (new HttpPaddleApi('[REDACTED]', sandbox: true))->previewTransaction([
        'items' => [['price_id' => 'pri_test', 'quantity' => 1]],
        'currency_code' => 'EUR',
        'address' => ['country_code' => 'NL', 'postal_code' => '1000 AA'],
    ]);

    expect($response['available_payment_methods'])->toBe(['card', 'ideal']);
    Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://sandbox-api.paddle.com/transactions/preview'
        && $request->header('Paddle-Version') === ['1']
        && $request->data()['address']['country_code'] === 'NL');
});

it('preserves Paddle error codes without exposing the request payload', function (): void {
    Http::fake([
        'https://sandbox-api.paddle.com/transactions' => Http::response([
            'error' => [
                'code' => 'transaction_default_checkout_url_not_set',
                'detail' => 'Set a default payment link.',
            ],
        ], 400),
    ]);

    try {
        (new HttpPaddleApi('[REDACTED]', sandbox: true))->createTransaction([
            'custom_data' => ['payment_id' => '42'],
        ]);
        test()->fail('Expected PaddleProviderException was not thrown.');
    } catch (PaddleProviderException $exception) {
        expect($exception->providerCode)->toBe('transaction_default_checkout_url_not_set')
            ->and($exception->providerDetail)->toBe('Set a default payment link.')
            ->and($exception->httpStatus)->toBe(400)
            ->and($exception->getMessage())->not->toContain('[REDACTED]');
    }
});

it('refunds a Tebex payment through the documented bodyless endpoint', function (): void {
    Http::fake([
        'https://checkout.tebex.io/api/payments/*' => Http::response(['transaction_id' => 'tbx-refund']),
    ]);

    $api = new HttpTebexApi('project-test', '[REDACTED]');
    expect($api->refundPayment('tbx-payment', 'customer request'))->toBe(['transaction_id' => 'tbx-refund']);

    Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://checkout.tebex.io/api/payments/tbx-payment/refund?type=txn_id'
        && $request->data() === []);
});

it('creates a custom Tebex checkout without package identifiers', function (): void {
    Http::fake([
        'https://checkout.tebex.io/api/checkout' => Http::response([
            'id' => 'checkout-test',
            'ident' => 'basket-ident',
            'links' => ['checkout' => 'https://checkout.tebex.test/basket-ident'],
        ]),
    ]);

    $api = new HttpTebexApi('project-test', '[REDACTED]');
    $checkout = $api->createCheckout([
        'basket' => [
            'email' => 'buyer@example.test',
            'complete_url' => 'https://agovena.test/complete',
        ],
        'items' => [[
            'package' => [
                'name' => 'Custom product',
                'price' => 25.0,
                'type' => 'single',
                'custom' => ['agovena_product_id' => '42'],
            ],
            'qty' => 1,
        ]],
    ], 'payment-attempt-42');

    expect($checkout['ident'])->toBe('basket-ident');
    Http::assertSent(function (HttpRequest $request): bool {
        return $request->method() === 'POST'
            && $request->url() === 'https://checkout.tebex.io/api/checkout'
            && $request->header('Idempotency-Key') === ['payment-attempt-42']
            && $request->data()['items'][0]['package']['name'] === 'Custom product'
            && $request->data()['items'][0]['package']['price'] === 25.0
            && ! array_key_exists('id', $request->data()['items'][0]['package']);
    });
});

it('probes the Paddle API with a read-only product request', function (): void {
    Http::fake([
        'https://sandbox-api.paddle.com/products?per_page=1' => Http::response(['data' => []]),
    ]);

    (new HttpPaddleApi('[REDACTED]', sandbox: true))->ping();

    Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://sandbox-api.paddle.com/products?per_page=1'
        && $request->header('Paddle-Version') === ['1']);
});

it('probes Tebex credentials without creating a basket', function (): void {
    Http::fake([
        'https://checkout.tebex.io/api/payments/tbx-0000000000000000000000000000000000000000?type=txn_id' => Http::response([], 404),
    ]);

    (new HttpTebexApi('project-test', '[REDACTED]'))->ping();

    Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'GET'
        && str_contains($request->url(), '/payments/tbx-0000000000000000000000000000000000000000')
        && str_contains($request->url(), 'type=txn_id'));
});
