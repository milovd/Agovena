<?php

declare(strict_types=1);

use Agovena\Extensions\Mollie\MollieApi;
use Agovena\Extensions\Mollie\MollieMandate;
use Agovena\Extensions\Mollie\MolliePaymentGateway;
use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Orders\StorefrontOrderAccess;
use App\Agovena\Payments\AvailablePaymentMethods;
use App\Agovena\Payments\Gateways\DevelopmentPaymentGateway;
use App\Agovena\Payments\HandlePaymentWebhook;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Payments\ReconcilePaymentStatus;
use App\Agovena\Payments\RecordRefund;
use App\Agovena\Payments\StartOrderPayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Livewire\Admin\Extensions\Index;
use App\Livewire\Storefront\PaymentStatusPage;
use App\Models\ExtensionSetting;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\Support\FakeMollieApi;

uses(CreatesStaff::class);

function enableMollie(?FakeMollieApi $api = null): FakeMollieApi
{
    app(ExtensionManager::class)->discover();
    $api ??= new FakeMollieApi;
    app()->instance(MollieApi::class, $api);
    installAndEnableExtension('mollie');
    app(ExtensionSettingsRepository::class)->set('mollie', 'api_key', 'test_abcdefghijklmnopqrstuvwxyz123456', secret: true);

    return $api;
}

function placeMollieOrder(): Payment
{
    $product = Product::factory()->active()->create(['price_amount' => 2500]);
    app(CartService::class)->add($product->id, 1);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'Mollie Buyer',
        'customer_email' => 'mollie-buyer@example.test',
        'payment_method' => 'mollie',
        'billing' => AddressData::fromArray([
            'name' => 'Mollie Buyer',
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
    ]);

    return $order->payment()->firstOrFail();
}

test('mollie registers only when the extension is enabled', function () {
    expect(app(PaymentGatewayRegistry::class)->has('mollie'))->toBeFalse();

    enableMollie();

    expect(app(PaymentGatewayRegistry::class)->get('mollie'))->toBeInstanceOf(MolliePaymentGateway::class)
        ->and(app(AvailablePaymentMethods::class)->ids())->toContain('mollie');

    app(ExtensionManager::class)->disable('mollie');

    expect(app(PaymentGatewayRegistry::class)->has('mollie'))->toBeFalse()
        ->and(app(AvailablePaymentMethods::class)->ids())->not->toContain('mollie');
});

test('mollie credentials are encrypted and never redisplayed', function () {
    enableMollie();
    $settings = app(ExtensionSettingsRepository::class);
    $row = ExtensionSetting::query()
        ->where('extension_id', 'mollie')
        ->where('key', 'api_key')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->is_secret)->toBeTrue()
        ->and($row->value)->not->toContain('test_abcdefghijklmnopqrstuvwxyz123456')
        ->and(Crypt::decryptString((string) $row->value))->toBe('test_abcdefghijklmnopqrstuvwxyz123456')
        ->and($settings->isConfigured('mollie', 'api_key'))->toBeTrue();
});

test('disabled mollie is unavailable at checkout', function () {
    enableMollie();
    app(ExtensionManager::class)->disable('mollie');

    $product = Product::factory()->active()->create(['price_amount' => 1000]);
    app(CartService::class)->add($product->id, 1);

    expect(fn () => app(PlaceOrder::class)->handle([
        'customer_name' => 'No Gateway',
        'customer_email' => 'none@example.test',
        'payment_method' => 'mollie',
        'billing' => AddressData::fromArray([
            'name' => 'No Gateway',
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
    ]))->toThrow(ValidationException::class);
});

test('mollie create payment redirects without marking the order paid', function () {
    $api = enableMollie();
    $payment = placeMollieOrder();

    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'mollie',
        route('storefront.payment.status', $payment->order),
        route('storefront.payment.status', $payment->order),
        'mollie-start-1',
    );

    expect($attempt->redirect_url)->toStartWith('https://mollie.test/checkout/')
        ->and($attempt->external_id)->toStartWith('tr_')
        ->and($attempt->status)->toBe(PaymentAttemptStatus::Processing)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Pending)
        ->and($api->createCalls)->toBe(1);
});

