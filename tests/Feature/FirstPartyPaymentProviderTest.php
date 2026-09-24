<?php

declare(strict_types=1);

use Agovena\Extensions\Paddle\PaddleApi;
use Agovena\Extensions\Paddle\PaddlePaymentGateway;
use Agovena\Extensions\Paddle\PaddleProviderException;
use Agovena\Extensions\Tebex\TebexApi;
use Agovena\Extensions\Tebex\TebexPaymentGateway;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Orders\StorefrontOrderAccess;
use App\Agovena\Payments\AvailablePaymentMethods;
use App\Agovena\Payments\HandlePaymentWebhook;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Payments\PaymentInitiation;
use App\Agovena\Payments\RecordRefund;
use App\Agovena\Payments\StartOrderPayment;
use App\Agovena\Recurring\Enums\SubscriptionInterval;
use App\Agovena\Recurring\Enums\SubscriptionStatus;
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

function enableFirstPartyPaddle(?FakePaddleApi $api = null): FakePaddleApi
{
    app(ExtensionManager::class)->discover();
    $api ??= new FakePaddleApi;
    app()->instance(PaddleApi::class, $api);
    installAndEnableExtension('paddle');
    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('paddle', 'api_key', '[REDACTED]', secret: true);
    $settings->set('paddle', 'client_token', 'test_abcdefghijklmnopqrstuvwxyz1');
    $settings->set('paddle', 'webhook_secret', '[REDACTED]', secret: true);
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
    $payment = placeFirstPartyOrder('paddle:card', 1);
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'paddle:card',
        'https://example.test/return',
        'https://example.test/cancel',
        'paddle-start-1',
    );

    expect($attempt->redirect_url)->toBe('https://checkout.paddle.test/txn_test?allowed_payment_methods=card')
        ->and($attempt->status)->toBe(PaymentAttemptStatus::Processing)
        ->and($api->transactionCalls)->toBe(1)
        ->and($api->transactionPayload['currency_code'] ?? null)->toBe('EUR')
        ->and($api->transactionPayload['items'][0]['quantity'] ?? null)->toBe(1)
        ->and($api->transactionPayload['items'][0]['price']['unit_price'] ?? null)->toBe([
            'amount' => '2500',
            'currency_code' => 'EUR',
        ])
        ->and($api->transactionPayload['items'][0]['price']['quantity'] ?? null)->toBe([
            'minimum' => 1,
            'maximum' => 1,
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

test('paddle exposes configurable individual methods without a generic fallback', function (): void {
    enableFirstPartyPaddle();

    $gateway = app(PaddlePaymentGateway::class);
    $checkoutIds = array_map(
        static fn ($method): string => $method->id,
        $gateway->checkoutMethods(),
    );
    $configurableIds = array_column($gateway->configurableCheckoutMethods(), 'id');

    expect($checkoutIds)->toContain('paddle:card', 'paddle:apple_pay')
        ->and($checkoutIds)->not->toContain('paddle:paddle')
        ->and($configurableIds)->toContain('card', 'apple_pay');

    app(ExtensionSettingsRepository::class)->set('paddle', 'enabled_methods', 'card,ideal');
    $selectedIds = array_map(
        static fn ($method): string => $method->id,
        $gateway->checkoutMethods(),
    );

    expect($selectedIds)->toBe(['paddle:card', 'paddle:ideal']);
});

test('paddle requires a client token and does not expose a webhook disable setting', function (): void {
    app(ExtensionManager::class)->discover();
    $manifest = app(ExtensionManager::class)->manifest('paddle');
    $settingKeys = array_column($manifest?->settings ?? [], 'key');

    expect($manifest?->productionReady)->toBeTrue()
        ->and($settingKeys)->toContain('client_token', 'webhook_secret')
        ->and($settingKeys)->not->toContain('webhooks_enabled');
});

test('paddle hosted payment links render the extension-owned Paddle.js launcher', function (): void {
    enableFirstPartyPaddle();
    app(ExtensionSettingsRepository::class)->set('paddle', 'client_token', 'test_abcdefghijklmnopqrstuvwxyz1');

    $response = $this->get('/paddle/checkout?_ptxn=txn_test');

    $response->assertOk()
        ->assertSee('https://cdn.paddle.com/paddle/v2/paddle.js', false)
        ->assertSee('Paddle.Environment.set(\'sandbox\')', false)
        ->assertSee('test_abcdefghijklmnopqrstuvwxyz1', false)
        ->assertSee("displayMode: 'overlay'", false)
        ->assertSee('Paddle.Checkout.open({', false)
        ->assertSee('transactionId: transactionId', false)
        ->assertDontSee("displayMode: 'inline'", false)
        ->assertDontSee('frameTarget:', false)
        ->assertDontSee('frameInitialHeight:', false)
        ->assertSee("case 'checkout.payment.error'", false)
        ->assertSee("case 'checkout.error'", false)
        ->assertSee('theme: checkoutTheme', false)
        ->assertSee('locale: checkoutLocale', false)
        ->assertSee('showAddDiscounts: false', false)
        ->assertSee('showAddTaxId: false', false)
        ->assertSee('allowDiscountRemoval: false', false)
        ->assertDontSee('<h1 class="store-title">Paddle checkout</h1>', false)
        ->assertDontSee('[REDACTED]', false)
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self "https://buy.paddle.com" "https://sandbox-buy.paddle.com")');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain('cdn.paddle.com')
        ->and($csp)->toContain('frame-src https://*.paddle.com');
});

test('paddle checkout requires order access and binds the payment method to the attempt', function (): void {
    enableFirstPartyPaddle();
    $payment = placeFirstPartyOrder('paddle:card', 5);
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'paddle:card',
        'https://example.test/return',
        'https://example.test/cancel',
        'paddle-access-1',
    );
    session()->forget(StorefrontOrderAccess::SESSION_KEY);

    $checkoutUrl = '/paddle/checkout?_ptxn='.$attempt->external_id.'&allowed_payment_methods=card';
    $this->get($checkoutUrl)->assertNotFound();

    app(StorefrontOrderAccess::class)->remember($payment->order);
    $this->get($checkoutUrl)
        ->assertOk()
        ->assertSee('allowedPaymentMethods', false)
        ->assertSee('card', false);

    $this->get('/paddle/checkout?_ptxn='.$attempt->external_id.'&allowed_payment_methods=bancontact')
        ->assertNotFound();
});

