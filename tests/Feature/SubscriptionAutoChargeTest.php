<?php

declare(strict_types=1);

use Agovena\Extensions\Mollie\MollieApi;
use Agovena\Extensions\Mollie\MollieMandate;
use Agovena\Modules\Subscriptions\Enums\RenewalStatus;
use Agovena\Modules\Subscriptions\Enums\SubscriptionStatus;
use Agovena\Modules\Subscriptions\Models\Subscription;
use Agovena\Modules\Subscriptions\Models\SubscriptionRenewal;
use Agovena\Modules\Subscriptions\SubscriptionService;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Payments\HandlePaymentWebhook;
use App\Agovena\Payments\StartOrderPayment;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Settings\SettingsRepository;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Notifications\CataloguedMailNotification;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FakeMollieApi;

function enableAutoChargeModules(?FakeMollieApi $api = null): FakeMollieApi
{
    $api ??= new FakeMollieApi;
    app()->instance(MollieApi::class, $api);
    installAndEnableModule('subscriptions');
    app(SyncRegisteredPermissions::class)(force: true);
    installAndEnableExtension('mollie');
    app(ExtensionSettingsRepository::class)->set('mollie', 'api_key', 'test_abcdefghijklmnopqrstuvwxyz123456', secret: true);

    return $api;
}

function paidMollieSubscription(FakeMollieApi $api, array $config = []): Subscription
{
    $customer = Customer::factory()->create([
        'email' => 'mollie-renew@example.test',
        'name' => 'Renew Buyer',
    ]);
    $product = Product::factory()->active()->create(['price_amount' => 1999]);
    app(ProductCapabilityManager::class)->enable($product, 'subscribable', array_merge([
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ], $config));

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'payment_method' => 'mollie',
        'billing' => AddressData::fromArray([
            'name' => $customer->name,
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
    ]);

    $attempt = app(StartOrderPayment::class)->handle(
        $order,
        'mollie',
        'https://example.test/return',
        'https://example.test/cancel',
    );
    $api->markPaid((string) $attempt->external_id);
    $request = Request::create('/webhooks/payments/mollie', 'POST', ['id' => $attempt->external_id]);
    app(HandlePaymentWebhook::class)->handle('mollie', $request);

    return Subscription::query()->where('order_id', $order->id)->firstOrFail();
}

test('renewal copies the origin gateway and auto-charges when a reusable authorization exists', function () {
    Notification::fake();
    $api = enableAutoChargeModules();
    $api->nextStatus = 'paid';
    $subscription = paidMollieSubscription($api);

    expect($subscription->payment_gateway)->toBe('mollie')
        ->and(MollieMandate::query()->where('customer_email', 'mollie-renew@example.test')->value('mandate_id'))
        ->toBe('mdt_fake');

    $createsAfterFirst = $api->createCalls;
    $this->travelTo(CarbonImmutable::parse($subscription->next_billing_at));
    $this->artisan('agovena:process-subscription-renewals')->assertSuccessful();

    $subscription->refresh();
    $renewal = SubscriptionRenewal::query()->firstOrFail();
    $customer = Customer::query()->where('email', 'mollie-renew@example.test')->firstOrFail();

    expect($renewal->status)->toBe(RenewalStatus::Paid)
        ->and($renewal->order->status)->toBe(OrderStatus::Paid)
        ->and($renewal->order->payment->status)->toBe(PaymentStatus::Paid)
        ->and($renewal->order->payment->method)->toBe('mollie')
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and(Subscription::query()->count())->toBe(1)
        ->and($api->createCalls)->toBe($createsAfterFirst + 1);

    Notification::assertSentTo($customer, CataloguedMailNotification::class, function (CataloguedMailNotification $mail): bool {
        return $mail->key === 'subscription_renewal_paid';
    });
    Notification::assertNotSentTo($customer, CataloguedMailNotification::class, function (CataloguedMailNotification $mail): bool {
        return $mail->key === 'subscription_renewal';
    });
});

