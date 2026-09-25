<?php

declare(strict_types=1);

use Agovena\Extensions\PayPal\PayPalApi;
use Agovena\Extensions\PayPal\PayPalPaymentAuthorization;
use Agovena\Extensions\PayPal\PayPalPaymentGateway;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Payments\AvailablePaymentMethods;
use App\Agovena\Payments\HandlePaymentWebhook;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Payments\RecordRefund;
use App\Agovena\Payments\StartOrderPayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Livewire\Admin\Extensions\Index;
use App\Models\ExtensionSetting;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
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

function placePayPalSubscriptionOrder(): Payment
{
    $product = Product::factory()->active()->create(['price_amount' => 2500, 'currency' => 'EUR']);
    app(ProductCapabilityManager::class)->enable($product, 'subscribable', [
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);
    app(CartService::class)->add($product->id, 1);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'PayPal Subscriber',
        'customer_email' => 'paypal-subscriber@example.test',
        'payment_method' => 'paypal:paypal',
        'custom_properties' => ['_agovena_renewal_mode' => 'automatic'],
        'billing' => AddressData::fromArray([
            'name' => 'PayPal Subscriber',
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

    $gateway = app(PaymentGatewayRegistry::class)->get('paypal');
    expect($gateway)->toBeInstanceOf(PayPalPaymentGateway::class)
        ->and(app(AvailablePaymentMethods::class)->ids())->toContain('paypal:paypal')
        ->and($gateway->capabilities()->recurring)->toBeTrue()
        ->and($gateway->checkoutMethods()[0]->icon)->toBe('ag:payment-method/paypal');

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

test('paypal automatic checkout stores a reusable vault authorization after capture', function () {
    $api = enablePayPal();
    $payment = placePayPalSubscriptionOrder();

    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'paypal:paypal',
        'https://example.test/return',
        'https://example.test/cancel',
        'paypal-vault-setup-1',
    );

    expect($attempt->redirect_url)->toStartWith('https://www.sandbox.paypal.com/')
        ->and($api->orderPayloads[$attempt->external_id]['payment_source']['paypal']['attributes']['vault']['store_in_vault'] ?? null)->toBe('ON_SUCCESS')
        ->and($api->orderPayloads[$attempt->external_id]['payment_source']['paypal']['attributes']['vault']['usage_type'] ?? null)->toBe('MERCHANT')
        ->and($api->orderPayloads[$attempt->external_id]['payment_source']['paypal']['attributes']['vault']['usage_pattern'] ?? null)->toBe('SUBSCRIPTION_PREPAID');

    app(HandlePaymentWebhook::class)->handle(
        'paypal',
        paypalSignedRequest('CHECKOUT.ORDER.APPROVED', [
            'id' => (string) $attempt->external_id,
            'status' => 'APPROVED',
        ], 'WH-TEST-EVT-VAULT'),
    );

    $authorization = PayPalPaymentAuthorization::query()->first();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($api->captureCalls)->toBe(1)
        ->and($authorization)->not->toBeNull()
        ->and($authorization->status)->toBe('active')
        ->and($authorization->payment_token_id)->toBe('VAULT_'.$attempt->external_id)
        ->and($authorization->payment_token_hash)->not->toBeNull();
});

test('paypal recurring charges use the stored vault authorization without a redirect', function () {
    $api = enablePayPal();
    $firstPayment = placePayPalSubscriptionOrder();
    $firstAttempt = app(StartOrderPayment::class)->handle(
        $firstPayment->order,
        'paypal:paypal',
        'https://example.test/return',
        'https://example.test/cancel',
        'paypal-vault-first-payment',
    );
    app(HandlePaymentWebhook::class)->handle(
        'paypal',
        paypalSignedRequest('CHECKOUT.ORDER.APPROVED', [
            'id' => (string) $firstAttempt->external_id,
            'status' => 'APPROVED',
        ], 'WH-TEST-EVT-VAULT-FIRST'),
    );

    $renewalPayment = placePayPalSubscriptionOrder();
    $renewalAttempt = app(StartOrderPayment::class)->handle(
        $renewalPayment->order,
        'paypal:paypal',
        'https://example.test/return',
        'https://example.test/cancel',
        'paypal-vault-renewal',
    );

    $payload = $api->orderPayloads[$renewalAttempt->external_id];
    expect($renewalAttempt->status)->toBe(PaymentAttemptStatus::Succeeded)
        ->and($renewalPayment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payload['payment_source']['paypal']['vault_id'] ?? null)->toBe('VAULT_'.$firstAttempt->external_id)
        ->and($payload['payment_source']['paypal']['stored_credential']['payment_initiator'] ?? null)->toBe('MERCHANT')
        ->and($payload['payment_source']['paypal']['stored_credential']['payment_type'] ?? null)->toBe('RECURRING')
        ->and($payload['payment_source']['paypal']['stored_credential']['usage'] ?? null)->toBe('SUBSEQUENT')
        ->and($api->createCalls)->toBe(2)
        ->and($api->captureCalls)->toBe(2);
});

test('paypal transport uncertainty requires payment reconciliation', function () {
    $api = enablePayPal();
    $api->unknownCreate = true;
    $payment = placePayPalOrder();

    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'paypal:paypal',
        'https://example.test/return',
        'https://example.test/cancel',
        'paypal-unknown-initiation-1',
    );

    expect($attempt->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($payment->fresh()->reconciliation_status)->toBe('manual_review')
        ->and($payment->fresh()->reconciliation_meta['reason'] ?? null)->toBe('provider_initiation_outcome_unknown');
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

test('paypal approved webhook captures the order before marking it paid', function () {
    $api = enablePayPal();
    $payment = placePayPalOrder();
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'paypal:paypal',
        'https://example.test/return',
        'https://example.test/cancel',
        'paypal-approved-capture',
    );

    app(HandlePaymentWebhook::class)->handle(
        'paypal',
        paypalSignedRequest('CHECKOUT.ORDER.APPROVED', [
            'id' => (string) $attempt->external_id,
            'status' => 'APPROVED',
        ], 'WH-TEST-EVT-APPROVED'),
    );

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($api->captureCalls)->toBe(1)
        ->and($api->captureIdempotencyKeys)->toBe(['WH-TEST-EVT-APPROVED']);
});

test('paypal recurring charges can be refunded through the capture endpoint', function () {
    $api = enablePayPal();
    $firstPayment = placePayPalSubscriptionOrder();
    $firstAttempt = app(StartOrderPayment::class)->handle(
        $firstPayment->order,
        'paypal:paypal',
        'https://example.test/return',
        'https://example.test/cancel',
        'paypal-refund-vault-first',
    );
    app(HandlePaymentWebhook::class)->handle(
        'paypal',
        paypalSignedRequest('CHECKOUT.ORDER.APPROVED', [
            'id' => (string) $firstAttempt->external_id,
            'status' => 'APPROVED',
        ], 'WH-TEST-EVT-REFUND-FIRST'),
    );

    $renewalPayment = placePayPalSubscriptionOrder();
    $renewalAttempt = app(StartOrderPayment::class)->handle(
        $renewalPayment->order,
        'paypal:paypal',
        'https://example.test/return',
        'https://example.test/cancel',
        'paypal-refund-vault-renewal',
    );

    $refund = app(RecordRefund::class)->handle($renewalPayment->fresh(), $this->createStaff(), 1000, 'Recurring refund');

    expect($renewalAttempt->status)->toBe(PaymentAttemptStatus::Succeeded)
        ->and($refund->status)->toBe(RefundStatus::Completed)
        ->and($refund->provider_reference)->toBe('REFUND_CAPTURE_'.$renewalAttempt->external_id)
        ->and($api->refundCalls)->toBe(1)
        ->and($api->saleRefundCalls)->toBe(0);
});

test('paypal vault deletion webhooks revoke the reusable authorization', function () {
    $api = enablePayPal();
    $payment = placePayPalSubscriptionOrder();
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'paypal:paypal',
        'https://example.test/return',
        'https://example.test/cancel',
        'paypal-vault-delete-setup',
    );
    app(HandlePaymentWebhook::class)->handle(
        'paypal',
        paypalSignedRequest('CHECKOUT.ORDER.APPROVED', [
            'id' => (string) $attempt->external_id,
            'status' => 'APPROVED',
        ], 'WH-TEST-EVT-DELETE-SETUP'),
    );

    app(HandlePaymentWebhook::class)->handle(
        'paypal',
        paypalSignedRequest('VAULT.PAYMENT-TOKEN.DELETED', [
            'id' => 'VAULT_'.$attempt->external_id,
        ], 'WH-TEST-EVT-DELETE'),
    );

    $authorization = PayPalPaymentAuthorization::query()->first();
    expect($authorization)->not->toBeNull()
        ->and($authorization->status)->toBe('revoked')
        ->and($api->createCalls)->toBe(1);
});

test('malformed paypal refund responses stay pending for reconciliation', function () {
    $api = enablePayPal();
    $payment = placePayPalOrder();
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'paypal:paypal',
        'https://example.test/return',
        'https://example.test/cancel',
        'paypal-malformed-refund-1',
    );
    $api->captureOrder((string) $attempt->external_id);
    app(HandlePaymentWebhook::class)->handle(
        'paypal',
        paypalSignedRequest('PAYMENT.CAPTURE.COMPLETED', [
            'id' => 'CAPTURE_'.(string) $attempt->external_id,
            'status' => 'COMPLETED',
            'supplementary_data' => ['related_ids' => ['order_id' => $attempt->external_id]],
        ], 'WH-TEST-EVT-REFUND'),
    );
    $api->malformedRefund = true;

    $refund = app(RecordRefund::class)->handle($payment->fresh(), $this->createStaff(), $payment->amount, 'Malformed response');

    expect($refund->status)->toBe(RefundStatus::Pending)
        ->and($payment->fresh()->reconciliation_status)->toBe('manual_review')
        ->and($payment->fresh()->reconciliation_meta['reason'] ?? null)->toBe('provider_refund_outcome_unknown');
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

test('paypal settings automatically checks the connection without method discovery', function () {
    $api = enablePayPal();
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->call('openSettings', 'paypal')
        ->assertSet('settingsMethodsLoaded', false)
        ->assertSet('settingsMethodOptions', [])
        ->assertSet('settingsConnectionState', 'success')
        ->assertSet('settingsConnectionMessage', __('admin.extensions.settings_connection_ok_without_methods'))
        ->assertDontSee('Refresh payment methods');

    expect($api->pingCalls)->toBe(1);
});

test('paypal settings automatically checks after the final credential is entered', function () {
    app(ExtensionManager::class)->discover();
    $api = new FakePayPalApi;
    app()->instance(PayPalApi::class, $api);
    installAndEnableExtension('paypal');
    $settings = app(ExtensionSettingsRepository::class);
    $settings->forget('paypal', 'client_id');
    $settings->forget('paypal', 'client_secret');
    $settings->forget('paypal', 'webhook_id');
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->call('openSettings', 'paypal')
        ->set('settingsForm.client_id', 'test-client-id-not-real')
        ->set('settingsForm.webhook_id', 'WH-TEST-WEBHOOK-ID')
        ->set('settingsForm.client_secret', 'test-client-secret-not-real')
        ->assertSet('settingsConnectionState', 'success')
        ->assertSet('settingsConnectionMessage', __('admin.extensions.settings_connection_ok_without_methods'));

    expect($api->pingCalls)->toBe(1)
        ->and($settings->isConfigured('paypal', 'client_secret'))->toBeFalse();
});

test('paypal settings reports a failed connection without persisting entered credentials', function () {
    app(ExtensionManager::class)->discover();
    $api = new FakePayPalApi;
    $api->unauthorized = true;
    app()->instance(PayPalApi::class, $api);
    installAndEnableExtension('paypal');
    $settings = app(ExtensionSettingsRepository::class);
    $settings->forget('paypal', 'client_id');
    $settings->forget('paypal', 'client_secret');
    $settings->forget('paypal', 'webhook_id');
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->call('openSettings', 'paypal')
        ->set('settingsForm.client_id', 'test-client-id-not-real')
        ->set('settingsForm.webhook_id', 'WH-TEST-WEBHOOK-ID')
        ->set('settingsForm.client_secret', 'test-client-secret-not-real')
        ->assertSet('settingsConnectionState', 'error')
        ->assertSet('settingsMethodOptions', []);

    expect($api->pingCalls)->toBe(1)
        ->and($settings->isConfigured('paypal', 'client_secret'))->toBeFalse();
});
