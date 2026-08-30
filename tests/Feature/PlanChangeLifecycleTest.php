<?php

declare(strict_types=1);

use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Subscriptions\Enums\SubscriptionInterval;
use Agovena\Modules\Subscriptions\Enums\SubscriptionStatus;
use Agovena\Modules\Subscriptions\Listeners\ApplyPlanChangeToSubscription;
use Agovena\Modules\Subscriptions\Models\Subscription;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Payments\Contracts\CancelsPayments;
use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\PlanChanges\ApplyPlanChange;
use App\Agovena\PlanChanges\CancelPlanChangeRequest;
use App\Agovena\PlanChanges\RequestPlanChange;
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