test('duplicate scheduler runs do not create a second renewal charge', function () {
    $api = enableAutoChargeModules();
    $api->nextStatus = 'paid';
    $subscription = paidMollieSubscription($api);
    $createsAfterFirst = $api->createCalls;

    $this->travelTo(CarbonImmutable::parse($subscription->next_billing_at));
    $this->artisan('agovena:process-subscription-renewals')->assertSuccessful();
    $this->artisan('agovena:process-subscription-renewals')->assertSuccessful();
    app(SubscriptionService::class)->processDue(CarbonImmutable::parse($subscription->next_billing_at));

    expect(SubscriptionRenewal::query()->count())->toBe(1)
        ->and(PaymentAttempt::query()->where('idempotency_key', 'like', 'recurring-%')->count())->toBe(1)
        ->and($api->createCalls)->toBe($createsAfterFirst + 1);
});

test('missing reusable authorization leaves the renewal payable', function () {
    Notification::fake();
    $api = enableAutoChargeModules();
    $subscription = paidMollieSubscription($api);
    MollieMandate::query()->where('customer_email', 'mollie-renew@example.test')->update(['mandate_id' => null]);
    $createsAfterFirst = $api->createCalls;

    $this->travelTo(CarbonImmutable::parse($subscription->next_billing_at));
    $this->artisan('agovena:process-subscription-renewals')->assertSuccessful();

    $renewal = SubscriptionRenewal::query()->firstOrFail();

    expect($renewal->status)->toBe(RenewalStatus::Pending)
        ->and($renewal->require_manual_payment)->toBeTrue()
        ->and($renewal->order->isAwaitingPayment())->toBeTrue()
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::PastDue)
        ->and($api->createCalls)->toBe($createsAfterFirst);

    Notification::assertSentTo(
        Customer::query()->where('email', 'mollie-renew@example.test')->firstOrFail(),
        CataloguedMailNotification::class,
        function (CataloguedMailNotification $mail): bool {
            return $mail->key === 'subscription_renewal';
        },
    );
});

test('revoked reusable authorization falls back to pay now', function () {
    $api = enableAutoChargeModules();
    $subscription = paidMollieSubscription($api);
    MollieMandate::query()->delete();
    $createsAfterFirst = $api->createCalls;

    $this->travelTo(CarbonImmutable::parse($subscription->next_billing_at));
    app(SubscriptionService::class)->processDue();

    expect(SubscriptionRenewal::query()->firstOrFail()->require_manual_payment)->toBeTrue()
        ->and($api->createCalls)->toBe($createsAfterFirst)
        ->and(PaymentAttempt::query()->where('idempotency_key', 'like', 'recurring-%')->count())->toBe(0);
});

test('failed automatic renewal stays payable and retries after the configured delay', function () {
    Notification::fake();
    $api = enableAutoChargeModules();
    $subscription = paidMollieSubscription($api);
    $api->failCreate = true;
    $createsAfterFirst = $api->createCalls;

    $due = CarbonImmutable::parse($subscription->next_billing_at);
    $this->travelTo($due);
    app(SubscriptionService::class)->processDue($due);

    $renewal = SubscriptionRenewal::query()->firstOrFail();
    $attempt = PaymentAttempt::query()->where('idempotency_key', 'like', 'recurring-%')->firstOrFail();
    expect($attempt->status)->toBe(PaymentAttemptStatus::Processing)
        ->and($renewal->charge_attempts)->toBe(1)
        ->and($renewal->order->isAwaitingPayment())->toBeTrue()
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::PastDue)
        ->and($api->createCalls)->toBe($createsAfterFirst);

    Notification::assertSentTo(
        Customer::query()->where('email', 'mollie-renew@example.test')->firstOrFail(),
        CataloguedMailNotification::class,
        function (CataloguedMailNotification $mail): bool {
            return $mail->key === 'subscription_renewal_failed';
        },
    );

    app(SubscriptionService::class)->processDue($due);
    expect($renewal->fresh()->charge_attempts)->toBe(1)
        ->and($api->createCalls)->toBe($createsAfterFirst);

    $this->travelTo(CarbonImmutable::parse($renewal->fresh()->next_retry_at));
    $api->failCreate = false;
    $api->nextStatus = 'paid';
    app(SubscriptionService::class)->processDue();

    expect($renewal->fresh()->status)->toBe(RenewalStatus::Paid)
        ->and($api->createCalls)->toBe($createsAfterFirst + 1);
});

