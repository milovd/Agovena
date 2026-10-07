<?php

declare(strict_types=1);

use Agovena\Modules\Provisioning\CapacityReservationService;
use Agovena\Modules\Provisioning\EloquentProvisionedServiceResolver;
use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Http\Livewire\Admin\InstanceShow;
use Agovena\Modules\Provisioning\Http\Livewire\Customer\ServiceShow;
use Agovena\Modules\Provisioning\Http\Livewire\Customer\ServicesIndex;
use Agovena\Modules\Provisioning\Models\CapacityReservation;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Provisioning\ProvisioningOrchestrator;
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
use App\Agovena\Provisioning\Contracts\Provisioner;
use App\Agovena\Provisioning\Contracts\ProvisionerLifecycle;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Agovena\Provisioning\RunProvisionerAction;
use App\Agovena\Provisioning\ServiceInstanceInfo;
use App\Agovena\Security\OrderItemRuntimeSecretStore;
use App\Events\OrderPreflight;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProvisioningServer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

test('provisioning settings are stored outside order item snapshots', function () {
    enableProvisioningModule();
    $customer = Customer::factory()->create();
    $server = ProvisioningServer::query()->create([
        'name' => 'Runtime server',
        'provider_key' => 'manual',
        'settings' => ['endpoint' => 'https://provider.invalid', 'api_token' => '[REDACTED]'],
        'is_active' => true,
    ]);
    $product = makeProvisionableProduct([
        'provider_key' => 'manual',
        'server_id' => $server->id,
    ]);
    $order = Order::factory()->create(['customer_id' => $customer->id]);
    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'options_snapshot' => [],
    ]);
    $order->setRelation('items', collect([$item]));
    $preflight = new OrderPreflight([]);
    $preflight->checks[0] = [
        'product_id' => $product->id,
        'provisionable' => true,
        'provider_key' => 'manual',
        'server_id' => $server->id,
        'server_settings' => ['endpoint' => 'https://provider.invalid', 'api_token' => '[REDACTED]'],
        'provider_settings' => ['api_token' => '[REDACTED]'],
        'requirements' => [],
    ];

    app(ProvisioningService::class)->snapshotOrderConfiguration($order, $preflight);

    $storedItem = OrderItem::query()->findOrFail($item->id);
    $runtimeValues = DB::table('order_item_runtime_secrets')
        ->where('order_item_id', $item->id)
        ->pluck('value_encrypted', 'key');

    expect($storedItem->provisioning_server_settings_snapshot)->toBeNull()
        ->and($storedItem->provisioning_provider_settings_snapshot)->toBeNull()
        ->and($runtimeValues)->toHaveKeys([
            'provisioning_server_settings',
            'provisioning_provider_settings',
        ])
        ->and(Crypt::decryptString((string) $runtimeValues['provisioning_server_settings']))
        ->toBe(json_encode(['endpoint' => 'https://provider.invalid', 'api_token' => '[REDACTED]'], JSON_THROW_ON_ERROR))
        ->and(Crypt::decryptString((string) $runtimeValues['provisioning_provider_settings']))
        ->toBe(json_encode(['api_token' => '[REDACTED]'], JSON_THROW_ON_ERROR));
});

