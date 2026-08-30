<?php

declare(strict_types=1);

use Agovena\Modules\Subscriptions\Enums\RenewalStatus;
use Agovena\Modules\Subscriptions\Enums\SubscriptionStatus;
use Agovena\Modules\Subscriptions\Http\Livewire\Customer\SubscriptionShow as CustomerSubscriptionShow;
use Agovena\Modules\Subscriptions\Http\Livewire\Customer\SubscriptionsIndex;
use Agovena\Modules\Subscriptions\Models\Subscription;
use Agovena\Modules\Subscriptions\Models\SubscriptionRenewal;
use Agovena\Modules\Subscriptions\SubscriptionService;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Customer\CustomerAccountNav;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Models\Customer;
use App\Models\Product;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function enableSubscriptionsModule(): void
{
    installAndEnableModule('subscriptions');
    app(SyncRegisteredPermissions::class)(force: true);
}

function billingForSubscription(): AddressData
{
    return AddressData::fromArray([
        'name' => 'Sub Buyer',
        'line1' => 'Street 1',
        'city' => 'Amsterdam',
        'postal_code' => '1000 AA',
        'country' => 'NL',
    ]);
}

function makeSubscribableProduct(array $config = [], array $attrs = []): Product
{
    $product = Product::factory()->active()->create(array_merge(['price_amount' => 1999], $attrs));
    app(ProductCapabilityManager::class)->enable($product, 'subscribable', array_merge([
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ], $config));

    return $product->fresh(['capabilities']);
}

test('subscriptions module registers capability and account nav', function () {
    expect(app(ProductCapabilityRegistry::class)->has('subscribable'))->toBeFalse();

    enableSubscriptionsModule();

    expect(app(ProductCapabilityRegistry::class)->has('subscribable'))->toBeTrue()
        ->and(collect(app(CustomerAccountNav::class)->items())->pluck('id')->all())
        ->toContain('subscriptions');
});

test('paid subscribable order creates an active subscription', function () {
    enableSubscriptionsModule();
    $customer = Customer::factory()->create();
    $product = makeSubscribableProduct(['interval' => 'month', 'interval_count' => 1]);

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingForSubscription(),
    ]);

    expect(Subscription::query()->count())->toBe(0);

    app(RecordManualPayment::class)->handle($order, $this->createStaff());

    $subscription = Subscription::query()->where('order_id', $order->id)->first();
    expect($subscription)->not->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->interval->value)->toBe('month')
        ->and($subscription->price_amount)->toBe(1999)
        ->and($subscription->next_billing_at)->not->toBeNull();
});

test('customer portal lists subscriptions and can cancel at period end', function () {
    enableSubscriptionsModule();
    $customer = Customer::factory()->create();
    $product = makeSubscribableProduct();

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingForSubscription(),
    ]);
    app(RecordManualPayment::class)->handle($order, $this->createStaff());

    $subscription = Subscription::query()->firstOrFail();

    Livewire::actingAs($customer->user)
        ->test(SubscriptionsIndex::class)
        ->assertSee($product->name)
        ->call('cancel', $subscription->id)
        ->assertSee(__('subscriptions::customer.ends_at_period'));

    expect($subscription->fresh()->cancel_at_period_end)->toBeTrue()
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);
});

test('customer id ownership cannot be replaced by a matching legacy email', function () {
    enableSubscriptionsModule();
    $owner = Customer::factory()->create();
    $other = Customer::factory()->create();
    $product = makeSubscribableProduct();
    $subscription = Subscription::query()->create([
        'number' => 'SUB-OWNERSHIP-1',
        'customer_id' => $owner->id,
        'customer_email' => $other->email,
        'customer_name' => $owner->name,
        'product_id' => $product->id,
        'status' => SubscriptionStatus::Active,
        'interval' => 'month',
        'interval_count' => 1,
        'price_amount' => $product->price_amount,
        'currency' => $product->currency,
        'quantity' => 1,
    ]);

    Livewire::actingAs($other->user)
        ->test(SubscriptionsIndex::class)
        ->assertDontSee($product->name);

    Livewire::actingAs($other->user)
        ->test(CustomerSubscriptionShow::class, ['subscription' => $subscription])
        ->assertStatus(404);
});

test('customer can undo period-end cancellation from subscription detail', function () {
    enableSubscriptionsModule();
    $customer = Customer::factory()->create();
    $product = makeSubscribableProduct();

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingForSubscription(),
    ]);
    app(RecordManualPayment::class)->handle($order, $this->createStaff());

    $subscription = Subscription::query()->firstOrFail();
    app(SubscriptionService::class)->cancel($subscription, atPeriodEnd: true);

    Livewire::actingAs($customer->user)
        ->test(CustomerSubscriptionShow::class, ['subscription' => $subscription->fresh()])
        ->assertSee($product->name)
        ->call('resume')
        ->assertHasNoErrors();

    expect($subscription->fresh()->cancel_at_period_end)->toBeFalse();
});

test('admin can create renewal order and payment advances period', function () {
    enableSubscriptionsModule();
    $customer = Customer::factory()->create();
    $product = makeSubscribableProduct();

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingForSubscription(),
    ]);
    app(RecordManualPayment::class)->handle($order, $this->createStaff());

    $subscription = Subscription::query()->firstOrFail();
    $periodEnd = $subscription->current_period_end?->toDateTimeString();

    $renewalOrder = app(SubscriptionService::class)->createRenewalOrder($subscription);
    expect($renewalOrder->total_amount)->toBe(1999)
        ->and(SubscriptionRenewal::query()->where('order_id', $renewalOrder->id)->value('status'))
        ->toBe(RenewalStatus::Pending);

    app(RecordManualPayment::class)->handle($renewalOrder, $this->createStaff());

    $subscription->refresh();
    expect(SubscriptionRenewal::query()->where('order_id', $renewalOrder->id)->value('status'))
        ->toBe(RenewalStatus::Paid)
        ->and(Subscription::query()->count())->toBe(1)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->current_period_start?->toDateTimeString())->toBe($periodEnd)
        ->and($subscription->current_period_end?->greaterThan($subscription->current_period_start))->toBeTrue();
});

test('subscriptions module disable preserves subscription rows', function () {
    enableSubscriptionsModule();
    $customer = Customer::factory()->create();
    $product = makeSubscribableProduct();

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingForSubscription(),
    ]);
    app(RecordManualPayment::class)->handle($order, $this->createStaff());

    expect(Subscription::query()->count())->toBe(1);

    app(ModuleManager::class)->disable('subscriptions');

    expect(app(ModuleManager::class)->isEnabled('subscriptions'))->toBeFalse()
        ->and(Subscription::query()->count())->toBe(1);
});

test('subscription-only cart does not require shipping', function () {
    enableSubscriptionsModule();
    $product = makeSubscribableProduct();
    app(CartService::class)->add($product->id, 1);

    expect(app(CartService::class)->requiresShipping())->toBeFalse();
});
