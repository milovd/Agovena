<?php

declare(strict_types=1);

use Agovena\Extensions\Paddle\PaddleApi;
use Agovena\Extensions\Paddle\PaddlePaymentGateway;
use Agovena\Extensions\Tebex\TebexApi;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Payments\AvailablePaymentMethods;
use App\Agovena\Payments\HandlePaymentWebhook;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Payments\PaymentInitiation;
use App\Agovena\Payments\RecordRefund;
use App\Agovena\Payments\StartOrderPayment;
use App\Agovena\Recurring\Models\Subscription;
use App\Agovena\Recurring\SubscriptionService;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\Request;
use Tests\Support\CreatesStaff;
use Tests\Support\FakePaddleApi;
use Tests\Support\FakeTebexApi;

uses(CreatesStaff::class);

function enableFirstPartyPaddle(?FakePaddleApi $api = null, bool $withWebhook = true): FakePaddleApi
{
    app(ExtensionManager::class)->discover();
    $api ??= new FakePaddleApi;
    app()->instance(PaddleApi::class, $api);
    installAndEnableExtension('paddle');
    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('paddle', 'api_key', '[REDACTED]', secret: true);
    if ($withWebhook) {
        $settings->set('paddle', 'webhook_secret', '[REDACTED]', secret: true);
    }
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
        ->and($api->transactionCalls)->toBe(1)
        ->and($api->transactionPayload['currency_code'] ?? null)->toBe('EUR')
        ->and($api->transactionPayload['items'][0]['quantity'] ?? null)->toBe(1)
        ->and($api->transactionPayload['items'][0]['price']['unit_price'] ?? null)->toBe([
            'amount' => '2500',
            'currency_code' => 'EUR',
        ])
        ->and($api->transactionPayload['items'][0]['price']['product']['name'] ?? null)->toBe($payment->order->number);

    $body = json_encode([
        'event_id' => 'evt_paddle_test',
        'event_type' => 'transaction.paid',
        'data' => [
            'id' => 'txn_test',
            'status' => 'paid',
            'currency_code' => 'EUR',
            'details' => ['totals' => ['grand_total' => '2500'], 'line_items' => [['price_id' => 'pri_generated', 'quantity' => 1, 'totals' => ['total' => '2500']]]],
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

test('paddle exposes country-aware methods and restricts its hosted checkout', function (): void {
    $api = enableFirstPartyPaddle();
    $api->preview = ['available_payment_methods' => ['card', 'ideal']];

    $options = app(AvailablePaymentMethods::class)->options('NL');
    $ids = array_column($options, 'id');

    expect($ids)->toContain('paddle:card', 'paddle:ideal')
        ->and($ids)->not->toContain('paddle:bancontact', 'paddle:pix');

    $payment = placeFirstPartyOrder('paddle:ideal', 2);
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'paddle:ideal',
        'https://example.test/return',
        'https://example.test/cancel',
        'paddle-ideal-1',
    );

    expect($attempt->redirect_url)->toBe('https://checkout.paddle.test/txn_test?allowed_payment_methods=ideal')
        ->and($api->previewPayload['address'] ?? null)->toBe([
            'country_code' => 'NL',
            'postal_code' => '1000 AA',
        ])
        ->and($api->previewPayload['currency_code'] ?? null)->toBe('EUR');
});

test('paddle checkout methods expose local branded payment icons', function (): void {
    enableFirstPartyPaddle();

    foreach (['card', 'apple_pay', 'google_pay', 'paypal', 'alipay', 'bancontact', 'blik', 'ideal', 'kakao_pay', 'mb_way', 'naver_pay', 'payco', 'pix', 'samsung_pay', 'upi'] as $method) {
        expect(is_file(public_path('images/payment-methods/'.$method.'.svg')))->toBeTrue();
    }

    $southKoreaCard = collect(app(PaddlePaymentGateway::class)->checkoutMethods())
        ->first(static fn ($method): bool => $method->id === 'paddle:south_korea_local_card');

    expect($southKoreaCard?->icon)->toBe('ag:payment-method/card');
});

test('paddle rejects a method that transaction preview does not allow', function (): void {
    $api = enableFirstPartyPaddle();
    $api->preview = ['available_payment_methods' => ['card']];
    $payment = placeFirstPartyOrder('paddle:ideal', 4);

    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'paddle:ideal',
        'https://example.test/return',
        'https://example.test/cancel',
        'paddle-ideal-unavailable-1',
    );

    expect($attempt->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($api->transactionCalls)->toBe(0)
        ->and($api->previewPayload['address']['country_code'] ?? null)->toBe('NL');
});

test('paddle status synchronization completes a local checkout without a webhook', function (): void {
    $api = enableFirstPartyPaddle(withWebhook: false);
    $payment = placeFirstPartyOrder('paddle:paddle', 3);
    app(StartOrderPayment::class)->handle(
        $payment->order,
        'paddle:paddle',
        'https://example.test/return',
        'https://example.test/cancel',
        'paddle-sync-1',
    );

    $api->transaction['status'] = 'completed';
    $updated = app(PaddlePaymentGateway::class)->syncStatus($payment->fresh());

    expect($updated->status)->toBe(PaymentStatus::Paid)
        ->and($api->transactionCalls)->toBe(1);
});

test('paddle creates a recurring inline price for automatic subscriptions', function (): void {
    enableFirstPartyModules(['subscriptions']);
    $api = enableFirstPartyPaddle();
    $api->transaction['subscription_id'] = 'sub_test';
    $api->transaction['available_payment_methods'] = ['card', 'ideal'];
    $product = Product::factory()->active()->create(['price_amount' => 2500]);
    app(ProductCapabilityManager::class)->enable($product, 'subscribable', [
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);
    app(CartService::class)->add($product->id, 1);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'Recurring Buyer',
        'customer_email' => 'recurring@example.test',
        'payment_method' => 'paddle:paddle',
        'billing' => AddressData::fromArray([
            'name' => 'Recurring Buyer',
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
        'custom_properties' => ['_agovena_renewal_mode' => 'automatic'],
    ]);
    $payment = $order->payment()->firstOrFail();
    $attempt = app(StartOrderPayment::class)->handle($order, 'paddle:paddle', 'https://example.test/return', 'https://example.test/cancel', 'paddle-recurring-1');

    expect($api->transactionPayload['items'][0]['price']['billing_cycle'] ?? null)->toBe([
        'interval' => 'month',
        'frequency' => 1,
    ])
        ->and($attempt->response_meta['provider_subscription_id'] ?? null)->toBe('sub_test')
        ->and($attempt->response_meta['available_payment_methods'] ?? null)->toBe(['card', 'ideal'])
        ->and(app(PaddlePaymentGateway::class)->availablePaymentMethods($payment->fresh()))->toBe(['card', 'ideal'])
        ->and($payment->fresh()->order_id)->toBe($order->id);
});

test('paddle subscription events synchronize the Core subscription projection', function (): void {
    enableFirstPartyModules(['subscriptions']);
    $api = enableFirstPartyPaddle();
    $api->transaction['subscription_id'] = 'sub_test';
    $product = Product::factory()->active()->create(['price_amount' => 2500]);
    app(ProductCapabilityManager::class)->enable($product, 'subscribable', [
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'Subscription Buyer',
        'customer_email' => 'subscription@example.test',
        'payment_method' => 'paddle:paddle',
        'billing' => AddressData::fromArray([
            'name' => 'Subscription Buyer',
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
        'custom_properties' => ['_agovena_renewal_mode' => 'automatic'],
    ]);
    $payment = $order->payment()->firstOrFail();
    app(StartOrderPayment::class)->handle($order, 'paddle:paddle', 'https://example.test/return', 'https://example.test/cancel', 'paddle-subscription-webhook-1');
    $body = json_encode([
        'event_id' => 'evt_paddle_subscription_paid',
        'event_type' => 'transaction.paid',
        'data' => [
            'id' => 'txn_test',
            'status' => 'paid',
            'subscription_id' => 'sub_test',
            'currency_code' => 'EUR',
            'details' => [
                'totals' => ['grand_total' => '2500'],
                'line_items' => [['quantity' => 1, 'totals' => ['total' => '2500']]],
            ],
            'custom_data' => ['order_id' => (string) $order->id, 'payment_id' => (string) $payment->id],
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

    $subscription = Subscription::query()->where('order_id', $order->id)->firstOrFail();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($subscription->provider_reference)->toBe('sub_test');

    $updateBody = json_encode([
        'event_id' => 'evt_paddle_subscription_updated',
        'event_type' => 'subscription.updated',
        'data' => [
            'id' => 'sub_test',
            'status' => 'active',
            'current_billing_period' => [
                'starts_at' => '2026-10-01T00:00:00Z',
                'ends_at' => '2026-11-01T00:00:00Z',
            ],
            'next_billed_at' => '2026-11-01T00:00:00Z',
            'custom_data' => ['order_id' => (string) $order->id],
            'scheduled_change' => null,
        ],
    ], JSON_THROW_ON_ERROR);
    $updateTimestamp = time();
    $updateSignature = hash_hmac('sha256', $updateTimestamp.':'.$updateBody, '[REDACTED]');
    app(HandlePaymentWebhook::class)->handle('paddle', Request::create(
        '/webhooks/payments/paddle',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_PADDLE-SIGNATURE' => 'ts='.$updateTimestamp.';h1='.$updateSignature],
        $updateBody,
    ));

    expect($subscription->fresh()->current_period_end?->toISOString())->toBe('2026-11-01T00:00:00.000000Z')
        ->and($subscription->fresh()->next_billing_at?->toISOString())->toBe('2026-11-01T00:00:00.000000Z');

    app(SubscriptionService::class)->cancel($subscription->fresh(), atPeriodEnd: true);
    expect($api->lastSubscriptionAction)->toMatchArray([
        'action' => 'cancel',
        'id' => 'sub_test',
        'at_period_end' => true,
    ]);

    app(SubscriptionService::class)->resume($subscription->fresh());
    expect($api->lastSubscriptionAction)->toMatchArray([
        'action' => 'clear_scheduled_change',
        'id' => 'sub_test',
    ]);
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

test('tebex unknown package outcomes require payment reconciliation', function (): void {
    $api = enableFirstPartyTebex();
    $api->throwOn = 'add_package';
    $payment = placeFirstPartyOrder('tebex:tebex', 2);

    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'tebex:tebex',
        'https://example.test/return',
        'https://example.test/cancel',
        'tebex-unknown-1',
    );

    expect($attempt->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($attempt->response_meta['provider_outcome'] ?? null)->toBe('unknown')
        ->and($payment->fresh()->reconciliation_status)->toBe('manual_review')
        ->and($api->basketCalls)->toBe(1);
});

test('tebex retry does not add a package twice after basket response loss', function (): void {
    $api = enableFirstPartyTebex();
    $api->throwOn = 'get_basket_after_add';
    $payment = placeFirstPartyOrder('tebex:tebex', 2);
    $gateway = app(PaymentGatewayRegistry::class)->get('tebex');
    $request = new PaymentInitiation(
        order: $payment->order,
        payment: $payment,
        returnUrl: 'https://example.test/return',
        cancelUrl: 'https://example.test/cancel',
        idempotencyKey: 'tebex-package-retry-1',
    );

    $firstResult = $gateway->initiate($request);
    expect($firstResult->status)->toBe('unknown');
    $api->throwOn = null;
    $result = $gateway->initiate($request);

    expect($result->redirectUrl)->toBe('https://checkout.tebex.test/basket-ident')
        ->and($api->addPackageCalls)->toBe(1)
        ->and($api->addPackageIdempotencyKeys)->toBe(['tebex-package-retry-1:package:12345']);
});

test('tebex refund unknown outcome remains pending for reconciliation', function (): void {
    $api = enableFirstPartyTebex();
    $payment = placeFirstPartyOrder('tebex:tebex', 2);
    app(StartOrderPayment::class)->handle(
        $payment->order,
        'tebex:tebex',
        'https://example.test/return',
        'https://example.test/cancel',
        'tebex-refund-attempt-1',
    );
    $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
    $payment->order()->update(['status' => OrderStatus::Paid]);
    $api->throwOn = 'refund';

    $refund = app(RecordRefund::class)->handle(
        $payment->fresh(),
        $this->createStaff(),
        $payment->amount,
        'Provider response lost',
    );

    $api->throwOn = null;
    $retry = app(RecordRefund::class)->handle(
        $payment->fresh(),
        $this->createStaff(),
        $payment->amount,
        'Provider response lost',
    );

    expect($refund->status->value)->toBe('pending')
        ->and($retry->id)->toBe($refund->id)
        ->and($retry->status->value)->toBe('completed')
        ->and($payment->fresh()->reconciliation_status)->toBe('manual_review')
        ->and($payment->fresh()->reconciliation_meta['reason'] ?? null)->toBe('provider_refund_outcome_unknown')
        ->and($api->refundIdempotencyKeys)->toBe(['refund-'.$refund->id, 'refund-'.$refund->id]);
});

test('tebex signed completed webhook completes a matching payment', function (): void {
    enableFirstPartyTebex();
    $payment = placeFirstPartyOrder('tebex:tebex', 2);
    $attempt = app(StartOrderPayment::class)->handle($payment->order, 'tebex:tebex', 'https://example.test/return', 'https://example.test/cancel', 'tebex-webhook-1');
    $body = json_encode([
        'id' => 'evt_tebex_test',
        'type' => 'payment.completed',
        'subject' => [
            'transaction_id' => 'txn_live_tebex',
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

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($attempt->fresh()->external_id)->toBe('txn_live_tebex');
});