test('service instance settings stay out of persistent snapshots', function () {
    enableProvisioningModule();
    $customer = Customer::factory()->create();
    $server = ProvisioningServer::query()->create([
        'name' => 'Runtime service server',
        'provider_key' => 'manual',
        'settings' => ['endpoint' => 'https://provider.invalid', 'api_token' => '[REDACTED]'],
        'is_active' => true,
    ]);
    $product = makeProvisionableProduct([
        'provider_key' => 'manual',
        'server_id' => $server->id,
    ]);
    $order = Order::factory()->create(['customer_id' => $customer->id]);
    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'options_snapshot' => [],
    ]);
    $order->setRelation('items', collect([$item]));
    $preflight = new OrderPreflight([]);
    $preflight->checks[0] = [
        'product_id' => $product->id,
        'provisionable' => true,
        'provider_key' => 'manual',
        'server_id' => $server->id,
        'server_settings' => ['endpoint' => 'https://provider.invalid', 'api_token' => '[REDACTED]'],
        'provider_settings' => ['api_token' => '[REDACTED]'],
        'requirements' => [],
    ];

    $service = app(ProvisioningService::class);
    $service->snapshotOrderConfiguration($order, $preflight);
    $service->createFromPaidOrder($order);
    $instance = ServiceInstance::query()->where('order_id', $order->id)->firstOrFail();
    $runtime = DB::table('service_instance_runtime_secrets')->where('service_instance_id', $instance->id)->first();

    expect($instance->server_settings_snapshot)->toBeNull()
        ->and($instance->provider_settings_snapshot)->toBeNull()
        ->and($runtime)->not->toBeNull()
        ->and(Crypt::decryptString((string) $runtime->server_settings_encrypted))
        ->toBe(json_encode(['endpoint' => 'https://provider.invalid', 'api_token' => '[REDACTED]'], JSON_THROW_ON_ERROR))
        ->and(Crypt::decryptString((string) $runtime->provider_settings_encrypted))
        ->toBe(json_encode(['api_token' => '[REDACTED]'], JSON_THROW_ON_ERROR));
});

test('empty service reconciliation removes prior runtime settings', function () {
    enableProvisioningModule();
    $customer = Customer::factory()->create();
    $server = ProvisioningServer::query()->create([
        'name' => 'Cleanup server',
        'provider_key' => 'manual',
        'settings' => ['endpoint' => 'https://provider.invalid', 'api_token' => '[REDACTED]'],
        'is_active' => true,
    ]);
    $product = makeProvisionableProduct([
        'provider_key' => 'manual',
        'server_id' => $server->id,
    ]);
    $order = Order::factory()->create(['customer_id' => $customer->id]);
    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'options_snapshot' => [],
    ]);
    $order->setRelation('items', collect([$item]));
    $preflight = new OrderPreflight([]);
    $preflight->checks[0] = [
        'product_id' => $product->id,
        'provisionable' => true,
        'provider_key' => 'manual',
        'server_id' => $server->id,
        'server_settings' => ['endpoint' => 'https://provider.invalid', 'api_token' => '[REDACTED]'],
        'provider_settings' => ['api_token' => '[REDACTED]'],
        'requirements' => [],
    ];

    $service = app(ProvisioningService::class);
    $service->snapshotOrderConfiguration($order, $preflight);
    $service->createFromPaidOrder($order);
    $instance = ServiceInstance::query()->where('order_id', $order->id)->firstOrFail();
    expect(DB::table('service_instance_runtime_secrets')->where('service_instance_id', $instance->id)->exists())->toBeTrue();

    $item->update(['quantity' => 2, 'options_snapshot' => []]);
    $runtimeSecrets = app(OrderItemRuntimeSecretStore::class);
    $runtimeSecrets->forget($item->id, 'provisioning_server_settings');
    $runtimeSecrets->forget($item->id, 'provisioning_provider_settings');
    $service->createFromPaidOrder($order->fresh());

    expect(DB::table('service_instance_runtime_secrets')->where('service_instance_id', $instance->id)->exists())->toBeFalse();
});

test('manual-review provisioning retains its existing capacity reservation', function () {
    enableProvisioningModule();
    $customer = Customer::factory()->create();
    $product = makeProvisionableProduct(['provider_key' => 'unavailable-provider']);
    $order = Order::factory()->create(['customer_id' => $customer->id]);
    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'options_snapshot' => [
            '__provisioning' => [
                'provider_key' => 'unavailable-provider',
                'capacity_key' => 'unavailable-provider:pool',
                'requirements' => ['memory' => 1024],
                'provider_settings' => [],
            ],
        ],
    ]);
    $order->setRelation('items', collect([$item]));
    app(CapacityReservationService::class)->reserve(
        $order,
        $product,
        'unavailable-provider',
        'unavailable-provider:pool',
        1,
        ['memory' => 1024],
        orderItemId: $item->id,
    );

    app(ProvisioningService::class)->createFromPaidOrder($order);

    expect(ServiceInstance::query()->where('order_id', $order->id)->firstOrFail()->status)
        ->toBe(ServiceInstanceStatus::ManualReview)
        ->and(CapacityReservation::query()->where('order_id', $order->id)->value('quantity'))
        ->toBe(1);
});

