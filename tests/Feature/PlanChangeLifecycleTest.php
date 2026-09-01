<?php

declare(strict_types=1);

use Agovena\Modules\Provisioning\Listeners\ApplyPlanChangeToService;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Provisioning\PlanChangeCompensationJournal;
use Agovena\Modules\Provisioning\PlanChangeCompensationRecovery;
use Agovena\Modules\Subscriptions\Enums\SubscriptionInterval;
use Agovena\Modules\Subscriptions\Enums\SubscriptionStatus;
use Agovena\Modules\Subscriptions\Listeners\ApplyPlanChangeToSubscription;
use Agovena\Modules\Subscriptions\Models\Subscription;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Packages\OptionalPackagesPath;
use App\Agovena\Payments\Contracts\CancelsPayments;
use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\PlanChanges\ApplyPlanChange;
use App\Agovena\PlanChanges\CancelPlanChangeRequest;
use App\Agovena\PlanChanges\RequestPlanChange;
use App\Agovena\Provisioning\Contracts\Provisioner;
use App\Agovena\Provisioning\Contracts\ProvisionerLifecycle;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Agovena\Provisioning\ServiceInstanceInfo;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Events\PlanChangeApplied;
use App\Listeners\ApplyPlanChangeWhenOrderPaid;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPlanChange;
use App\Models\ProductPlanChangeRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function enablePlanChangeModules(): void
{
    installAndEnableModule('subscriptions');
    installAndEnableModule('provisioning');
    app(SyncRegisteredPermissions::class)(force: true);
}

function planChangeBilling(): AddressData
{
    return AddressData::fromArray([
        'name' => 'Plan Buyer',
        'line1' => 'Street 1',
        'city' => 'Amsterdam',
        'postal_code' => '1000 AA',
        'country' => 'NL',
    ]);
}

function createPlanSubscription(Customer $customer, Product $product): Subscription
{
    return Subscription::query()->create([
        'number' => 'SUB-'.strtoupper(str()->random(10)),
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
        'customer_name' => $customer->name,
        'product_id' => $product->id,
        'status' => SubscriptionStatus::Active,
        'interval' => SubscriptionInterval::Month,
        'interval_count' => 1,
        'price_amount' => $product->price_amount,
        'currency' => $product->currency,
        'quantity' => 1,
    ]);
}

test('paying an immediate plan-change order applies the new plan', function () {
    enablePlanChangeModules();
    $customer = Customer::factory()->create();
    $from = Product::factory()->active()->create(['price_amount' => 1000, 'currency' => 'EUR']);
    $to = Product::factory()->active()->create(['price_amount' => 2500, 'currency' => 'EUR']);
    app(ProductCapabilityManager::class)->enable($from, 'subscribable', [
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);
    app(ProductCapabilityManager::class)->enable($from, 'provisionable', ['provider_key' => 'manual']);
    app(ProductCapabilityManager::class)->enable($to, 'subscribable', [
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);
    app(ProductCapabilityManager::class)->enable($to, 'provisionable', ['provider_key' => 'manual']);

    app(CartService::class)->add($from->id, 1);
    $origin = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => planChangeBilling(),
    ]);
    app(RecordManualPayment::class)->handle($origin, $this->createStaff());

    $subscription = Subscription::query()->firstOrFail();
    ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'upgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);

    $request = app(RequestPlanChange::class)->handle($customer, $from, $to, $subscription->id);
    expect($request->status)->toBe('pending')
        ->and($request->order_id)->not->toBeNull();

    app(RecordManualPayment::class)->handle(Order::query()->findOrFail($request->order_id), $this->createStaff());

    expect($request->fresh()->status)->toBe('applied')
        ->and($request->fresh()->active_request_key)->toBeNull()
        ->and($subscription->fresh()->product_id)->toBe($to->id)
        ->and($subscription->fresh()->price_amount)->toBe(2500)
        ->and(Subscription::query()->count())->toBe(1)
        ->and(ServiceInstance::query()->where('order_id', $origin->id)->count())->toBe(1)
        ->and(ServiceInstance::query()->where('order_id', $request->order_id)->count())->toBe(0)
        ->and(ServiceInstance::query()->where('order_id', $origin->id)->firstOrFail()->product_id)->toBe($to->id);
});

test('an immediate zero-difference change applies without an order', function () {
    $customer = Customer::factory()->create();
    $from = Product::factory()->active()->create(['price_amount' => 2000, 'currency' => 'EUR']);
    $to = Product::factory()->active()->create(['price_amount' => 1500, 'currency' => 'EUR']);
    ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'downgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);

    $request = app(RequestPlanChange::class)->handle($customer, $from, $to);

    expect($request->status)->toBe('applied')
        ->and($request->order_id)->toBeNull();
});

