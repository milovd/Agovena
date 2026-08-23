<?php

declare(strict_types=1);

use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Subscriptions\Models\Subscription;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\PlanChanges\CancelPlanChangeRequest;
use App\Agovena\PlanChanges\RequestPlanChange;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPlanChange;
use App\Models\ProductPlanChangeRequest;
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
        ->and($subscription->fresh()->product_id)->toBe($to->id)
        ->and($subscription->fresh()->price_amount)->toBe(2500)
        ->and(ServiceInstance::query()->firstOrFail()->product_id)->toBe($to->id);
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

    $request = app(RequestPlanChange::class)->handle($customer, $from, $to);
    app(CancelPlanChangeRequest::class)->handle($request);

    expect($request->fresh()->status)->toBe('cancelled')
        ->and(Order::query()->findOrFail($request->order_id)->status)->toBe(OrderStatus::Cancelled);
});

test('failed payment leaves a plan change pending', function () {
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

    $request = app(RequestPlanChange::class)->handle($customer, $from, $to);

    expect($request->status)->toBe('pending')
        ->and(ProductPlanChangeRequest::query()->whereKey($request->id)->value('status'))->toBe('pending');
});