test('paddle cancels an open provider transaction before local order cancellation', function (): void {
    $api = enableFirstPartyPaddle();
    $payment = placeFirstPartyOrder('paddle:card', 7);
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'paddle:card',
        'https://example.test/return',
        'https://example.test/cancel',
        'paddle-cancel-1',
    );
    $api->transaction['status'] = 'ready';

    $cancelled = app(PaddlePaymentGateway::class)->cancel($payment->fresh(), $attempt->fresh());

    expect($api->transaction['status'])->toBe('canceled')
        ->and($cancelled->status)->toBe(PaymentStatus::Cancelled)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Cancelled);
});

test('paddle rejected refunds remain failed instead of completed', function (): void {
    $api = enableFirstPartyPaddle();
    $payment = placeFirstPartyOrder('paddle:card', 8);
    app(StartOrderPayment::class)->handle(
        $payment->order,
        'paddle:card',
        'https://example.test/return',
        'https://example.test/cancel',
        'paddle-refund-rejected-1',
    );
    $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
    $payment->order()->update(['status' => OrderStatus::Paid]);
    $api->adjustment = [
        'id' => 'adj_rejected',
        'status' => 'rejected',
    ];

    $refund = app(RecordRefund::class)->handle(
        $payment->fresh(),
        $this->createStaff(),
        $payment->amount,
        'Rejected by provider',
    );

    expect($refund->status->value)->toBe('failed');
});