test('a pending plan change can be cancelled without applying', function () {
    enablePlanChangeModules();
    $customer = Customer::factory()->create();
    $from = Product::factory()->active()->create(['price_amount' => 1000, 'currency' => 'EUR']);
    $to = Product::factory()->active()->create(['price_amount' => 2500, 'currency' => 'EUR']);
    ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'upgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);

    $subscription = createPlanSubscription($customer, $from);
    $request = app(RequestPlanChange::class)->handle($customer, $from, $to, $subscription->id);
    app(CancelPlanChangeRequest::class)->handle($request);

    expect($request->fresh()->status)->toBe('cancelled')
        ->and($request->fresh()->active_request_key)->toBeNull()
        ->and(Order::query()->findOrFail($request->order_id)->status)->toBe(OrderStatus::Cancelled);
});

test('failed payment leaves a plan change pending', function () {
    enablePlanChangeModules();
    $customer = Customer::factory()->create();
    $from = Product::factory()->active()->create(['price_amount' => 1000, 'currency' => 'EUR']);
    $to = Product::factory()->active()->create(['price_amount' => 2500, 'currency' => 'EUR']);
    ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'upgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);

    $subscription = createPlanSubscription($customer, $from);
    $request = app(RequestPlanChange::class)->handle($customer, $from, $to, $subscription->id);

    expect($request->status)->toBe('pending')
        ->and(ProductPlanChangeRequest::query()->whereKey($request->id)->value('status'))->toBe('pending');
});

test('a plan change cannot apply before its surcharge payment is paid', function () {
    enablePlanChangeModules();
    $customer = Customer::factory()->create();
    $from = Product::factory()->active()->create(['price_amount' => 1000, 'currency' => 'EUR']);
    $to = Product::factory()->active()->create(['price_amount' => 2500, 'currency' => 'EUR']);
    ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'upgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);
    $subscription = createPlanSubscription($customer, $from);
    $request = app(RequestPlanChange::class)->handle($customer, $from, $to, $subscription->id);

    expect(fn () => app(ApplyPlanChange::class)->handle($request))
        ->toThrow(ValidationException::class);
});

test('positive plan changes without a subscription are rejected', function () {
    $customer = Customer::factory()->create();
    $from = Product::factory()->active()->create(['price_amount' => 1000, 'currency' => 'EUR']);
    $to = Product::factory()->active()->create(['price_amount' => 2500, 'currency' => 'EUR']);
    ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'upgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);

    expect(fn () => app(RequestPlanChange::class)->handle($customer, $from, $to))
        ->toThrow(ValidationException::class);
});

test('plan change requests have a unique active request key', function () {
    expect(Schema::hasColumn('product_plan_change_requests', 'active_request_key'))->toBeTrue();

    $index = collect(Schema::getIndexes('product_plan_change_requests'))
        ->firstWhere('name', 'product_plan_change_requests_active_request_key_unique');

    expect($index['unique'] ?? false)->toBeTrue();
});

test('plan changes reject a subscription owned by another customer or on another product', function () {
    enablePlanChangeModules();
    $owner = Customer::factory()->create();
    $attacker = Customer::factory()->create();
    $from = Product::factory()->active()->create(['price_amount' => 1000, 'currency' => 'EUR']);
    $to = Product::factory()->active()->create(['price_amount' => 2500, 'currency' => 'EUR']);
    ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'upgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);
    $subscription = Subscription::query()->create([
        'number' => 'SUB-SECURITY-1',
        'customer_id' => $owner->id,
        'customer_email' => $owner->email,
        'customer_name' => $owner->name,
        'product_id' => $from->id,
        'status' => SubscriptionStatus::Active,
        'interval' => SubscriptionInterval::Month,
        'interval_count' => 1,
        'price_amount' => $from->price_amount,
        'currency' => $from->currency,
        'quantity' => 1,
    ]);

    expect(fn () => app(RequestPlanChange::class)->handle($attacker, $from, $to, $subscription->id))
        ->toThrow(ValidationException::class);

    $subscription->update(['product_id' => $to->id]);
    expect(fn () => app(RequestPlanChange::class)->handle($owner, $from, $to, $subscription->id))
        ->toThrow(ValidationException::class);
});

