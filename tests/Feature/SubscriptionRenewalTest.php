<?php

declare(strict_types=1);

use Agovena\Modules\Subscriptions\Enums\RenewalStatus;
use Agovena\Modules\Subscriptions\Enums\SubscriptionStatus;
use Agovena\Modules\Subscriptions\Models\Subscription;
use Agovena\Modules\Subscriptions\Models\SubscriptionRenewal;
use Agovena\Modules\Subscriptions\SubscriptionService;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Credits\CustomerCreditLedger;
use App\Agovena\Customer\AddressData;
use App\Agovena\Orders\CancelUnpaidOrder;
use App\Agovena\Orders\UnpaidOrderCancelSource;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\PlanChanges\RequestPlanChange;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPlanChange;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function enableSubscriptionsForRenewal(): void
{
    installAndEnableModule('subscriptions');
    app(SyncRegisteredPermissions::class)(force: true);
}

function renewalBilling(): AddressData
{
    return AddressData::fromArray([
        'name' => 'Renew Buyer',
        'line1' => 'Street 1',
        'city' => 'Amsterdam',
        'postal_code' => '1000 AA',
        'country' => 'NL',
    ]);
}

function paidSubscription(array $config = []): Subscription
{
    $customer = Customer::factory()->create();
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
        'billing' => renewalBilling(),
    ]);
    app(RecordManualPayment::class)->handle($order, test()->createStaff());

    return Subscription::query()->where('order_id', $order->id)->firstOrFail();
}

test('scheduler creates one renewal order per due period and is idempotent', function () {
    enableSubscriptionsForRenewal();
    $subscription = paidSubscription();
    $due = CarbonImmutable::parse($subscription->next_billing_at);

    $this->travelTo($due);

    $this->artisan('agovena:process-subscription-renewals')->assertSuccessful();
    expect(SubscriptionRenewal::query()->count())->toBe(1)
        ->and(Invoice::query()->where('order_id', SubscriptionRenewal::query()->firstOrFail()->order_id)->value('status'))
        ->toBe(InvoiceStatus::Issued);

    $this->artisan('agovena:process-subscription-renewals')->assertSuccessful();
    expect(SubscriptionRenewal::query()->count())->toBe(1);

    app(SubscriptionService::class)->processDue($due);
    expect(SubscriptionRenewal::query()->count())->toBe(1)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::PastDue);
});

test('scheduler consolidates active services into one prorated renewal invoice', function () {
    enableSubscriptionsForRenewal();
    $this->travelTo(CarbonImmutable::parse('2026-09-01 10:00:00'));

    $first = paidSubscription();
    $customer = $first->customer()->firstOrFail();
    $secondProduct = Product::factory()->active()->create(['price_amount' => 1999]);
    app(ProductCapabilityManager::class)->enable($secondProduct, 'subscribable', [
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    app(CartService::class)->add($secondProduct->id, 1);
    $secondOrder = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => renewalBilling(),
    ]);
    app(RecordManualPayment::class)->handle($secondOrder, test()->createStaff());
    $second = Subscription::query()->where('order_id', $secondOrder->id)->firstOrFail();
    $second->forceFill([
        'customer_email' => strtoupper((string) $second->customer_email),
        'current_period_start' => '2026-09-04 10:00:00',
        'current_period_end' => '2026-10-04 10:00:00',
        'next_billing_at' => '2026-10-04 10:00:00',
    ])->save();

    $due = CarbonImmutable::parse('2026-10-01 10:00:00');
    $this->travelTo($due);
    app(SubscriptionService::class)->processDue($due);

    $renewals = SubscriptionRenewal::query()->with('order')->orderBy('id')->get();
    $renewalOrder = Order::query()->whereKey($renewals->firstOrFail()->order_id)->with('items')->firstOrFail();

    expect($renewals)->toHaveCount(2)
        ->and(Order::query()->where('idempotency_key', 'like', 'consolidated-renewal:%')->count())->toBe(1)
        ->and(Invoice::query()->where('order_id', $renewalOrder->id)->count())->toBe(1)
        ->and($renewalOrder->items)->toHaveCount(2)
        ->and($renewalOrder->due_at?->toDateString())->toBe($due->toDateString())
        ->and($renewalOrder->total_amount)->toBe(3798);

    app(RecordManualPayment::class)->handle($renewalOrder, $this->createStaff());

    expect(SubscriptionRenewal::query()->where('status', RenewalStatus::Paid)->count())->toBe(2)
        ->and($first->fresh()->current_period_start?->toDateString())->toBe($due->toDateString())
        ->and($second->fresh()->current_period_start?->toDateString())->toBe($due->toDateString());
});
test('scheduler ends a subscription that is cancelled at period end instead of renewing', function () {
    enableSubscriptionsForRenewal();
    $subscription = paidSubscription();
    app(SubscriptionService::class)->cancel($subscription, atPeriodEnd: true);

    $this->travelTo(CarbonImmutable::parse($subscription->current_period_end));
    $this->artisan('agovena:process-subscription-renewals')->assertSuccessful();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Ended)
        ->and(SubscriptionRenewal::query()->count())->toBe(0);
});