test('paddle status sync reconciles an adjustment after a missed webhook', function (): void {
    $api = enableFirstPartyPaddle();
    $payment = placeFirstPartyOrder('paddle:card', 9);
    app(StartOrderPayment::class)->handle(
        $payment->order,
        'paddle:card',
        'https://example.test/return',
        'https://example.test/cancel',
        'paddle-refund-sync-1',
    );
    $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
    $payment->order()->update(['status' => OrderStatus::Paid]);
    $api->adjustment = [
        'id' => 'adj_sync',
        'status' => 'pending_approval',
    ];

    $refund = app(RecordRefund::class)->handle(
        $payment->fresh(),
        $this->createStaff(),
        $payment->amount,
        'Awaiting provider approval',
    );
    $api->transaction['adjustments'] = [[
        'id' => 'adj_sync',
        'status' => 'approved',
    ]];

    app(PaddlePaymentGateway::class)->syncStatus($payment->fresh());

    expect($refund->fresh()->status->value)->toBe('completed')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
});

test('paddle launcher passes the selected method and payment status return', function (): void {
    $api = enableFirstPartyPaddle();
    $api->preview = ['available_payment_methods' => ['apple_pay']];
    $payment = placeFirstPartyOrder('paddle:apple_pay', 6);
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'paddle:apple_pay',
        'https://example.test/return',
        'https://example.test/cancel',
        'paddle-launcher-return-1',
    );

    app(StorefrontOrderAccess::class)->remember($payment->order);

    $response = $this->get('/paddle/checkout?_ptxn=txn_test&allowed_payment_methods=apple_pay');

    $response->assertOk()
        ->assertSee('checkoutSettings.allowedPaymentMethods = allowedPaymentMethods', false)
        ->assertSee('"apple_pay"', false)
        ->assertSee('/payment', false)
        ->assertDontSee('[REDACTED]', false);

    expect($api->transactionCalls)->toBe(1);
});

test('paddle health fails when the required webhook secret is missing', function (): void {
    enableFirstPartyPaddle();
    app(ExtensionSettingsRepository::class)->forget('paddle', 'webhook_secret');

    $gateway = app(PaddlePaymentGateway::class);

    expect($gateway->health()->ok)->toBeFalse()
        ->and($gateway->capabilities()->webhooks)->toBeTrue();
});

test('paddle health rejects a client token from the wrong provider mode', function (): void {
    enableFirstPartyPaddle();
    app(ExtensionSettingsRepository::class)->set('paddle', 'client_token', 'live_abcdefghijklmnopqrstuvwxyz1');

    expect(app(PaddlePaymentGateway::class)->health()->ok)->toBeFalse();
});

