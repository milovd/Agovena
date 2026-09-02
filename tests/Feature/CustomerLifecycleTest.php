<?php

declare(strict_types=1);

use Agovena\Modules\Inventory\InventoryService;
use Agovena\Modules\Inventory\Models\InventoryStock;
use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Http\Livewire\Customer\ServiceShow;
use Agovena\Modules\Provisioning\Http\Livewire\Customer\ServicesIndex;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Provisioning\ProvisioningService;
use Agovena\Modules\Shipping\Enums\ShipmentStatus;
use Agovena\Modules\Shipping\Enums\ShippingMethodType;
use Agovena\Modules\Shipping\Models\Shipment;
use Agovena\Modules\Shipping\Models\ShippingMethod;
use Agovena\Modules\Subscriptions\Enums\SubscriptionStatus;
use Agovena\Modules\Subscriptions\Models\Subscription;
use Agovena\Modules\Subscriptions\SubscriptionService;
use App\Agovena\Auth\ConfirmsRecentPassword;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Orders\CancelUnpaidOrder;
use App\Agovena\Orders\UnpaidOrderCancelSource;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Settings\SettingsRepository;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Admin\Customers\Show as AdminCustomerShow;
use App\Livewire\Admin\Orders\Show as AdminOrderShow;
use App\Livewire\Customer\Account\OrderShow;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function enableLifecycleModules(array $ids): void
{
    installAndEnableModules($ids);
    app(SyncRegisteredPermissions::class)(force: true);
}

function lifecycleBilling(): AddressData
{
    return AddressData::fromArray([
        'name' => 'Lifecycle Buyer',
        'line1' => 'Street 1',
        'city' => 'Amsterdam',
        'postal_code' => '1000 AA',
        'country' => 'NL',
    ]);
}

test('customer can cancel an unpaid order which voids the invoice and restocks inventory', function () {
    enableLifecycleModules(['inventory']);
    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 1500]);
    app(ProductCapabilityManager::class)->enable($product, 'physical');
    app(ProductCapabilityManager::class)->enable($product, 'inventory');
    app(InventoryService::class)->setQuantity($product, 5);

    app(CartService::class)->add($product->id, 2);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => lifecycleBilling(),
    ]);

    expect(InventoryStock::query()->where('product_id', $product->id)->value('quantity'))->toBe(3);

    Livewire::actingAs($customer->user)
        ->test(OrderShow::class, ['order' => $order])
        ->call('cancelUnpaid')
        ->assertHasNoErrors()
        ->assertSee(__('customer.account.order_cancelled'), false);

    $order = $order->fresh(['invoices', 'payment']);
    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->isAwaitingPayment())->toBeFalse()
        ->and($order->invoices->first()?->status)->toBe(InvoiceStatus::Void)
        ->and($order->payment?->status)->toBe(PaymentStatus::Cancelled)
        ->and(InventoryStock::query()->where('product_id', $product->id)->value('quantity'))->toBe(5)
        ->and(AuditLog::query()->where('action', 'order.cancelled')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'invoice.voided')->exists())->toBeTrue();

    expect(fn () => app(RecordManualPayment::class)->handle($order, $this->createStaff()))
        ->toThrow(ValidationException::class);
});

test('cancelling an unpaid shippable order cancels pending shipments only', function () {
    enableLifecycleModules(['shipping']);
    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 2000]);
    app(ProductCapabilityManager::class)->enable($product, 'physical');
    app(ProductCapabilityManager::class)->enable($product, 'shippable', ['weight_grams' => 500]);
    $method = ShippingMethod::query()->create([
        'name' => 'Standard',
        'code' => 'standard-lifecycle',
        'type' => ShippingMethodType::Flat,
        'config' => ['amount' => 695],
        'currency' => 'EUR',
        'is_active' => true,
        'sort' => 10,
    ]);

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => lifecycleBilling(),
        'shipping_same_as_billing' => true,
        'shipping_method_id' => $method->id,
    ]);

    expect(Shipment::query()->where('order_id', $order->id)->value('status'))->toBe(ShipmentStatus::Pending);

    app(CancelUnpaidOrder::class)->handle($order, UnpaidOrderCancelSource::Customer);

    expect(Shipment::query()->where('order_id', $order->id)->value('status'))->toBe(ShipmentStatus::Cancelled)
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

test('paid orders cannot be cancelled as unpaid', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 900]);
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => lifecycleBilling(),
    ]);
    app(RecordManualPayment::class)->handle($order, $this->createStaff());

    expect(fn () => app(CancelUnpaidOrder::class)->handle($order->fresh(['invoices', 'payment']), UnpaidOrderCancelSource::Customer))
        ->toThrow(ValidationException::class);
});

test('staff without cancel or void permission cannot cancel an unpaid order', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 1100]);
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => lifecycleBilling(),
    ]);

    $staff = $this->createStaff([], ['orders.view']);

    Livewire::actingAs($staff)
        ->test(AdminOrderShow::class, ['order' => $order])
        ->call('cancelUnpaid')
        ->assertForbidden();
});

