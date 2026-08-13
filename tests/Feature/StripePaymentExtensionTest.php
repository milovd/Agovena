<?php

declare(strict_types=1);

use Agovena\Extensions\Stripe\StripeApi;
use Agovena\Extensions\Stripe\StripePaymentAuthorization;
use Agovena\Extensions\Stripe\StripePaymentGateway;
use Agovena\Modules\Subscriptions\Enums\RenewalStatus;
use Agovena\Modules\Subscriptions\Enums\SubscriptionStatus;
use Agovena\Modules\Subscriptions\Models\Subscription;
use Agovena\Modules\Subscriptions\Models\SubscriptionRenewal;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Payments\AvailablePaymentMethods;
use App\Agovena\Payments\HandlePaymentWebhook;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Payments\ReconcilePaymentStatus;
use App\Agovena\Payments\RecordRefund;
use App\Agovena\Payments\StartOrderPayment;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Livewire\Storefront\PaymentStatusPage;
use App\Models\Customer;
use App\Models\ExtensionSetting;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Support\CreatesStaff;
use Tests\Support\FakeStripeApi;

uses(CreatesStaff::class);

const STRIPE_WEBHOOK_SECRET = 'whsec_test_secret_not_real';

function enableStripe(?FakeStripeApi $api = null): FakeStripeApi
{
    $api ??= new FakeStripeApi;
    app()->instance(StripeApi::class, $api);
    app(ExtensionManager::class)->enable('stripe');
    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('stripe', 'secret_key', 'sk_test_abcdefghijklmnopqrstuvwxyz123456', secret: true);
    $settings->set('stripe', 'webhook_secret', STRIPE_WEBHOOK_SECRET, secret: true);

    return $api;
}

function stripeBilling(): AddressData
{
    return AddressData::fromArray([
        'name' => 'Stripe Buyer',
        'line1' => 'Street 1',
        'city' => 'Amsterdam',
        'postal_code' => '1000 AA',
        'country' => 'NL',
    ]);
}

function placeStripeOrder(?Customer $customer = null): Payment
{
    $product = Product::factory()->active()->create(['price_amount' => 2500]);
    app(CartService::class)->add($product->id, 1);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name ?? 'Stripe Buyer',
        'customer_email' => $customer->email ?? 'stripe-buyer@example.test',
        'customer_id' => $customer?->id,
        'payment_method' => 'stripe:card',
        'billing' => stripeBilling(),
    ]);

    return $order->payment()->firstOrFail();
}

/**
 * @param  array<string, mixed>  $object
 */
function stripeSignedRequest(string $type, array $object, string $eventId = 'evt_test_1'): Request
{
    $event = [
        'id' => $eventId,
        'object' => 'event',
        'type' => $type,
        'data' => ['object' => $object],
    ];
    $payload = json_encode($event, JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, STRIPE_WEBHOOK_SECRET);

    return Request::create(
        '/webhooks/payments/stripe',
        'POST',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ],
        content: $payload,
    );
}

test('stripe registers only when the extension is enabled', function () {
    expect(app(PaymentGatewayRegistry::class)->has('stripe'))->toBeFalse();

    enableStripe();

    expect(app(PaymentGatewayRegistry::class)->get('stripe'))->toBeInstanceOf(StripePaymentGateway::class)
        ->and(app(AvailablePaymentMethods::class)->ids())->toContain('stripe:card');

    app(ExtensionManager::class)->disable('stripe');

    expect(app(PaymentGatewayRegistry::class)->has('stripe'))->toBeFalse()
        ->and(app(AvailablePaymentMethods::class)->ids())->not->toContain('stripe:card');
});

test('stripe credentials are encrypted and never redisplayed', function () {
    enableStripe();
    $row = ExtensionSetting::query()
        ->where('extension_id', 'stripe')
        ->where('key', 'secret_key')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->is_secret)->toBeTrue()
        ->and($row->value)->not->toContain('sk_test_abcdefghijklmnopqrstuvwxyz123456')
        ->and(Crypt::decryptString((string) $row->value))->toBe('sk_test_abcdefghijklmnopqrstuvwxyz123456');
});

test('stripe checkout redirects without marking the order paid', function () {
    $api = enableStripe();
    $payment = placeStripeOrder();

    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'stripe:card',
        route('storefront.payment.status', $payment->order),
        route('storefront.payment.status', $payment->order),
        'stripe-start-1',
    );

    expect($attempt->redirect_url)->toStartWith('https://checkout.stripe.test/')
        ->and($attempt->external_id)->toStartWith('pi_test_')
        ->and($attempt->status)->toBe(PaymentAttemptStatus::Processing)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Pending)
        ->and($api->checkoutCalls)->toBe(1);
});

