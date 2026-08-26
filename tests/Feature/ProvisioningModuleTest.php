<?php

declare(strict_types=1);

use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Http\Livewire\Admin\InstanceShow;
use Agovena\Modules\Provisioning\Http\Livewire\Customer\ServiceShow;
use Agovena\Modules\Provisioning\Http\Livewire\Customer\ServicesIndex;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Provisioning\ProvisioningService;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Customer\CustomerAccountNav;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Provisioning\RunProvisionerAction;
use App\Models\Customer;
use App\Models\Product;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function enableProvisioningModule(): void
{
    installAndEnableModule('provisioning');
    app(SyncRegisteredPermissions::class)(force: true);
}

function billingForProvisioning(): AddressData
{
    return AddressData::fromArray([
        'name' => 'Service Buyer',
        'line1' => 'Street 1',
        'city' => 'Amsterdam',
        'postal_code' => '1000 AA',
        'country' => 'NL',
    ]);
}

function makeProvisionableProduct(array $config = [], array $attrs = []): Product
{
    $product = Product::factory()->active()->create(array_merge(['price_amount' => 5000], $attrs));
    app(ProductCapabilityManager::class)->enable($product, 'provisionable', $config);

    return $product->fresh(['capabilities']);
}

test('provisioning module registers capability and account nav', function () {
    expect(app(ProductCapabilityRegistry::class)->has('provisionable'))->toBeFalse();

    enableProvisioningModule();

    expect(app(ProductCapabilityRegistry::class)->has('provisionable'))->toBeTrue()
        ->and(collect(app(CustomerAccountNav::class)->items())->pluck('id')->all())
        ->toContain('services');
});

test('paid provisionable order creates pending service instances per quantity', function () {
    enableProvisioningModule();
    $customer = Customer::factory()->create();
    $product = makeProvisionableProduct(['provider_key' => 'manual']);

    app(CartService::class)->add($product->id, 2);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingForProvisioning(),
    ]);

    expect(ServiceInstance::query()->count())->toBe(0);

    app(RecordManualPayment::class)->handle($order, $this->createStaff());

    $instances = ServiceInstance::query()->where('order_id', $order->id)->get();
    expect($instances)->toHaveCount(2)
        ->and($instances->every(fn ($i) => $i->status === ServiceInstanceStatus::Pending))->toBeTrue()
        ->and($instances->first()->provider_key)->toBe('manual');
});

test('admin can activate suspend and terminate a service instance', function () {
    enableProvisioningModule();
    $customer = Customer::factory()->create();
    $product = makeProvisionableProduct();

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingForProvisioning(),
    ]);
    app(RecordManualPayment::class)->handle($order, $this->createStaff());

    $instance = ServiceInstance::query()->firstOrFail();
    $service = app(ProvisioningService::class);

    $instance = $service->markProvisioning($instance);
    expect($instance->status)->toBe(ServiceInstanceStatus::Provisioning);

    $instance = $service->activate($instance, 'ext-123');
    expect($instance->status)->toBe(ServiceInstanceStatus::Active)
        ->and($instance->external_ref)->toBe('ext-123');

    $instance = $service->suspend($instance);
    expect($instance->status)->toBe(ServiceInstanceStatus::Suspended);

    $instance = $service->activate($instance);
    expect($instance->status)->toBe(ServiceInstanceStatus::Active);

    $instance = $service->terminate($instance);
    expect($instance->status)->toBe(ServiceInstanceStatus::Terminated);
});

test('customer portal lists service instances', function () {
    enableProvisioningModule();
    $customer = Customer::factory()->create();
    $product = makeProvisionableProduct();

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingForProvisioning(),
    ]);
    app(RecordManualPayment::class)->handle($order, $this->createStaff());
    app(ProvisioningService::class)->activate(ServiceInstance::query()->firstOrFail(), 'portal-ref');

    Livewire::actingAs($customer->user)
        ->test(ServicesIndex::class)
        ->assertSee($product->name)
        ->assertSee('portal-ref')
        ->assertSee(__('provisioning::status.active'));
});

test('manual provisioner exposes and safely runs its proof action', function () {
    enableProvisioningModule();
    $customer = Customer::factory()->create();
    $product = makeProvisionableProduct(['provider_key' => 'manual']);

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingForProvisioning(),
    ]);
    app(RecordManualPayment::class)->handle($order, $this->createStaff());
    $instance = ServiceInstance::query()->firstOrFail();

    app(RunProvisionerAction::class)->handle($customer, $instance->id, 'refresh_status');

    Livewire::actingAs($customer->user)
        ->test(ServiceShow::class, ['instance' => $instance])
        ->assertSee(__('notifications.provisioning.refresh_status'))
        ->call('runAction', 'refresh_status')
        ->assertHasNoErrors();
});

test('provisioning module disable preserves service instances', function () {
    enableProvisioningModule();
    $customer = Customer::factory()->create();
    $product = makeProvisionableProduct();

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingForProvisioning(),
    ]);
    app(RecordManualPayment::class)->handle($order, $this->createStaff());

    expect(ServiceInstance::query()->count())->toBe(1);

    app(ModuleManager::class)->disable('provisioning');

    expect(app(ModuleManager::class)->isEnabled('provisioning'))->toBeFalse()
        ->and(ServiceInstance::query()->count())->toBe(1);
});

test('subscriptions disabled does not break provisioning', function () {
    enableProvisioningModule();
    expect(app(ModuleManager::class)->isEnabled('subscriptions'))->toBeFalse();

    $customer = Customer::factory()->create();
    $product = makeProvisionableProduct();
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingForProvisioning(),
    ]);
    app(RecordManualPayment::class)->handle($order, $this->createStaff());

    expect(ServiceInstance::query()->where('order_id', $order->id)->count())->toBe(1);
});

test('staff can send a failed service to manual review', function () {
    enableProvisioningModule();
    $staff = $this->createStaff(permissions: ['provisioning.view', 'provisioning.manage']);
    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-ADMIN-REVIEW',
        'status' => ServiceInstanceStatus::Failed,
        'customer_email' => 'admin-review@example.test',
        'failure_message' => 'Provider response requires review.',
    ]);

    Livewire::actingAs($staff)
        ->test(InstanceShow::class, ['instance' => $instance])
        ->call('markManualReview')
        ->assertSee(__('provisioning::admin.manual_reviewed'));

    expect($instance->fresh()->status)->toBe(ServiceInstanceStatus::ManualReview);
});