test('staff unpaid cancel requires recent password confirmation', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 1100]);
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => lifecycleBilling(),
    ]);

    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(AdminOrderShow::class, ['order' => $order])
        ->call('cancelUnpaid')
        ->assertSet('showingPasswordConfirmation', true);

    expect($order->fresh()->status->value)->toBe('pending');

    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $staff->id,
    ]);

    Livewire::actingAs($staff)
        ->test(AdminOrderShow::class, ['order' => $order])
        ->call('cancelUnpaid')
        ->assertHasNoErrors()
        ->assertSet('showingPasswordConfirmation', false);

    expect($order->fresh()->status->value)->toBe('cancelled');
});

test('stale unpaid orders are cancelled only when the grace setting is enabled', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 800]);
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => lifecycleBilling(),
    ]);
    $order->forceFill(['created_at' => now()->subDays(3)])->save();

    $this->artisan('agovena:cancel-stale-unpaid-orders')->assertSuccessful();
    expect($order->fresh()->status)->toBe(OrderStatus::Pending);

    app(SettingsRepository::class)->set('store', 'unpaid_order_cancel_after_days', 1);
    $this->artisan('agovena:cancel-stale-unpaid-orders')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(Invoice::query()->where('order_id', $order->id)->first()?->status)->toBe(InvoiceStatus::Void);
});

test('customer can open a service detail page', function () {
    enableLifecycleModules(['provisioning']);
    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 5000, 'name' => 'Hosted Box']);
    app(ProductCapabilityManager::class)->enable($product, 'provisionable');

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => lifecycleBilling(),
    ]);
    app(RecordManualPayment::class)->handle($order, $this->createStaff());
    $instance = ServiceInstance::query()->firstOrFail();
    app(ProvisioningService::class)->activate($instance, 'box-1');

    Livewire::actingAs($customer->user)
        ->test(ServiceShow::class, ['instance' => $instance->fresh()])
        ->assertOk()
        ->assertSee('Hosted Box')
        ->assertSee('box-1')
        ->assertSee($order->number);
});

test('service list does not use email fallback for instances with a customer id', function () {
    enableLifecycleModules(['provisioning']);
    $owner = Customer::factory()->create();
    $intruder = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 5000, 'name' => 'Private Service']);
    app(ProductCapabilityManager::class)->enable($product, 'provisionable');

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $owner->name,
        'customer_email' => $owner->email,
        'customer_id' => $owner->id,
        'billing' => lifecycleBilling(),
    ]);
    app(RecordManualPayment::class)->handle($order, $this->createStaff());
    $instance = ServiceInstance::query()->firstOrFail();
    $instance->forceFill(['customer_email' => $intruder->email])->save();

    Livewire::actingAs($intruder->user)
        ->test(ServicesIndex::class)
        ->assertOk()
        ->assertViewHas('instances', fn ($instances): bool => $instances->doesntContain('id', $instance->id));
});

test('immediate subscription cancel suspends linked active services but period-end cancel does not', function () {
    enableLifecycleModules(['subscriptions', 'provisioning']);
    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 4000, 'name' => 'VPS Plan']);
    $capabilities = app(ProductCapabilityManager::class);
    $capabilities->enable($product, 'subscribable', [
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);
    $capabilities->enable($product, 'provisionable', ['provider_key' => 'manual']);

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => lifecycleBilling(),
    ]);
    app(RecordManualPayment::class)->handle($order, $this->createStaff());

    $subscription = Subscription::query()->firstOrFail();
    $instance = ServiceInstance::query()->firstOrFail();
    app(ProvisioningService::class)->activate($instance);

    app(SubscriptionService::class)->cancel($subscription, atPeriodEnd: true);
    expect($instance->fresh()->status)->toBe(ServiceInstanceStatus::Active)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->fresh()->cancel_at_period_end)->toBeTrue();

    app(SubscriptionService::class)->resume($subscription->fresh());
    app(SubscriptionService::class)->cancel($subscription->fresh(), atPeriodEnd: false);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($instance->fresh()->status)->toBe(ServiceInstanceStatus::Suspended);
});

test('admin customer show lists orders invoices subscriptions and services', function () {
    enableLifecycleModules(['subscriptions', 'provisioning']);
    $customer = Customer::factory()->create(['name' => '360 Customer']);
    $product = Product::factory()->active()->create(['price_amount' => 2500, 'name' => '360 Plan']);
    $capabilities = app(ProductCapabilityManager::class);
    $capabilities->enable($product, 'subscribable', [
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);
    $capabilities->enable($product, 'provisionable');

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => lifecycleBilling(),
    ]);
    app(RecordManualPayment::class)->handle($order, $this->createStaff());

    $subscription = Subscription::query()->firstOrFail();
    $instance = ServiceInstance::query()->firstOrFail();
    $invoice = Invoice::query()->where('order_id', $order->id)->firstOrFail();

    $page = Livewire::actingAs($this->createStaff())
        ->test(AdminCustomerShow::class, ['customer' => $customer])
        ->assertOk()
        ->assertSee($order->number)
        ->assertSee($invoice->number)
        ->assertSee(__('admin.customers.activity_heading'), false)
        ->assertSee($subscription->number)
        ->assertSee($instance->number);
});