test('paid non-provisionable order does not create service instances', function () {
    enableProvisioningModule();
    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 5000]);

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingForProvisioning(),
    ]);
    app(RecordManualPayment::class)->handle($order, $this->createStaff());

    expect(ServiceInstance::query()->where('order_id', $order->id)->count())->toBe(0);
});

test('an unavailable configured provisioning server blocks checkout', function () {
    enableProvisioningModule();
    $customer = Customer::factory()->create();
    $server = ProvisioningServer::query()->create([
        'name' => 'Inactive server',
        'provider_key' => 'manual',
        'settings' => ['api_url' => 'https://provider.invalid', 'api_token' => '[REDACTED]'],
        'is_active' => false,
    ]);
    $product = makeProvisionableProduct([
        'provider_key' => 'manual',
        'server_id' => $server->id,
    ]);

    app(CartService::class)->add($product->id, 1);
    expect(fn () => app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingForProvisioning(),
    ]))->toThrow(ValidationException::class)
        ->and(ServiceInstance::query()->count())->toBe(0);
});

test('a service instance without customer ownership is not authorized by matching email', function () {
    enableProvisioningModule();
    $customer = Customer::factory()->create(['email' => 'shared@example.test']);
    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-AUTHZ-001',
        'status' => ServiceInstanceStatus::Active,
        'provider_key' => 'manual',
        'customer_id' => null,
        'customer_email' => $customer->email,
    ]);

    expect(app(EloquentProvisionedServiceResolver::class)->resolveForCustomer($customer, $instance->id))->toBeNull();
});

test('a populated service customer id cannot be bypassed by a matching email', function () {
    enableProvisioningModule();
    $owner = Customer::factory()->create(['email' => 'service-owner@example.test']);
    $other = Customer::factory()->create(['email' => 'service-other@example.test']);
    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-AUTHZ-002',
        'status' => ServiceInstanceStatus::Active,
        'provider_key' => 'manual',
        'customer_id' => $owner->id,
        'customer_email' => $other->email,
    ]);
    $method = new ReflectionMethod(ServiceShow::class, 'owns');
    $method->setAccessible(true);

    expect($method->invoke(new ServiceShow, $instance, $other))->toBeFalse();
});

test('polling preserves an existing provider reference when a provider returns the local id', function () {
    enableProvisioningModule();
    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-MAPPING-001',
        'status' => ServiceInstanceStatus::Provisioning,
        'provider_key' => 'fixture-mapping',
        'external_ref' => 'provider-reference-1',
        'customer_email' => 'mapping@example.test',
    ]);
    $provider = new class implements Provisioner, ProvisionerLifecycle
    {
        public function id(): string
        {
            return 'fixture-mapping';
        }

        public function label(): string
        {
            return 'Fixture mapping';
        }

        public function provision(ServiceInstanceInfo $instance): void {}

        public function poll(ServiceInstanceInfo $instance): ServiceInstanceInfo
        {
            return $this->syncStatus($instance);
        }

        public function activate(ServiceInstanceInfo $instance): void {}

        public function suspend(ServiceInstanceInfo $instance): void {}

        public function unsuspend(ServiceInstanceInfo $instance): void {}

        public function terminate(ServiceInstanceInfo $instance): void {}

        public function changePlan(ServiceInstanceInfo $instance, string|array $plan): void {}

        public function syncStatus(ServiceInstanceInfo $instance): ServiceInstanceInfo
        {
            return new ServiceInstanceInfo(
                id: $instance->id,
                label: $instance->label,
                status: 'active',
                providerKey: 'fixture-mapping',
                externalRef: 'agovena-fixture-mapping-'.$instance->id,
                meta: [],
            );
        }
    };
    app(ProvisionerRegistry::class)->register($provider);

    $updated = app(ProvisioningOrchestrator::class)->sync($instance);

    expect($updated->fresh()->external_ref)->toBe('provider-reference-1');
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