test('stripe initiate is idempotent for retries', function () {
    $api = enableStripe();
    $payment = placeStripeOrder();

    $first = app(StartOrderPayment::class)->handle(
        $payment->order,
        'stripe:card',
        'https://example.test/return',
        'https://example.test/cancel',
        'stripe-idem-1',
    );
    $second = app(StartOrderPayment::class)->handle(
        $payment->order,
        'stripe:card',
        'https://example.test/return',
        'https://example.test/cancel',
        'stripe-idem-1',
    );

    expect($second->id)->toBe($first->id)
        ->and(PaymentAttempt::query()->count())->toBe(1)
        ->and($api->checkoutCalls)->toBe(1);
});

test('stripe return url does not mark the order paid', function () {
    enableStripe();
    $payment = placeStripeOrder();
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'stripe:card',
        route('storefront.payment.status', $payment->order),
        route('storefront.payment.status', $payment->order),
    );

    Livewire::test(PaymentStatusPage::class, ['order' => $payment->order])
        ->assertOk()
        ->assertSee(__('storefront.payment_status.title.pending'));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Processing);
});

test('signed stripe webhook paid confirms order and stores reusable authorization', function () {
    $api = enableStripe();
    $payment = placeStripeOrder();
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'stripe:card',
        'https://example.test/return',
        'https://example.test/cancel',
    );
    $api->markPaid((string) $attempt->external_id);
    $session = $api->sessionForIntent((string) $attempt->external_id);
    expect($session)->not->toBeNull();

    $result = app(HandlePaymentWebhook::class)->handle(
        'stripe',
        stripeSignedRequest('checkout.session.completed', $session, 'evt_paid_1'),
    );

    expect($result->duplicate)->toBeFalse()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Paid)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Succeeded)
        ->and(StripePaymentAuthorization::query()->where('customer_email', 'stripe-buyer@example.test')->value('payment_method_id'))
        ->toBe('pm_test');
});

test('invalid stripe webhook signatures are rejected', function () {
    enableStripe();
    $request = Request::create(
        '/webhooks/payments/stripe',
        'POST',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't=1,v1=not-a-valid-signature',
        ],
        content: '{"id":"evt_bad","type":"payment_intent.succeeded","data":{"object":{}}}',
    );

    expect(fn () => app(HandlePaymentWebhook::class)->handle('stripe', $request))
        ->toThrow(AccessDeniedHttpException::class);
});

test('duplicate stripe webhook events are harmless', function () {
    $api = enableStripe();
    $payment = placeStripeOrder();
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'stripe:card',
        'https://example.test/return',
        'https://example.test/cancel',
    );
    $api->markPaid((string) $attempt->external_id);
    $session = $api->sessionForIntent((string) $attempt->external_id);
    $request = stripeSignedRequest('checkout.session.completed', $session, 'evt_dup_1');

    $first = app(HandlePaymentWebhook::class)->handle('stripe', $request);
    $second = app(HandlePaymentWebhook::class)->handle('stripe', $request);

    expect($first->duplicate)->toBeFalse()
        ->and($second->duplicate)->toBeTrue()
        ->and(PaymentWebhookEvent::query()->count())->toBe(1)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

test('stripe payment_intent.payment_failed maps to a failed attempt', function () {
    $api = enableStripe();
    $payment = placeStripeOrder();
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'stripe:card',
        'https://example.test/return',
        'https://example.test/cancel',
    );
    $api->markStatus((string) $attempt->external_id, 'requires_payment_method');
    $intent = $api->retrievePaymentIntent((string) $attempt->external_id);

    app(HandlePaymentWebhook::class)->handle(
        'stripe',
        stripeSignedRequest('payment_intent.payment_failed', $intent, 'evt_fail_1'),
    );

    expect($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Pending);
});

test('status sync can confirm a paid stripe payment after webhook delay', function () {
    $api = enableStripe();
    $payment = placeStripeOrder();
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'stripe:card',
        'https://example.test/return',
        'https://example.test/cancel',
    );
    $api->markPaid((string) $attempt->external_id);

    $synced = app(ReconcilePaymentStatus::class)->handle($payment);

    expect($synced->status)->toBe(PaymentStatus::Paid)
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Paid);
});

test('stripe full and partial refunds are idempotent', function () {
    $api = enableStripe();
    $staff = $this->createStaff();
    $payment = placeStripeOrder();
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'stripe:card',
        'https://example.test/return',
        'https://example.test/cancel',
    );
    $api->markPaid((string) $attempt->external_id);
    $session = $api->sessionForIntent((string) $attempt->external_id);
    app(HandlePaymentWebhook::class)->handle(
        'stripe',
        stripeSignedRequest('checkout.session.completed', $session, 'evt_refund_setup'),
    );

    $partial = app(RecordRefund::class)->handle($payment->fresh(), $staff, 1000, 'partial');
    expect($partial->status)->toBe(RefundStatus::Completed)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($api->refundCalls)->toBe(1);

    $full = app(RecordRefund::class)->handle($payment->fresh(), $staff, 1500, 'remainder');
    expect($full->status)->toBe(RefundStatus::Completed)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
});