test('positive plan changes require a target subscription', function () {
    $customer = Customer::factory()->create();
    $from = Product::factory()->active()->create(['price_amount' => 1000, 'currency' => 'EUR']);
    $to = Product::factory()->active()->create(['price_amount' => 2500, 'currency' => 'EUR']);
    ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'upgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);

    expect(fn () => app(RequestPlanChange::class)->handle($customer, $from, $to))
        ->toThrow(ValidationException::class);
});

test('plan change cancellation loses to an already paid surcharge', function () {
    enablePlanChangeModules();
    $customer = Customer::factory()->create();
    $from = Product::factory()->active()->create(['price_amount' => 1000, 'currency' => 'EUR']);
    $to = Product::factory()->active()->create(['price_amount' => 2500, 'currency' => 'EUR']);
    ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'upgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);
    $subscription = createPlanSubscription($customer, $from);
    $request = app(RequestPlanChange::class)->handle($customer, $from, $to, $subscription->id);
    $order = Order::query()->findOrFail($request->order_id);
    $payment = $order->payment()->firstOrFail();
    $order->update(['status' => OrderStatus::Paid]);
    $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);

    expect(fn () => app(CancelPlanChangeRequest::class)->handle($request))
        ->toThrow(ValidationException::class)
        ->and($request->fresh()->status)->toBe('pending');
});

test('subscription plan changes require an active target and source subscription', function () {
    enablePlanChangeModules();
    $customer = Customer::factory()->create();
    $from = Product::factory()->active()->create(['price_amount' => 1000, 'currency' => 'EUR']);
    $to = Product::factory()->create(['price_amount' => 2500, 'currency' => 'EUR']);
    $subscription = createPlanSubscription($customer, $from);
    $mapping = ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'upgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);
    $request = ProductPlanChangeRequest::query()->create([
        'product_plan_change_id' => $mapping->id,
        'customer_id' => $customer->id,
        'subscription_id' => $subscription->id,
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'status' => 'applying',
        'timing' => 'immediate',
    ]);

    expect(fn () => app(ApplyPlanChangeToSubscription::class)->handle(new PlanChangeApplied($request)))
        ->toThrow(ValidationException::class);

});

test('plan change requests reject inactive source and target products', function () {
    $customer = Customer::factory()->create();
    $from = Product::factory()->active()->create(['price_amount' => 2000, 'currency' => 'EUR']);
    $to = Product::factory()->create(['price_amount' => 2000, 'currency' => 'EUR']);
    ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'upgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);

    expect(fn () => app(RequestPlanChange::class)->handle($customer, $from, $to))
        ->toThrow(ValidationException::class);
});

test('plan-change cancellation preserves reconciliation when provider cancellation fails', function () {
    $customer = Customer::factory()->create();
    $from = Product::factory()->active()->create(['price_amount' => 1000, 'currency' => 'EUR']);
    $to = Product::factory()->active()->create(['price_amount' => 1500, 'currency' => 'EUR']);
    $mapping = ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'upgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'status' => OrderStatus::Pending,
        'currency' => 'EUR',
        'total_amount' => 500,
    ]);
    $payment = $order->payment()->create([
        'method' => 'failing-cancel',
        'status' => PaymentStatus::Pending,
        'amount' => 500,
        'currency' => 'EUR',
    ]);
    $request = ProductPlanChangeRequest::query()->create([
        'product_plan_change_id' => $mapping->id,
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'timing' => 'immediate',
        'status' => 'pending',
        'active_request_key' => 'cancel-failure-key',
    ]);
    $gateway = Mockery::mock(PaymentGateway::class, CancelsPayments::class);
    $gateway->shouldReceive('id')->andReturn('failing-cancel');
    $gateway->shouldReceive('cancel')->once()->andThrow(new RuntimeException('provider unavailable'));
    app(PaymentGatewayRegistry::class)->register($gateway);

    expect(fn () => app(CancelPlanChangeRequest::class)->handle($request))
        ->toThrow(ValidationException::class);

    expect($request->fresh()->status)->toBe('pending')
        ->and($payment->fresh()->reconciliation_status)->toBe('manual_review')
        ->and($payment->fresh()->reconciliation_meta['reason'] ?? null)->toBe('provider_cancel_failed');
});

