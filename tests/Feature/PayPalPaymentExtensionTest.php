<?php

declare(strict_types=1);

use Agovena\Extensions\PayPal\PayPalApi;
use Agovena\Extensions\PayPal\PayPalPaymentGateway;
use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Payments\AvailablePaymentMethods;
use App\Agovena\Payments\HandlePaymentWebhook;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Payments\StartOrderPayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\ExtensionSetting;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Support\CreatesStaff;
use Tests\Support\FakePayPalApi;

uses(CreatesStaff::class);

function enablePayPal(?FakePayPalApi $api = null): FakePayPalApi
{
    app(ExtensionManager::class)->discover();
    $api ??= new FakePayPalApi;
    app()->instance(PayPalApi::class, $api);
    installAndEnableExtension('paypal');
    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('paypal', 'client_id', 'test-client-id-not-real');
    $settings->set('paypal', 'client_secret', 'test-client-secret-not-real', secret: true);
    $settings->set('paypal', 'webhook_id', 'WH-TEST-WEBHOOK-ID');
    $settings->set('paypal', 'sandbox', true);

    return $api;
}

function placePayPalOrder(): Payment
{
    $product = Product::factory()->active()->create(['price_amount' => 2500]);
    app(CartService::class)->add($product->id, 1);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'PayPal Buyer',
        'customer_email' => 'paypal-buyer@example.test',
        'payment_method' => 'paypal:paypal',
        'billing' => AddressData::fromArray([
            'name' => 'PayPal Buyer',
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
    ]);

    return $order->payment()->firstOrFail();
}

/**
 * @param  array<string, mixed>  $resource
 */
function paypalSignedRequest(string $eventType, array $resource, string $eventId = 'WH-TEST-EVT-1'): Request
{
    $event = [
        'id' => $eventId,
        'event_type' => $eventType,
        'resource' => $resource,
    ];

    return Request::create(
        '/webhooks/payments/paypal',
        'POST',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            'HTTP_PAYPAL-CERT-URL' => 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-TEST',
            'HTTP_PAYPAL-TRANSMISSION-ID' => 'transmission-test-1',
            'HTTP_PAYPAL-TRANSMISSION-SIG' => 'signature-test-not-real',
            'HTTP_PAYPAL-TRANSMISSION-TIME' => '2026-08-24T12:00:00Z',
        ],
        json_encode($event, JSON_THROW_ON_ERROR),
    );
}

test('paypal registers only when the extension is enabled', function () {
    expect(app(PaymentGatewayRegistry::class)->has('paypal'))->toBeFalse();

    enablePayPal();

    expect(app(PaymentGatewayRegistry::class)->get('paypal'))->toBeInstanceOf(PayPalPaymentGateway::class)
        ->and(app(AvailablePaymentMethods::class)->ids())->toContain('paypal:paypal');

    app(ExtensionManager::class)->disable('paypal');

    expect(app(PaymentGatewayRegistry::class)->has('paypal'))->toBeFalse();
});

test('paypal credentials are encrypted and never redisplayed', function () {
    enablePayPal();
    $row = ExtensionSetting::query()
        ->where('extension_id', 'paypal')
        ->where('key', 'client_secret')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->is_secret)->toBeTrue()
        ->and($row->value)->not->toContain('test-client-secret-not-real')
        ->and(Crypt::decryptString((string) $row->value))->toBe('test-client-secret-not-real');
});

test('paypal checkout redirects without marking the order paid', function () {
    $api = enablePayPal();
    $payment = placePayPalOrder();

    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'paypal:paypal',
        route('storefront.payment.status', $payment->order),
        route('storefront.payment.status', $payment->order),
        'paypal-start-1',
    );

    expect($attempt->redirect_url)->toStartWith('https://www.sandbox.paypal.com/')
        ->and($attempt->external_id)->toStartWith('5O')
        ->and($attempt->status)->toBe(PaymentAttemptStatus::Processing)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Pending)
        ->and($api->createCalls)->toBe(1);
});

test('verified paypal webhook marks payment paid', function () {
    enablePayPal();
    $payment = placePayPalOrder();
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'paypal:paypal',
        'https://example.test/return',
        'https://example.test/cancel',
        'paypal-webhook-setup',
    );

    app(HandlePaymentWebhook::class)->handle(
        'paypal',
        paypalSignedRequest('PAYMENT.CAPTURE.COMPLETED', [
            'id' => 'CAPTURE_TEST',
            'status' => 'COMPLETED',
            'supplementary_data' => ['related_ids' => ['order_id' => $attempt->external_id]],
        ]),
    );

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and(PaymentWebhookEvent::query()->count())->toBe(1);
});

test('invalid paypal webhook signatures are rejected', function () {
    $api = enablePayPal();
    $api->verifyWebhook = false;

    $request = paypalSignedRequest('PAYMENT.CAPTURE.COMPLETED', ['id' => 'CAPTURE_TEST']);

    expect(fn () => app(HandlePaymentWebhook::class)->handle('paypal', $request))
        ->toThrow(AccessDeniedHttpException::class);
});

test('disabled paypal is unavailable at checkout', function () {
    enablePayPal();
    app(ExtensionManager::class)->disable('paypal');

    $product = Product::factory()->active()->create(['price_amount' => 1000]);
    app(CartService::class)->add($product->id, 1);

    expect(fn () => app(PlaceOrder::class)->handle([
        'customer_name' => 'No Gateway',
        'customer_email' => 'none@example.test',
        'payment_method' => 'paypal:paypal',
        'billing' => AddressData::fromArray([
            'name' => 'No Gateway',
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
    ]))->toThrow(ValidationException::class);
});

test('paypal health check validates credentials without exposing secrets', function () {
    enablePayPal();

    $health = app(PayPalPaymentGateway::class)->health();

    expect($health->ok)->toBeTrue()
        ->and($health->message)->not->toContain('test-client-secret-not-real');
});