test('cancelling an unpaid renewal order cancels its pending renewals', function () {
    enableSubscriptionsForRenewal();
    $subscription = paidSubscription();
    $this->travelTo(CarbonImmutable::parse($subscription->next_billing_at));

    app(SubscriptionService::class)->processDue();
    $renewal = SubscriptionRenewal::query()->firstOrFail();
    $order = $renewal->order()->with(['invoices', 'payment'])->firstOrFail();

    app(CancelUnpaidOrder::class)->handle($order, UnpaidOrderCancelSource::Customer);

    expect($renewal->fresh()->status)->toBe(RenewalStatus::Cancelled)
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

test('paying a scheduled renewal advances the period', function () {
    enableSubscriptionsForRenewal();
    $subscription = paidSubscription();
    $periodEnd = $subscription->current_period_end?->toDateTimeString();
    $this->travelTo(CarbonImmutable::parse($subscription->next_billing_at));

    app(SubscriptionService::class)->processDue();
    $renewal = SubscriptionRenewal::query()->firstOrFail();
    app(RecordManualPayment::class)->handle($renewal->order, $this->createStaff());

    $subscription->refresh();
    expect($renewal->fresh()->status)->toBe(RenewalStatus::Paid)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->current_period_start?->toDateTimeString())->toBe($periodEnd);
});

test('a due renewal is automatically paid from full customer credit', function () {
    enableSubscriptionsForRenewal();
    $subscription = paidSubscription();
    $customer = $subscription->customer()->firstOrFail();
    app(CustomerCreditLedger::class)->credit($customer, 1999, 'test_credit');

    $this->travelTo(CarbonImmutable::parse($subscription->next_billing_at));
    app(SubscriptionService::class)->processDue();

    $renewal = SubscriptionRenewal::query()->firstOrFail()->fresh();
    $order = $renewal->order()->with('payment')->firstOrFail();
    expect($renewal->status)->toBe(RenewalStatus::Paid)
        ->and($order->status)->toBe(OrderStatus::Paid)
        ->and($order->payment?->method)->toBe('account_balance')
        ->and(app(CustomerCreditLedger::class)->available($customer))->toBe(0);
});

test('credit reservations roll back when renewal settlement fails', function () {
    enableSubscriptionsForRenewal();
    $subscription = paidSubscription();
    $customer = $subscription->customer;
    $this->travelTo(CarbonImmutable::parse($subscription->next_billing_at));
    app(SubscriptionService::class)->processDue();

    $renewal = $subscription->renewals()->latest('id')->firstOrFail();
    $renewal->order->invoices->firstOrFail()->update(['status' => InvoiceStatus::Void]);

    $ledger = app(CustomerCreditLedger::class);
    $ledger->credit($customer, 1999, 'test_credit');
    $before = $ledger->available($customer, $subscription->currency);

    expect(fn () => app(SubscriptionService::class)->processDue())
        ->toThrow(ValidationException::class);

    expect($ledger->available($customer, $subscription->currency))->toBe($before);
});

test('next-period plan change applies before the renewal order is created', function () {
    enableSubscriptionsForRenewal();
    $subscription = paidSubscription();
    $from = $subscription->product()->firstOrFail();
    $to = Product::factory()->active()->create(['price_amount' => 2999, 'currency' => $from->currency]);
    ProductPlanChange::query()->create([
        'from_product_id' => $from->id,
        'to_product_id' => $to->id,
        'change_type' => 'upgrade',
        'timing' => 'next_period',
        'is_active' => true,
        'sort' => 0,
    ]);

    app(RequestPlanChange::class)->handle(
        $subscription->customer()->firstOrFail(),
        $from,
        $to,
        $subscription->id,
    );

    $this->travelTo(CarbonImmutable::parse($subscription->next_billing_at));
    app(SubscriptionService::class)->processDue();

    $subscription->refresh();
    $renewalOrder = SubscriptionRenewal::query()->firstOrFail()->order;

    expect($subscription->product_id)->toBe($to->id)
        ->and($subscription->price_amount)->toBe(2999)
        ->and($renewalOrder->total_amount)->toBe(2999)
        ->and($renewalOrder->status)->toBe(OrderStatus::Pending);
});