test('pending automatic charge is confirmed by webhook without a second provider charge', function () {
    $api = enableAutoChargeModules();
    $subscription = paidMollieSubscription($api);
    $api->nextStatus = 'open';
    $createsAfterFirst = $api->createCalls;

    $this->travelTo(CarbonImmutable::parse($subscription->next_billing_at));
    app(SubscriptionService::class)->processDue();

    $renewal = SubscriptionRenewal::query()->firstOrFail();
    $attempt = PaymentAttempt::query()->where('idempotency_key', 'like', 'recurring-%')->firstOrFail();

    expect($renewal->status)->toBe(RenewalStatus::Pending)
        ->and($attempt->status)->toBe(PaymentAttemptStatus::Processing)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);

    $api->markPaid((string) $attempt->external_id);
    $request = Request::create('/webhooks/payments/mollie', 'POST', ['id' => $attempt->external_id]);
    app(HandlePaymentWebhook::class)->handle('mollie', $request);

    expect($renewal->fresh()->status)->toBe(RenewalStatus::Paid)
        ->and($renewal->fresh()->order->status)->toBe(OrderStatus::Paid);

    app(SubscriptionService::class)->processDue();
    expect($api->createCalls)->toBe($createsAfterFirst + 1)
        ->and(Subscription::query()->count())->toBe(1);
});

test('provider timeout leaves the attempt open and retry uses the same payment attempt', function () {
    $api = enableAutoChargeModules();
    $subscription = paidMollieSubscription($api);
    $api->timeout = true;
    $createsAfterFirst = $api->createCalls;

    $this->travelTo(CarbonImmutable::parse($subscription->next_billing_at));
    app(SubscriptionService::class)->processDue();

    $renewal = SubscriptionRenewal::query()->firstOrFail();
    $attempt = PaymentAttempt::query()->where('idempotency_key', 'like', 'recurring-%')->firstOrFail();
    expect($attempt->status)->toBe(PaymentAttemptStatus::Processing)
        ->and($api->createCalls)->toBe($createsAfterFirst)
        ->and($renewal->charge_attempts)->toBe(1);

    $api->timeout = false;
    $api->nextStatus = 'paid';
    $this->travelTo(CarbonImmutable::parse($renewal->fresh()->next_retry_at));
    app(SubscriptionService::class)->processDue();

    expect($renewal->fresh()->status)->toBe(RenewalStatus::Paid)
        ->and(PaymentAttempt::query()->where('idempotency_key', 'like', 'recurring-%')->count())->toBe(1)
        ->and($api->createCalls)->toBe($createsAfterFirst + 1);
});

test('exhausted renewal retries stay payable and may schedule cancel at period end', function () {
    $api = enableAutoChargeModules();
    app(SettingsRepository::class)->set('store', 'subscription_retry_max', 1);
    app(SettingsRepository::class)->set('store', 'subscription_retry_exhausted', 'cancel_at_period_end');
    $subscription = paidMollieSubscription($api);
    $api->failCreate = true;

    $due = CarbonImmutable::parse($subscription->next_billing_at);
    $this->travelTo($due);
    app(SubscriptionService::class)->processDue($due);

    $renewal = SubscriptionRenewal::query()->firstOrFail();
    expect($renewal->charge_attempts)->toBe(1)
        ->and($renewal->require_manual_payment)->toBeTrue()
        ->and($renewal->next_retry_at)->toBeNull()
        ->and($renewal->order->isAwaitingPayment())->toBeTrue()
        ->and($subscription->fresh()->cancel_at_period_end)->toBeTrue()
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::PastDue);

    $createsAfterExhaustion = $api->createCalls;
    app(SubscriptionService::class)->processDue($due->addHour());
    expect($renewal->fresh()->charge_attempts)->toBe(1)
        ->and($api->createCalls)->toBe($createsAfterExhaustion);
});

test('subscriptions module does not import mollie types', function () {
    $root = optionalModuleRoot('subscriptions');
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $contents = (string) file_get_contents($file->getPathname());
        expect($contents)
            ->not->toContain('Agovena\\Extensions\\Mollie\\')
            ->not->toContain('Mollie\\Api\\')
            ->not->toContain('MollieMandate');
    }
});