test('stripe provider failures stay as safe agovena failures', function () {
    $api = enableStripe();
    $api->failCreate = true;
    $payment = placeStripeOrder();

    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'stripe:card',
        'https://example.test/return',
        'https://example.test/cancel',
        'stripe-fail-1',
    );

    expect($attempt->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

test('stripe network timeout does not leak secrets into logs', function () {
    $api = enableStripe();
    $api->timeout = true;
    $payment = placeStripeOrder();

    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'stripe:card',
        'https://example.test/return',
        'https://example.test/cancel',
        'stripe-timeout-1',
    );

    expect($attempt->status)->toBe(PaymentAttemptStatus::Failed)
        ->and(json_encode($attempt->response_meta))->not->toContain('sk_test_abcdefghijklmnopqrstuvwxyz123456')
        ->and(json_encode($attempt->response_meta))->not->toContain(STRIPE_WEBHOOK_SECRET);
});

test('malformed stripe checkout response fails the attempt', function () {
    $api = enableStripe();
    $api->malformed = true;
    $payment = placeStripeOrder();

    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'stripe:card',
        'https://example.test/return',
        'https://example.test/cancel',
        'stripe-malformed-1',
    );

    expect($attempt->status)->toBe(PaymentAttemptStatus::Failed);
});

test('stripe health check validates credentials without exposing the key', function () {
    $api = enableStripe();
    $result = app(StripePaymentGateway::class)->health();

    expect($result->ok)->toBeTrue()
        ->and($result->message)->toContain('test')
        ->and($result->message)->not->toContain('sk_test_abcdefghijklmnopqrstuvwxyz123456')
        ->and($api->balanceCalls)->toBe(1);
});

test('manual and development gateways still work with stripe installed', function () {
    enableStripe();
    config(['agovena.payments.allow_development_instant_pay' => true]);
    app(ExtensionManager::class)->enable('manual-payment');

    $ids = app(AvailablePaymentMethods::class)->ids();

    expect($ids)->toContain('manual')
        ->and($ids)->toContain('development')
        ->and($ids)->toContain('stripe:card');
});

test('subscription renewal auto-charges through stripe without module knowing stripe types', function () {
    $api = enableStripe();
    app(ModuleManager::class)->enable('subscriptions');
    app(SyncRegisteredPermissions::class)(force: true);

    $customer = Customer::factory()->create([
        'email' => 'stripe-renew@example.test',
        'name' => 'Stripe Renew',
    ]);
    $product = Product::factory()->active()->create(['price_amount' => 1999]);
    app(ProductCapabilityManager::class)->enable($product, 'subscribable', [
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'payment_method' => 'stripe:card',
        'billing' => stripeBilling(),
    ]);
    $attempt = app(StartOrderPayment::class)->handle(
        $order,
        'stripe:card',
        'https://example.test/return',
        'https://example.test/cancel',
    );
    $api->markPaid((string) $attempt->external_id);
    $session = $api->sessionForIntent((string) $attempt->external_id);
    app(HandlePaymentWebhook::class)->handle(
        'stripe',
        stripeSignedRequest('checkout.session.completed', $session, 'evt_sub_paid'),
    );

    $subscription = Subscription::query()->where('order_id', $order->id)->firstOrFail();
    expect($subscription->payment_gateway)->toBe('stripe');

    $api->nextIntentStatus = 'succeeded';
    $this->travelTo(CarbonImmutable::parse($subscription->next_billing_at));
    $this->artisan('agovena:process-subscription-renewals')->assertSuccessful();

    $renewal = SubscriptionRenewal::query()->firstOrFail();
    expect($renewal->status)->toBe(RenewalStatus::Paid)
        ->and($renewal->order->payment->status)->toBe(PaymentStatus::Paid)
        ->and($renewal->order->payment->method)->toBe('stripe')
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($api->intentCalls)->toBe(1);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('modules/subscriptions'), FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        expect((string) file_get_contents($file->getPathname()))
            ->not->toContain('Agovena\\Extensions\\Stripe\\')
            ->not->toContain('Stripe\\');
    }
});

test('core payment files do not import stripe sdk types', function () {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $contents = (string) file_get_contents($file->getPathname());
        expect($contents)
            ->not->toContain('Stripe\\StripeClient')
            ->not->toContain('Stripe\\Webhook')
            ->not->toContain('Agovena\\Extensions\\Stripe\\');
    }
});