test('mollie initiate is idempotent for retries and double clicks', function () {
    $api = enableMollie();
    $payment = placeMollieOrder();

    $first = app(StartOrderPayment::class)->handle(
        $payment->order,
        'mollie',
        'https://example.test/return',
        'https://example.test/cancel',
        'mollie-idem-1',
    );
    $second = app(StartOrderPayment::class)->handle(
        $payment->order,
        'mollie',
        'https://example.test/return',
        'https://example.test/cancel',
        'mollie-idem-1',
    );

    expect($second->id)->toBe($first->id)
        ->and(PaymentAttempt::query()->count())->toBe(1)
        ->and($api->createCalls)->toBe(1);
});

test('return url does not mark the order paid', function () {
    enableMollie();
    $payment = placeMollieOrder();
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'mollie',
        route('storefront.payment.status', $payment->order),
        route('storefront.payment.status', $payment->order),
    );

    app(StorefrontOrderAccess::class)->remember($payment->order);

    Livewire::test(PaymentStatusPage::class, ['order' => $payment->order])
        ->assertOk()
        ->assertSee(__('storefront.payment_status.title.pending'));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Processing);
});

test('mollie webhook paid confirms order and invoice', function () {
    $api = enableMollie();
    $payment = placeMollieOrder();
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'mollie',
        'https://example.test/return',
        'https://example.test/cancel',
    );
    $api->markPaid($attempt->external_id);

    $request = Request::create('/webhooks/payments/mollie', 'POST', ['id' => $attempt->external_id]);
    $result = app(HandlePaymentWebhook::class)->handle('mollie', $request);

    expect($result->duplicate)->toBeFalse()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Paid)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Succeeded);
});

test('duplicate mollie webhook is harmless', function () {
    $api = enableMollie();
    $payment = placeMollieOrder();
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'mollie',
        'https://example.test/return',
        'https://example.test/cancel',
    );
    $api->markPaid($attempt->external_id);
    $request = Request::create('/webhooks/payments/mollie', 'POST', ['id' => $attempt->external_id]);

    $first = app(HandlePaymentWebhook::class)->handle('mollie', $request);
    $second = app(HandlePaymentWebhook::class)->handle('mollie', $request);

    expect($first->duplicate)->toBeFalse()
        ->and($second->duplicate)->toBeTrue()
        ->and(PaymentWebhookEvent::query()->count())->toBe(1)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

test('mollie webhook failed cancelled and expired map to normalized states', function (string $provider, PaymentAttemptStatus $expected) {
    $api = enableMollie();
    $payment = placeMollieOrder();
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'mollie',
        'https://example.test/return',
        'https://example.test/cancel',
    );
    $api->markStatus($attempt->external_id, $provider);

    app(HandlePaymentWebhook::class)->handle(
        'mollie',
        Request::create('/webhooks/payments/mollie', 'POST', ['id' => $attempt->external_id]),
    );

    expect($attempt->fresh()->status)->toBe($expected)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Pending);
})->with([
    ['failed', PaymentAttemptStatus::Failed],
    ['canceled', PaymentAttemptStatus::Cancelled],
    ['expired', PaymentAttemptStatus::Expired],
]);

test('status sync can confirm a paid mollie payment after webhook delay', function () {
    $api = enableMollie();
    $payment = placeMollieOrder();
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'mollie',
        'https://example.test/return',
        'https://example.test/cancel',
    );
    $api->markPaid($attempt->external_id);

    $synced = app(ReconcilePaymentStatus::class)->handle($payment);

    expect($synced->status)->toBe(PaymentStatus::Paid)
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Paid);
});

test('mollie full and partial refunds are idempotent', function () {
    $api = enableMollie();
    $staff = $this->createStaff();
    $payment = placeMollieOrder();
    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'mollie',
        'https://example.test/return',
        'https://example.test/cancel',
    );
    $api->markPaid($attempt->external_id);
    app(HandlePaymentWebhook::class)->handle(
        'mollie',
        Request::create('/webhooks/payments/mollie', 'POST', ['id' => $attempt->external_id]),
    );

    $partial = app(RecordRefund::class)->handle($payment->fresh(), $staff, 1000, 'partial');
    expect($partial->status)->toBe(RefundStatus::Completed)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($api->refundCalls)->toBe(1);

    $full = app(RecordRefund::class)->handle($payment->fresh(), $staff, 1500, 'remainder');
    expect($full->status)->toBe(RefundStatus::Completed)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
});