test('failed plan-change compensation is durable and releases its active request key', function () {
    $customer = Customer::factory()->create();
    $from = Product::factory()->active()->create(['price_amount' => 2000, 'currency' => 'EUR']);
    $to = Product::factory()->active()->create(['price_amount' => 1500, 'currency' => 'EUR']);
    $mapping = ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'downgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);
    $request = ProductPlanChangeRequest::query()->create([
        'product_plan_change_id' => $mapping->id,
        'customer_id' => $customer->id,
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'timing' => 'immediate',
        'status' => 'pending',
        'active_request_key' => 'active-plan-change-key',
    ]);
    Event::listen(PlanChangeApplied::class, static function (PlanChangeApplied $event): void {
        $event->registerCompensation(static function (): void {
            throw new RuntimeException('provider compensation failed');
        });

        throw new RuntimeException('provider update failed');
    });

    expect(fn () => app(ApplyPlanChange::class)->handle($request))
        ->toThrow(RuntimeException::class, 'provider update failed');

    expect($request->fresh()->status)->toBe('manual_review')
        ->and($request->fresh()->active_request_key)->toBeNull();
});

test('plan-change compensation journal entries are decoded lazily', function () {
    enablePlanChangeModules();

    expect(app(PlanChangeCompensationJournal::class)->entries())->toBeInstanceOf(Generator::class);
});

test('plan-change failure before compensation registration becomes manual review', function () {
    $customer = Customer::factory()->create();
    $from = Product::factory()->active()->create(['price_amount' => 2000, 'currency' => 'EUR']);
    $to = Product::factory()->active()->create(['price_amount' => 1500, 'currency' => 'EUR']);
    $mapping = ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'downgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);
    $request = ProductPlanChangeRequest::query()->create([
        'product_plan_change_id' => $mapping->id,
        'customer_id' => $customer->id,
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'timing' => 'immediate',
        'status' => 'pending',
        'active_request_key' => 'provider-before-compensation-key',
    ]);
    Event::listen(PlanChangeApplied::class, static function (): void {
        throw new RuntimeException('provider update failed before compensation registration');
    });

    expect(fn () => app(ApplyPlanChange::class)->handle($request))
        ->toThrow(RuntimeException::class, 'provider update failed before compensation registration');

    expect($request->fresh()->status)->toBe('manual_review')
        ->and($request->fresh()->active_request_key)->toBeNull();
});

test('paid plan-change recovery retries requests left applying', function () {
    $customer = Customer::factory()->create();
    $from = Product::factory()->active()->create(['price_amount' => 1000, 'currency' => 'EUR']);
    $to = Product::factory()->active()->create(['price_amount' => 2500, 'currency' => 'EUR']);
    $mapping = ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'upgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);
    $order = Order::factory()->create([
        'status' => OrderStatus::Paid,
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
        'currency' => 'EUR',
        'subtotal_amount' => 1500,
        'shipping_amount' => 0,
        'total_amount' => 1500,
        'custom_properties_snapshot' => ['origin' => 'plan_change_surcharge'],
    ]);
    $order->items()->create([
        'product_id' => $to->id,
        'label' => $to->name,
        'quantity' => 1,
        'unit_amount' => 1500,
        'line_total_amount' => 1500,
        'currency' => 'EUR',
    ]);
    $order->payment()->create([
        'method' => 'manual',
        'status' => PaymentStatus::Paid,
        'amount' => 1500,
        'currency' => $order->currency,
        'paid_at' => now(),
    ]);
    $request = ProductPlanChangeRequest::query()->create([
        'product_plan_change_id' => $mapping->id,
        'customer_id' => $customer->id,
        'status' => 'applying',
        'order_id' => $order->id,
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'timing' => 'immediate',
    ]);

    app(ApplyPlanChangeWhenOrderPaid::class)
        ->handle(new OrderPaid($order->fresh(['items', 'payment'])));

    expect($request->fresh()->status)->toBe('applied');
});

test('plan-change compensation journal persists encrypted phases', function () {
    enablePlanChangeModules();
    $journal = app(PlanChangeCompensationJournal::class);
    $info = new ServiceInstanceInfo(
        id: 42,
        label: 'fixture',
        status: 'active',
        providerKey: 'fixture-provider',
        externalRef: 'fixture-ref',
        meta: ['fixture' => true],
        serverSettings: ['region' => 'test'],
        providerSettings: ['setting' => 'fixture'],
    );

    $path = $journal->prepare(10, 42, 'fixture-provider', [
        'status' => 'active',
        'product_id' => 1,
        'provider_key' => 'fixture-provider',
        'meta' => ['fixture' => true],
    ], $info);

    expect(DB::table('plan_change_compensation_journals')->where('journal_key', $path)->value('payload_encrypted'))
        ->not->toContain('fixture-provider')
        ->and(iterator_to_array($journal->entries())[0]['payload']['phase'])->toBe('prepared');

    $claim = $journal->claim($path);
    expect($claim)->not->toBeNull()
        ->and($journal->claim($path))->toBeNull();
    $journal->release($path, (string) $claim);

    $journal->markApplied($path);
    expect(iterator_to_array($journal->entries())[0]['payload']['phase'])->toBe('applied');
    $appliedClaim = $journal->claim($path);
    expect($appliedClaim)->not->toBeNull();
    $journal->release($path, (string) $appliedClaim);

    $journal->forget($path);
    expect(DB::table('plan_change_compensation_journals')->where('journal_key', $path)->exists())->toBeFalse();
});