test('paddle live health rejects a non-HTTPS webhook URL', function (): void {
    enableFirstPartyPaddle();
    app(ExtensionSettingsRepository::class)->set('paddle', 'sandbox', false);
    app(ExtensionSettingsRepository::class)->set('paddle', 'client_token', 'live_abcdefghijklmnopqrstuvwxyz1');

    expect(app(PaddlePaymentGateway::class)->health()->ok)->toBeFalse();
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

test('paddle preserves a safe provider error when checkout creation fails', function (): void {
    $api = enableFirstPartyPaddle();
    $api->createException = new PaddleProviderException(
        'paddle::messages.errors.request_failed',
        'provider error',
        'transaction_default_checkout_url_not_set',
        'default payment link missing',
        400,
    );
    $payment = placeFirstPartyOrder('paddle:card', 5);

    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'paddle:card',
        'https://example.test/return',
        'https://example.test/cancel',
        'paddle-missing-payment-link-1',
    );

    expect($attempt->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($attempt->response_meta['provider_error_code'] ?? null)->toBe('transaction_default_checkout_url_not_set')
        ->and($attempt->response_meta['failure_message'] ?? null)->toContain('default payment link');
});

test('paddle status synchronization remains available with required webhook configuration', function (): void {
    $api = enableFirstPartyPaddle();
    $payment = placeFirstPartyOrder('paddle:card', 3);
    app(StartOrderPayment::class)->handle(
        $payment->order,
        'paddle:card',
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
        'payment_method' => 'paddle:card',
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
    $attempt = app(StartOrderPayment::class)->handle($order, 'paddle:card', 'https://example.test/return', 'https://example.test/cancel', 'paddle-recurring-1');

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
        'payment_method' => 'paddle:card',
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
    app(StartOrderPayment::class)->handle($order, 'paddle:card', 'https://example.test/return', 'https://example.test/cancel', 'paddle-subscription-webhook-1');
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

test('paddle renewal transaction events still settle the payment for an existing subscription', function (): void {
    enableFirstPartyModules(['subscriptions']);
    enableFirstPartyPaddle();
    $payment = placeFirstPartyOrder('paddle:card', 10);
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'paddle:card',
        'https://example.test/return',
        'https://example.test/cancel',
        'paddle-renewal-event-1',
    );
    Subscription::query()->create([
        'number' => 'SUB-EXISTING-1',
        'customer_email' => 'existing@example.test',
        'status' => SubscriptionStatus::Active,
        'interval' => SubscriptionInterval::Month,
        'interval_count' => 1,
        'price_amount' => $payment->amount,
        'currency' => $payment->currency,
        'quantity' => 1,
        'renewal_mode' => 'automatic',
        'provider_reference' => 'sub_existing',
    ]);

    $body = json_encode([
        'event_id' => 'evt_paddle_renewal_paid',
        'event_type' => 'transaction.paid',
        'data' => [
            'id' => $attempt->external_id,
            'status' => 'paid',
            'subscription_id' => 'sub_existing',
            'currency_code' => $payment->currency,
            'details' => [
                'totals' => ['grand_total' => (string) $payment->amount],
                'line_items' => [['quantity' => 1, 'totals' => ['total' => (string) $payment->amount]]],
            ],
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

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Succeeded);
});

test('tebex exposes only provider credentials and no product package mapping setting', function (): void {
    enableFirstPartyTebex();
    $manifest = app(ExtensionManager::class)->manifest('tebex');
    $settingKeys = array_column($manifest?->settings ?? [], 'key');

    expect($settingKeys)->toBe(['project_id', 'secret_key', 'webhook_secret'])
        ->and($settingKeys)->not->toContain('package_map');
});

test('tebex checkout creates a custom checkout without package mapping', function (): void {
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
        ->and($api->checkoutCalls)->toBe(1)
        ->and($api->checkoutPayloads[0]['items'][0]['package']['name'] ?? null)->not->toBeEmpty()
        ->and($api->checkoutPayloads[0]['items'][0]['package']['price'] ?? null)->toBe(25.0)
        ->and($api->checkoutPayloads[0]['items'][0]['qty'] ?? null)->toBe(1)
        ->and($api->checkoutPayloads[0]['items'][0]['package']['custom']['agovena_product_id'] ?? null)->toBe((string) $payment->order->items->first()->product_id);
});

test('tebex unknown custom checkout outcomes require payment reconciliation', function (): void {
    $api = enableFirstPartyTebex();
    $api->throwOn = 'create_checkout';
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
        ->and($api->checkoutCalls)->toBe(1);
});

test('tebex retry reuses the custom checkout idempotency key after response loss', function (): void {
    $api = enableFirstPartyTebex();
    $api->throwOn = 'create_checkout';
    $payment = placeFirstPartyOrder('tebex:tebex', 2);
    $gateway = app(PaymentGatewayRegistry::class)->get('tebex');
    $request = new PaymentInitiation(
        order: $payment->order,
        payment: $payment,
        returnUrl: 'https://example.test/return',
        cancelUrl: 'https://example.test/cancel',
        idempotencyKey: 'tebex-custom-retry-1',
    );

    $firstResult = $gateway->initiate($request);
    expect($firstResult->status)->toBe('unknown');
    $api->throwOn = null;
    $result = $gateway->initiate($request);

    expect($result->redirectUrl)->toBe('https://checkout.tebex.test/basket-ident')
        ->and($api->checkoutCalls)->toBe(2)
        ->and($api->checkoutIdempotencyKeys)->toBe(['tebex-custom-retry-1', 'tebex-custom-retry-1']);
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

test('tebex supports provider-managed recurring checkout without pretending to offer local methods', function (): void {
    enableFirstPartyModules(['subscriptions']);
    $api = enableFirstPartyTebex();
    $product = Product::factory()->active()->create(['id' => 22, 'price_amount' => 2500]);
    app(ProductCapabilityManager::class)->enable($product, 'subscribable', [
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);
    app(CartService::class)->add($product->id, 1);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'Recurring Tebex Buyer',
        'customer_email' => 'tebex-recurring@example.test',
        'payment_method' => 'tebex:tebex',
        'billing' => AddressData::fromArray([
            'name' => 'Recurring Tebex Buyer',
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
        'custom_properties' => ['_agovena_renewal_mode' => 'automatic'],
    ]);
    $payment = $order->payment()->firstOrFail();
    $attempt = app(StartOrderPayment::class)->handle(
        $order,
        'tebex:tebex',
        'https://example.test/return',
        'https://example.test/cancel',
        'tebex-recurring-1',
    );

    expect(app(PaymentGatewayRegistry::class)->get('tebex')->capabilities()->recurring)->toBeTrue()
        ->and($attempt->redirect_url)->toBe('https://checkout.tebex.test/basket-ident')
        ->and(array_map(static fn ($method): string => $method->id, app(TebexPaymentGateway::class)->checkoutMethods()))->toBe(['tebex:tebex'])
        ->and(app(TebexPaymentGateway::class)->checkoutMethods()[0]->label)->toBe('Tebex Checkout')
        ->and(app(TebexPaymentGateway::class)->checkoutMethods()[0]->icon)->toBe('ag:payment-method/tebex')
        ->and(is_file(public_path('images/payment-methods/tebex.svg')))->toBeTrue()
        ->and($api->checkoutPayloads[0]['items'][0]['package']['type'] ?? null)->toBe('subscription')
        ->and($api->checkoutPayloads[0]['items'][0]['package']['expiry_period'] ?? null)->toBe('month')
        ->and($api->checkoutPayloads[0]['items'][0]['package']['expiry_length'] ?? null)->toBe(1);
});

test('tebex refund status synchronization completes a pending full refund after a missed webhook', function (): void {
    $api = enableFirstPartyTebex();
    $payment = placeFirstPartyOrder('tebex:tebex', 25);
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'tebex:tebex',
        'https://example.test/return',
        'https://example.test/cancel',
        'tebex-refund-sync-1',
    );
    $attempt->update(['external_id' => 'tbx-refund-sync']);
    $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
    $payment->order()->update(['status' => OrderStatus::Paid]);
    $api->refund = [
        'transaction_id' => 'tbx-refund-sync',
        'status' => ['id' => 21, 'description' => 'Refund Pending'],
    ];

    $refund = app(RecordRefund::class)->handle(
        $payment->fresh(),
        $this->createStaff(),
        $payment->amount,
        'Awaiting Tebex refund',
    );
    expect($refund->status->value)->toBe('processing');

    $api->payment = [
        'status' => ['id' => 21, 'description' => 'Refund Pending'],
    ];
    app(TebexPaymentGateway::class)->syncStatus($payment->fresh());
    expect($refund->fresh()->status->value)->toBe('processing');

    $api->payment = [
        'status' => ['id' => 2, 'description' => 'Refund'],
    ];
    app(TebexPaymentGateway::class)->syncStatus($payment->fresh());

    expect($refund->fresh()->status->value)->toBe('completed')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
});

test('tebex recurring webhooks create and synchronize the Core subscription projection', function (): void {
    enableFirstPartyModules(['subscriptions']);
    enableFirstPartyTebex();
    $product = Product::factory()->active()->create(['id' => 23, 'price_amount' => 2500]);
    app(ProductCapabilityManager::class)->enable($product, 'subscribable', [
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'Recurring Tebex Buyer',
        'customer_email' => 'tebex-recurring@example.test',
        'payment_method' => 'tebex:tebex',
        'billing' => AddressData::fromArray([
            'name' => 'Recurring Tebex Buyer',
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
        'custom_properties' => ['_agovena_renewal_mode' => 'automatic'],
    ]);
    $payment = $order->payment()->firstOrFail();
    app(StartOrderPayment::class)->handle($order, 'tebex:tebex', 'https://example.test/return', 'https://example.test/cancel', 'tebex-recurring-webhook-1');

    $send = static function (array $event): void {
        $body = json_encode($event, JSON_THROW_ON_ERROR);
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
    };

    $send([
        'id' => 'evt_tebex_recurring_payment',
        'type' => 'payment.completed',
        'subject' => [
            'transaction_id' => 'txn_tebex_recurring',
            'recurring_payment_reference' => 'tbx-r-recurring-test',
            'price_paid' => ['amount' => 25.0, 'currency' => 'EUR'],
            'products' => [['id' => 54321, 'quantity' => 1]],
            'custom' => ['order_id' => (string) $order->id, 'payment_id' => (string) $payment->id],
        ],
    ]);

    $subscription = Subscription::query()->where('order_id', $order->id)->firstOrFail();
    expect($subscription->provider_reference)->toBe('tbx-r-recurring-test')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid);

    $send([
        'id' => 'evt_tebex_recurring_cancel_requested',
        'type' => 'recurring-payment.cancellation.requested',
        'subject' => [
            'reference' => 'tbx-r-recurring-test',
            'status' => ['id' => 2, 'description' => 'Active'],
            'next_payment_at' => '2026-11-01T00:00:00Z',
        ],
    ]);

    expect($subscription->fresh()->cancel_at_period_end)->toBeTrue()
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);

    $send([
        'id' => 'evt_tebex_recurring_cancel_aborted',
        'type' => 'recurring-payment.cancellation.aborted',
        'subject' => [
            'reference' => 'tbx-r-recurring-test',
            'status' => ['id' => 2, 'description' => 'Active'],
            'next_payment_at' => '2026-12-01T00:00:00Z',
        ],
    ]);

    expect($subscription->fresh()->cancel_at_period_end)->toBeFalse()
        ->and($subscription->fresh()->next_billing_at?->toISOString())->toBe('2026-12-01T00:00:00.000000Z');

    $send([
        'id' => 'evt_tebex_recurring_ended',
        'type' => 'recurring-payment.ended',
        'subject' => [
            'reference' => 'tbx-r-recurring-test',
            'status' => ['id' => 5, 'description' => 'Cancelled'],
        ],
    ]);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($subscription->fresh()->next_billing_at)->toBeNull();
});

test('tebex maps provider subscription cancellation and resume to the supported API endpoints', function (): void {
    enableFirstPartyModules(['subscriptions']);
    $api = enableFirstPartyTebex();
    $payment = placeFirstPartyOrder('tebex:tebex', 24);
    $subscription = Subscription::query()->create([
        'number' => 'SUB-TEBEX-1',
        'customer_email' => 'tebex-subscription@example.test',
        'status' => SubscriptionStatus::Active,
        'interval' => SubscriptionInterval::Month,
        'interval_count' => 1,
        'price_amount' => $payment->amount,
        'currency' => $payment->currency,
        'quantity' => 1,
        'renewal_mode' => 'automatic',
        'payment_gateway' => 'tebex',
        'provider_reference' => 'tbx-r-api-test',
    ]);

    app(SubscriptionService::class)->cancel($subscription, atPeriodEnd: true);
    expect($api->recurringActions)->toContain(['action' => 'cancel', 'reference' => 'tbx-r-api-test']);

    app(SubscriptionService::class)->resume($subscription->fresh());
    expect($api->recurringActions)->toContain(['action' => 'status', 'reference' => 'tbx-r-api-test', 'status' => 'Active']);
});