test('mollie provider failures stay as safe agovena failures', function () {
    $api = enableMollie();
    $api->failCreate = true;
    $payment = placeMollieOrder();

    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'mollie',
        'https://example.test/return',
        'https://example.test/cancel',
        'mollie-fail-1',
    );

    expect($attempt->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->order->status)->toBe(OrderStatus::Pending);
});

test('mollie network timeout does not leak secrets into logs', function () {
    $api = enableMollie();
    $api->timeout = true;
    $payment = placeMollieOrder();

    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'mollie',
        'https://example.test/return',
        'https://example.test/cancel',
        'mollie-timeout-1',
    );

    expect($attempt->status)->toBe(PaymentAttemptStatus::Failed)
        ->and(json_encode($attempt->response_meta))->not->toContain('test_abcdefghijklmnopqrstuvwxyz123456');
});

test('malformed mollie checkout response fails the attempt', function () {
    $api = enableMollie();
    $api->malformed = true;
    $payment = placeMollieOrder();

    $attempt = app(StartOrderPayment::class)->handle(
        $payment->order,
        'mollie',
        'https://example.test/return',
        'https://example.test/cancel',
        'mollie-malformed-1',
    );

    expect($attempt->status)->toBe(PaymentAttemptStatus::Failed);
});

test('mollie unauthorized and server errors fail safely without leaking secrets', function () {
    $secret = 'test_abcdefghijklmnopqrstuvwxyz123456';

    foreach (['unauthorized', 'serverError'] as $mode) {
        $api = enableMollie();
        $api->{$mode} = true;
        $payment = placeMollieOrder();

        $attempt = app(StartOrderPayment::class)->handle(
            $payment->order,
            'mollie',
            'https://example.test/return',
            'https://example.test/cancel',
            'mollie-'.$mode.'-1',
        );

        expect($attempt->status)->toBe(PaymentAttemptStatus::Failed)
            ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending)
            ->and(json_encode($attempt->response_meta))->not->toContain($secret);
    }
});

test('mollie secret is not rendered in the extensions settings UI', function () {
    enableMollie();
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->call('openSettings', 'mollie')
        ->assertDontSee('test_abcdefghijklmnopqrstuvwxyz123456')
        ->assertSet('settingsForm.api_key', '');
});

test('mollie health check validates credentials without exposing the key', function () {
    $api = enableMollie();
    $result = app(MolliePaymentGateway::class)->health();

    expect($result->ok)->toBeTrue()
        ->and($result->message)->toContain('test')
        ->and($result->message)->not->toContain('test_abcdefghijklmnopqrstuvwxyz123456')
        ->and($api->methods)->not->toBeEmpty();
});

test('development gateway is not offered at checkout alongside mollie', function () {
    enableMollie();
    config(['agovena.payments.allow_development_instant_pay' => true]);
    app(PaymentGatewayRegistry::class)->register(app(DevelopmentPaymentGateway::class));

    $ids = app(AvailablePaymentMethods::class)->ids();

    expect($ids)->toContain('mollie')
        ->and($ids)->not->toContain('development');
});

test('mollie can store a mandate mapping without core customer columns', function () {
    enableMollie();
    $payment = placeMollieOrder();
    app(StartOrderPayment::class)->handle(
        $payment->order,
        'mollie',
        'https://example.test/return',
        'https://example.test/cancel',
    );

    expect(MollieMandate::query()->where('customer_email', 'mollie-buyer@example.test')->exists())->toBeTrue();
});

test('core payment files do not import the mollie sdk', function () {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $contents = (string) file_get_contents($file->getPathname());
        expect($contents)
            ->not->toContain('Mollie\\Api\\')
            ->not->toContain('Agovena\\Extensions\\Mollie\\');
    }
});