test('compensation journal finalization requires the active claim owner', function () {
    enablePlanChangeModules();
    $journal = app(PlanChangeCompensationJournal::class);
    $info = new ServiceInstanceInfo(
        id: 42,
        label: 'fixture',
        status: 'active',
        providerKey: 'fixture-provider',
        externalRef: 'fixture-ref',
        meta: [],
    );
    $path = $journal->prepare(10, 42, 'fixture-provider', [], $info);
    $claim = $journal->claim($path);

    expect(fn () => $journal->markApplied($path, 'wrong-claim-token'))
        ->toThrow(RuntimeException::class);
    expect(iterator_to_array($journal->entries())[0]['payload']['phase'])->toBe('prepared');

    $journal->markApplied($path, (string) $claim);
    expect(iterator_to_array($journal->entries())[0]['payload']['phase'])->toBe('applied');
});

test('compensation journal has isolated storage for the active database driver', function () {
    $driver = (string) config('database.connections.compensation_journal.driver');
    $compensationDatabase = config('database.connections.compensation_journal.database');
    $defaultDatabase = config('database.connections.'.config('database.default').'.database');

    if ($driver === 'sqlite') {
        expect($driver)->toBe(config('database.default'))
            ->and($compensationDatabase)->toBe(database_path('compensation-journal.sqlite'))
            ->and($compensationDatabase)->not->toBe($defaultDatabase);
    } else {
        expect($driver)->toBe(config('database.default'))
            ->and($compensationDatabase)->toBe($defaultDatabase);
    }
});

test('compensation journal uses its independent connection for persistent test databases', function () {
    enablePlanChangeModules();
    $defaultConnection = config('database.default');
    $defaultCompensationConnection = config('database.compensation_connection');
    $persistentConnection = array_merge(
        (array) config('database.connections.sqlite'),
        ['database' => database_path('persistent-test.sqlite')],
    );
    config([
        'database.default' => 'persistent_default',
        'database.connections.persistent_default' => $persistentConnection,
        'database.compensation_connection' => 'compensation_journal',
    ]);
    DB::purge('persistent_default');

    try {
        $journal = app(PlanChangeCompensationJournal::class);
        $database = Closure::bind(fn () => $this->database(), $journal, PlanChangeCompensationJournal::class);
        $migration = require OptionalPackagesPath::root().'/modules/provisioning/database/migrations/2026_08_31_000102_create_plan_change_compensation_journals_table.php';
        $journalSchema = Closure::bind(fn () => $this->journalSchema(), $migration, $migration);

        expect($database()->getName())->toBe('compensation_journal');
        expect($journalSchema()->getConnection()->getName())->toBe('compensation_journal');
    } finally {
        config([
            'database.default' => $defaultConnection,
            'database.compensation_connection' => $defaultCompensationConnection,
        ]);
        DB::purge('persistent_default');
    }
});

