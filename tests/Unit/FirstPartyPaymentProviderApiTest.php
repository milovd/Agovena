<?php

declare(strict_types=1);

use Agovena\Extensions\Paddle\HttpPaddleApi;
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

it('creates Tebex baskets and adds mapped packages', function (): void {
    Http::fake([
        'https://checkout.tebex.io/api/baskets' => Http::response([
            'id' => 'basket-test',
            'ident' => 'basket-ident',
            'links' => ['checkout' => 'https://checkout.tebex.test/basket-ident'],
        ]),
        'https://checkout.tebex.io/api/baskets/basket-ident/packages' => Http::response([
            'id' => 'basket-test',
            'ident' => 'basket-ident',
        ]),
    ]);

    $api = new HttpTebexApi('project-test', '[REDACTED]');
    $basket = $api->createBasket([
        'email' => 'buyer@example.test',
        'complete_url' => 'https://agovena.test/complete',
    ]);
    $added = $api->addPackage('basket-ident', '12345', 2);

    expect($basket['ident'])->toBe('basket-ident')
        ->and($added['ident'])->toBe('basket-ident');

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->method() === 'POST'
            && str_ends_with($request->url(), '/baskets/basket-ident/packages')
            && $request->data() === ['package' => ['id' => '12345'], 'qty' => 2];
    });
});