test('legacy service-secret migration preserves an existing runtime field', function () {
    enablePlanChangeModules();

    $instance = ServiceInstance::query()->create([
        'number' => 'MIGRATION-SECRET-1',
        'customer_email' => 'migration-secret@example.test',
        'status' => 'active',
        'provider_key' => 'fixture-provider',
        'meta' => [
            'provider_settings' => ['region' => 'new'],
        ],
    ]);
    $serverCiphertext = Crypt::encryptString(json_encode(
        ['host' => 'existing'],
        JSON_THROW_ON_ERROR,
    ));
    DB::table('service_instance_runtime_secrets')->insert([
        'service_instance_id' => $instance->id,
        'server_settings_encrypted' => $serverCiphertext,
        'provider_settings_encrypted' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $optionalRoot = OptionalPackagesPath::root();
    expect($optionalRoot)->not->toBeNull();
    /** @var string $optionalRoot */
    $migration = require $optionalRoot.'/modules/provisioning/database/migrations/2026_09_01_000100_migrate_legacy_runtime_secrets.php';
    $migration->up();

    $runtime = DB::table('service_instance_runtime_secrets')
        ->where('service_instance_id', $instance->id)
        ->first();
    expect($runtime->server_settings_encrypted)->toBe($serverCiphertext)
        ->and(Crypt::decryptString($runtime->provider_settings_encrypted))
        ->toBe(json_encode(['region' => 'new'], JSON_THROW_ON_ERROR));
});

test('plan-change compensation recovery uses the shared service-instance mutex', function () {
    enablePlanChangeModules();

    $customer = Customer::factory()->create();
    $from = Product::factory()->active()->create();
    $to = Product::factory()->active()->create();
    $mapping = ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'upgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);
    $request = ProductPlanChangeRequest::query()->create([
        'product_plan_change_id' => $mapping->id,
        'customer_id' => $customer->id,
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'timing' => 'immediate',
        'status' => 'pending',
    ]);
    $instance = ServiceInstance::query()->create([
        'number' => 'RECOVERY-MUTEX-1',
        'customer_email' => $customer->email,
        'customer_name' => $customer->name,
        'product_id' => $to->id,
        'customer_id' => $customer->id,
        'status' => 'active',
        'provider_key' => 'fixture-recovery',
        'meta' => ['plan_change' => ['request_id' => $request->id]],
    ]);
    $provider = Mockery::mock(Provisioner::class, ProvisionerLifecycle::class);
    $provider->shouldReceive('id')->andReturn('fixture-recovery');
    $transactionLevelAtProvider = 0;
    $provider->shouldReceive('changePlan')->once()->andReturnUsing(function () use (&$transactionLevelAtProvider): void {
        $transactionLevelAtProvider = DB::transactionLevel();
    });
    $provider->shouldReceive('syncStatus')->once()->andReturn(new ServiceInstanceInfo(
        id: $instance->id,
        label: 'recovered',
        status: 'active',
        providerKey: 'fixture-recovery',
        externalRef: 'fixture-recovery-ref',
        meta: [],
    ));
    app(ProvisionerRegistry::class)->register($provider);

    $info = new ServiceInstanceInfo(
        id: $instance->id,
        label: 'original',
        status: 'active',
        providerKey: 'fixture-recovery',
        externalRef: 'fixture-original-ref',
        meta: [],
    );
    $journal = app(PlanChangeCompensationJournal::class);
    $path = $journal->prepare($request->id, $instance->id, 'fixture-recovery', [
        'status' => 'active',
        'product_id' => $from->id,
        'provider_key' => 'fixture-recovery',
        'meta' => [],
    ], $info, [
        'status' => 'active',
        'product_id' => $to->id,
        'provider_key' => 'fixture-recovery',
        'meta' => ['plan_change' => ['request_id' => $request->id]],
    ]);
    $payload = $journal->read($path);
    $payload['created_at'] = now()->subMinutes(10)->toIso8601String();
    DB::table('plan_change_compensation_journals')->where('journal_key', $path)->update([
        'payload_encrypted' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
    ]);

    $lock = Mockery::mock();
    $lock->shouldReceive('block')->once()->with(10);
    $lock->shouldReceive('release')->once();
    Cache::shouldReceive('lock')
        ->once()
        ->with('agovena:provisioning:instance:'.$instance->id, 900)
        ->andReturn($lock);

    $minimumProviderTransactionLevel = config('database.default') === 'sqlite' ? 2 : 1;

    expect(app(PlanChangeCompensationRecovery::class)->recover())->toBe(1)
        ->and($transactionLevelAtProvider)->toBeGreaterThanOrEqual($minimumProviderTransactionLevel);
});

test('malformed compensation state is quarantined for manual review', function () {
    enablePlanChangeModules();

    $customer = Customer::factory()->create();
    $from = Product::factory()->active()->create();
    $to = Product::factory()->active()->create();
    $mapping = ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'upgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);
    $request = ProductPlanChangeRequest::query()->create([
        'product_plan_change_id' => $mapping->id,
        'customer_id' => $customer->id,
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'timing' => 'immediate',
        'status' => 'manual_review',
    ]);
    $instance = ServiceInstance::query()->create([
        'number' => 'RECOVERY-MALFORMED-1',
        'customer_email' => $customer->email,
        'customer_name' => $customer->name,
        'product_id' => $to->id,
        'customer_id' => $customer->id,
        'status' => 'active',
        'provider_key' => 'fixture-recovery-malformed',
        'meta' => ['plan_change' => ['request_id' => $request->id]],
    ]);
    $provider = Mockery::mock(Provisioner::class, ProvisionerLifecycle::class);
    $provider->shouldReceive('id')->andReturn('fixture-recovery-malformed');
    $provider->shouldReceive('changePlan')->never();
    $provider->shouldReceive('syncStatus')->never();
    app(ProvisionerRegistry::class)->register($provider);

    $info = new ServiceInstanceInfo(
        id: $instance->id,
        label: 'original',
        status: 'active',
        providerKey: 'fixture-recovery-malformed',
        externalRef: 'fixture-original-ref',
        meta: [],
    );
    $journal = app(PlanChangeCompensationJournal::class);
    $path = $journal->prepare($request->id, $instance->id, 'fixture-recovery-malformed', [
        'status' => 'active',
        'product_id' => $from->id,
        'provider_key' => 'fixture-recovery-malformed',
        'meta' => [],
    ], $info, [
        'status' => 'active',
        'product_id' => $to->id,
        'provider_key' => 'fixture-recovery-malformed',
        'meta' => ['plan_change' => ['request_id' => $request->id]],
    ]);
    $payload = $journal->read($path);
    $payload['created_at'] = now()->subMinutes(10)->toIso8601String();
    unset($payload['previous_info']['meta']);
    DB::table('plan_change_compensation_journals')->where('journal_key', $path)->update([
        'payload_encrypted' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
    ]);

    expect(app(PlanChangeCompensationRecovery::class)->recover())->toBe(0)
        ->and(iterator_to_array($journal->entries())[0]['payload']['phase'])->toBe('manual_review')
        ->and(iterator_to_array($journal->entries())[0]['payload']['recovery_reason'])->toBe('invalid_service_state');
});

test('compensation journal with an invalid maturity timestamp is quarantined', function () {
    enablePlanChangeModules();

    $customer = Customer::factory()->create();
    $from = Product::factory()->active()->create();
    $to = Product::factory()->active()->create();
    $mapping = ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'upgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);
    $request = ProductPlanChangeRequest::query()->create([
        'product_plan_change_id' => $mapping->id,
        'customer_id' => $customer->id,
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'timing' => 'immediate',
        'status' => 'manual_review',
    ]);
    $instance = ServiceInstance::query()->create([
        'number' => 'RECOVERY-INVALID-TIME-1',
        'customer_email' => $customer->email,
        'customer_name' => $customer->name,
        'product_id' => $to->id,
        'customer_id' => $customer->id,
        'status' => 'active',
        'provider_key' => 'fixture-recovery-invalid-time',
        'meta' => ['plan_change' => ['request_id' => $request->id]],
    ]);
    $provider = Mockery::mock(Provisioner::class, ProvisionerLifecycle::class);
    $provider->shouldReceive('id')->andReturn('fixture-recovery-invalid-time');
    $provider->shouldReceive('changePlan')->never();
    $provider->shouldReceive('syncStatus')->never();
    app(ProvisionerRegistry::class)->register($provider);

    $info = new ServiceInstanceInfo(
        id: $instance->id,
        label: 'original',
        status: 'active',
        providerKey: 'fixture-recovery-invalid-time',
        externalRef: 'fixture-original-ref',
        meta: [],
    );
    $journal = app(PlanChangeCompensationJournal::class);
    $path = $journal->prepare($request->id, $instance->id, 'fixture-recovery-invalid-time', [
        'status' => 'active',
        'product_id' => $from->id,
        'provider_key' => 'fixture-recovery-invalid-time',
        'meta' => [],
    ], $info, [
        'status' => 'active',
        'product_id' => $to->id,
        'provider_key' => 'fixture-recovery-invalid-time',
        'meta' => ['plan_change' => ['request_id' => $request->id]],
    ]);
    $payload = $journal->read($path);
    $payload['created_at'] = 'not-a-timestamp';
    DB::table('plan_change_compensation_journals')->where('journal_key', $path)->update([
        'payload_encrypted' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
    ]);

    expect(app(PlanChangeCompensationRecovery::class)->recover())->toBe(0)
        ->and(iterator_to_array($journal->entries())[0]['payload']['phase'])->toBe('manual_review')
        ->and(iterator_to_array($journal->entries())[0]['payload']['recovery_reason'])->toBe('invalid_service_state');
});

test('plan-change compensation skips a journal whose target state was superseded', function () {
    enablePlanChangeModules();

    $customer = Customer::factory()->create();
    $from = Product::factory()->active()->create();
    $firstTarget = Product::factory()->active()->create();
    $newerTarget = Product::factory()->active()->create();
    $mapping = ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $firstTarget->id,
        'change_type' => 'upgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);
    $request = ProductPlanChangeRequest::query()->create([
        'product_plan_change_id' => $mapping->id,
        'customer_id' => $customer->id,
        'from_product_id' => $from->id,
        'to_product_id' => $firstTarget->id,
        'timing' => 'immediate',
        'status' => 'manual_review',
    ]);
    $instance = ServiceInstance::query()->create([
        'number' => 'RECOVERY-SUPERSEDED-1',
        'customer_email' => $customer->email,
        'customer_name' => $customer->name,
        'product_id' => $newerTarget->id,
        'customer_id' => $customer->id,
        'status' => 'active',
        'provider_key' => 'fixture-recovery-superseded',
        'meta' => ['plan_change' => ['request_id' => $request->id + 1]],
    ]);
    $provider = Mockery::mock(Provisioner::class, ProvisionerLifecycle::class);
    $provider->shouldReceive('id')->andReturn('fixture-recovery-superseded');
    $provider->shouldReceive('changePlan')->never();
    $provider->shouldReceive('syncStatus')->never();
    app(ProvisionerRegistry::class)->register($provider);

    $info = new ServiceInstanceInfo(
        id: $instance->id,
        label: 'original',
        status: 'active',
        providerKey: 'fixture-recovery-superseded',
        externalRef: 'fixture-original-ref',
        meta: [],
    );
    $journal = app(PlanChangeCompensationJournal::class);
    $path = $journal->prepare($request->id, $instance->id, 'fixture-recovery-superseded', [
        'status' => 'active',
        'product_id' => $from->id,
        'provider_key' => 'fixture-recovery-superseded',
        'meta' => [],
    ], $info, [
        'status' => 'active',
        'product_id' => $firstTarget->id,
        'provider_key' => 'fixture-recovery-superseded',
        'meta' => ['plan_change' => ['request_id' => $request->id]],
    ]);
    $payload = $journal->read($path);
    $payload['created_at'] = now()->subMinutes(10)->toIso8601String();
    DB::table('plan_change_compensation_journals')->where('journal_key', $path)->update([
        'payload_encrypted' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
    ]);

    expect(app(PlanChangeCompensationRecovery::class)->recover())->toBe(0)
        ->and(iterator_to_array($journal->entries())[0]['payload']['phase'])->toBe('manual_review')
        ->and($instance->fresh()->product_id)->toBe($newerTarget->id);
});

test('plan-change locks instances added during instance stabilization', function () {
    enablePlanChangeModules();
    $customer = Customer::factory()->create();
    $from = Product::factory()->active()->create();
    $to = Product::factory()->active()->create();
    app(ProductCapabilityManager::class)->enable($to, 'provisionable', [
        'provider_key' => 'fixture-plan-change-lock',
    ]);
    $subscription = createPlanSubscription($customer, $from);
    $mapping = ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'upgrade',
        'timing' => 'immediate',
        'is_active' => true,
        'sort' => 0,
    ]);
    $request = ProductPlanChangeRequest::query()->create([
        'product_plan_change_id' => $mapping->id,
        'customer_id' => $customer->id,
        'subscription_id' => $subscription->id,
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'timing' => 'immediate',
        'status' => 'pending',
    ]);
    $instanceData = [
        'number' => 'PLAN-LOCK-A',
        'customer_email' => $customer->email,
        'customer_name' => $customer->name,
        'customer_id' => $customer->id,
        'subscription_id' => $subscription->id,
        'status' => 'active',
        'provider_key' => 'other-provider',
        'meta' => [],
    ];
    ServiceInstance::query()->create($instanceData);
    $createdDuringLock = false;
    $provider = Mockery::mock(Provisioner::class, ProvisionerLifecycle::class);
    $provider->shouldReceive('id')->andReturn('fixture-plan-change-lock');
    app(ProvisionerRegistry::class)->register($provider);

    Cache::shouldReceive('lock')
        ->twice()
        ->withArgs(fn (string $key, int $seconds): bool => str_starts_with($key, 'agovena:provisioning:instance:') && $seconds === 900)
        ->andReturnUsing(function () use (&$createdDuringLock, $instanceData) {
            $lock = Mockery::mock();
            $lock->shouldReceive('block')->once()->with(10)->andReturnUsing(function () use (&$createdDuringLock, $instanceData): void {
                if (! $createdDuringLock) {
                    $createdDuringLock = true;
                    ServiceInstance::query()->create(array_merge($instanceData, ['number' => 'PLAN-LOCK-B']));
                }
            });
            $lock->shouldReceive('release')->once();

            return $lock;
        });

    expect(fn () => app(ApplyPlanChangeToService::class)
        ->handle(new PlanChangeApplied($request)))
        ->toThrow(ValidationException::class)
        ->and($createdDuringLock)->toBeTrue();
});
