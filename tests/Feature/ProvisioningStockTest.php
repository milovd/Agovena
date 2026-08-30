<?php

declare(strict_types=1);

use Agovena\Extensions\Convoy\HttpConvoyApi;
use Agovena\Extensions\CPanel\HttpCPanelApi;
use Agovena\Extensions\DirectAdmin\HttpDirectAdminApi;
use Agovena\Extensions\Enhance\HttpEnhanceApi;
use Agovena\Extensions\Plesk\HttpPleskApi;
use Agovena\Extensions\Pterodactyl\PterodactylApi;
use Agovena\Extensions\Pterodactyl\PterodactylProvisioner;
use Agovena\Extensions\Virtfusion\HttpVirtfusionApi;
use Agovena\Extensions\Virtualizor\HttpVirtualizorApi;
use Agovena\Modules\Provisioning\CapacityReservationService;
use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Listeners\CreateServiceInstancesWhenOrderPaid;
use Agovena\Modules\Provisioning\Listeners\KeepProvisioningCapacityWhenOrderPaid;
use Agovena\Modules\Provisioning\Models\CapacityReservation;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Provisioning\ProvisioningOrchestrator;
use Agovena\Modules\Provisioning\ProvisioningService;
use Agovena\Modules\Provisioning\Support\AbstractServerProvisioner;
use Agovena\Modules\Provisioning\Support\ServerApi;
use Agovena\Modules\Provisioning\Support\ServerProviderException;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingDefinition;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\PaymentGatewayCapabilities;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Provisioning\Contracts\ChecksProvisioningStock;
use App\Agovena\Provisioning\Contracts\ProvidesProvisioningCapacityRequirements;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Agovena\Provisioning\ServiceInstanceInfo;
use App\Enums\OrderStatus;
use App\Enums\ProductOptionType;
use App\Events\OrderPaid;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionChoice;
use App\Models\ProvisioningServer;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Validation\ValidationException;
use Tests\Support\FakePterodactylApi;

function enableStockPterodactyl(?FakePterodactylApi $api = null): FakePterodactylApi
{
    app(ModuleManager::class)->discover();
    app(ExtensionManager::class)->discover();
    $api ??= new FakePterodactylApi;
    app()->instance(PterodactylApi::class, $api);
    installAndEnableModule('provisioning');
    app(SyncRegisteredPermissions::class)(force: true);
    installAndEnableExtension('pterodactyl');

    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('pterodactyl', 'panel_url', 'https://panel.example.test');
    $settings->set('pterodactyl', 'application_api_key', '[REDACTED]', secret: true);
    $settings->set('pterodactyl', 'user_id', '1');

    return $api;
}

test('generic server provisioners remain explicitly unsupported until readback guarantees exist', function () {
    app(ModuleManager::class)->discover();
    app(ExtensionManager::class)->discover();
    installAndEnableModule('provisioning');
    installAndEnableExtension('cpanel');

    $api = Mockery::mock(ServerApi::class);
    $api->shouldReceive('withConnection')->andReturnSelf();
    $api->shouldReceive('findServerByExternalId')->andReturn(null);
    $api->shouldReceive('createServer')->andReturn([
        'id' => 1,
        'identifier' => 'unverified',
    ]);
    $provisioner = new class(app(ExtensionSettingsRepository::class), $api) extends AbstractServerProvisioner
    {
        public function id(): string
        {
            return 'generic-scaffold';
        }

        public function label(): string
        {
            return 'Generic scaffold';
        }

        public function serverSettings(): array
        {
            return [
                new ExtensionSettingDefinition('api_url', 'api_url', required: true),
                new ExtensionSettingDefinition('api_token', 'api_token', required: true),
            ];
        }

        public function productSettings(): array
        {
            return [];
        }

        protected function buildCreatePayload(ServiceInstanceInfo $instance, array $providerSettings, string $externalId): array
        {
            return ['name' => $instance->label, 'external_id' => $externalId] + $providerSettings;
        }
    };
    $instance = new ServiceInstanceInfo(
        id: 1,
        label: 'Unverified service',
        status: 'provisioning',
        providerKey: 'generic-scaffold',
        externalRef: null,
        meta: [],
        serverSettings: [
            'api_url' => 'https://generic.example.test',
            'api_token' => '[REDACTED]',
        ],
        providerSettings: [],
    );

    expect(fn () => $provisioner->provision($instance))
        ->toThrow(ValidationException::class);
});

function stockBilling(): AddressData
{
    return AddressData::fromArray([
        'name' => 'Stock Buyer',
        'line1' => 'Street 1',
        'city' => 'Amsterdam',
        'postal_code' => '1000 AA',
        'country' => 'NL',
    ]);
}

function stockProduct(): Product
{
    $product = Product::factory()->active()->create(['price_amount' => 5000]);
    app(ProductCapabilityManager::class)->enable($product, 'provisionable', [
        'provider_key' => 'pterodactyl',
        'provider_settings' => [
            'location_id' => '1',
            'nest_id' => '1',
            'egg_id' => '15',
            'memory' => '1024',
            'disk' => '2048',
        ],
    ]);

    return $product->fresh(['capabilities']);
}

test('order snapshots use effective provider option overrides for capacity', function () {
    enableStockPterodactyl();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('pending-test');
    $gateway->shouldReceive('label')->andReturn('Pending test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities);
    app(PaymentGatewayRegistry::class)->register($gateway);
    $product = stockProduct();
    $option = ProductOption::query()->create([
        'product_id' => $product->id,
        'key' => 'location_id',
        'label' => 'Location',
        'type' => ProductOptionType::Select,
        'is_required' => false,
        'is_active' => true,
        'sort' => 1,
        'price_adjustment_amount' => 0,
        'constraints' => [],
    ]);
    ProductOptionChoice::query()->create([
        'product_option_id' => $option->id,
        'value' => '2',
        'label' => 'Location 2',
        'price_adjustment_amount' => 0,
        'sort' => 1,
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create();
    app(CartService::class)->add($product->id, 1, ['location_id' => '2']);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => stockBilling(),
        'payment_method' => 'pending-test',
    ]);

    $snapshot = $order->fresh('items')->items->firstOrFail()->options_snapshot['__provisioning'] ?? null;
    expect($snapshot['provider_settings']['location_id'] ?? null)->toBe('2')
        ->and($snapshot['capacity_key'] ?? null)->toBe(
            app(PterodactylProvisioner::class)->capacityKeyForSettings(['location_id' => '2']),
        )
        ->and($snapshot['requirements'] ?? null)->toMatchArray(['memory' => 1024, 'disk' => 2048]);
});

test('order items receive an immutable provisioning snapshot at placement', function () {
    enableStockPterodactyl();
    $customer = Customer::factory()->create();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('pending-test');
    $gateway->shouldReceive('label')->andReturn('Pending test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities);
    app(PaymentGatewayRegistry::class)->register($gateway);
    $product = stockProduct();
    $capability = $product->capability('provisionable');
    $capabilityConfig = $capability->config;
    $capabilityConfig['provider_settings']['api_token'] = str_repeat('x', 16);
    $capability->config = $capabilityConfig;
    $capability->save();
    app(CartService::class)->add($product->id, 1);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => stockBilling(),
        'payment_method' => 'pending-test',
    ]);

    $snapshot = $order->fresh('items')->items->firstOrFail()->options_snapshot['__provisioning'] ?? null;
    expect($snapshot)->toBeArray()
        ->and($snapshot['provider_key'] ?? null)->toBe('pterodactyl')
        ->and($snapshot['provider_settings']['memory'] ?? null)->toBe('1024')
        ->and($snapshot['provider_settings']['api_token'] ?? null)->toBe('[REDACTED]');
});

test('plan capacity migration moves one unit without deleting sibling reservations', function () {
    enableStockPterodactyl();
    $product = stockProduct();
    $order = Order::factory()->create();
    $instance = ServiceInstance::query()->create([
        'number' => 'TEST-PLAN-0',
        'order_id' => $order->id,
        'product_id' => $product->id,
        'customer_email' => $order->customer_email,
        'status' => ServiceInstanceStatus::Active,
        'unit_index' => 0,
        'provider_key' => 'pterodactyl',
        'provider_settings_snapshot' => [
            'location_id' => '1',
            'nest_id' => '1',
            'egg_id' => '15',
            'memory' => '1024',
            'disk' => '2048',
        ],
        'meta' => [
            'provisioning_capacity_key' => 'pterodactyl:location-1',
            'provisioning_capacity_requirements' => ['memory' => 1024, 'disk' => 2048],
        ],
    ]);
    $reservations = app(CapacityReservationService::class);
    $reservations->reserve($order, $product, 'pterodactyl', 'pterodactyl:location-1', 2, ['memory' => 1024, 'disk' => 2048]);

    $reservations->revalidateAndReserveForInstance(
        $instance,
        static function (): void {},
        ['memory' => 2048, 'disk' => 4096],
        true,
        'pterodactyl:location-2',
        'pterodactyl:location-1',
        ['memory' => 1024, 'disk' => 2048],
    );
    $reservations->commitForInstance(
        $instance,
        'pterodactyl:location-2',
        ['memory' => 2048, 'disk' => 4096],
        'pterodactyl:location-1',
        ['memory' => 1024, 'disk' => 2048],
    );

    expect(CapacityReservation::query()->where('capacity_key', 'pterodactyl:location-1')->value('quantity'))->toBe(1)
        ->and(CapacityReservation::query()->where('capacity_key', 'pterodactyl:location-2')->exists())->toBeFalse();
});

test('plan capacity migration refuses a missing target reservation', function () {
    enableStockPterodactyl();
    $previousProduct = stockProduct();
    $targetProduct = stockProduct();
    $order = Order::factory()->create();
    $instance = ServiceInstance::query()->create([
        'number' => 'TEST-PLAN-MISSING-TARGET',
        'order_id' => $order->id,
        'product_id' => $targetProduct->id,
        'customer_email' => $order->customer_email,
        'status' => ServiceInstanceStatus::Active,
        'unit_index' => 0,
        'provider_key' => 'manual',
        'meta' => [
            'provisioning_capacity_key' => 'target-capacity',
            'provisioning_capacity_requirements' => ['memory' => 1024],
        ],
    ]);
    $reservations = app(CapacityReservationService::class);
    $reservations->reserve($order, $previousProduct, 'manual', 'previous-capacity', 1, ['memory' => 1024]);

    expect(fn () => $reservations->commitForInstance(
        $instance,
        'target-capacity',
        ['memory' => 1024],
        'previous-capacity',
        ['memory' => 1024],
        $previousProduct->id,
        'manual',
    ))->toThrow(ValidationException::class);

    expect(CapacityReservation::query()->where('capacity_key', 'previous-capacity')->value('quantity'))->toBe(1);
});

test('instance revalidation subtracts the full owned reservation quantity', function () {
    enableStockPterodactyl();
    $product = stockProduct();
    $order = Order::factory()->create();
    $instance = ServiceInstance::query()->create([
        'number' => 'TEST-REVALIDATE-QUANTITY',
        'order_id' => $order->id,
        'product_id' => $product->id,
        'customer_email' => $order->customer_email,
        'status' => ServiceInstanceStatus::Provisioning,
        'provider_key' => 'pterodactyl',
        'provider_settings_snapshot' => [
            'location_id' => '1',
            'nest_id' => '1',
            'egg_id' => '15',
            'memory' => '1024',
            'disk' => '2048',
        ],
        'meta' => [
            'provisioning_capacity_key' => 'pterodactyl:location-1',
            'provisioning_capacity_requirements' => ['memory' => 1024, 'disk' => 2048],
        ],
    ]);
    app(CapacityReservationService::class)->reserve(
        $order,
        $product,
        'pterodactyl',
        'pterodactyl:location-1',
        2,
        ['memory' => 1024, 'disk' => 2048],
    );
    $observedQuantity = null;

    app(CapacityReservationService::class)->revalidateAndReserveForInstance(
        $instance,
        static function (int $quantity) use (&$observedQuantity): void {
            $observedQuantity = $quantity;
        },
        ['memory' => 1024, 'disk' => 2048],
        true,
    );

    expect($observedQuantity)->toBe(0);
});

test('plan capacity migration removes a previous reservation with the previous product identity', function () {
    enableStockPterodactyl();
    $previousProduct = stockProduct();
    $targetProduct = stockProduct();
    $order = Order::factory()->create();
    $instance = ServiceInstance::query()->create([
        'number' => 'TEST-PLAN-IDENTITY',
        'order_id' => $order->id,
        'product_id' => $targetProduct->id,
        'customer_email' => $order->customer_email,
        'status' => ServiceInstanceStatus::Active,
        'unit_index' => 0,
        'provider_key' => 'pterodactyl',
        'provider_settings_snapshot' => [
            'location_id' => '1',
            'nest_id' => '1',
            'egg_id' => '15',
            'memory' => '1024',
            'disk' => '2048',
        ],
        'meta' => [
            'provisioning_capacity_key' => 'pterodactyl:location-2',
            'provisioning_capacity_requirements' => ['memory' => 2048, 'disk' => 4096],
        ],
    ]);
    $reservations = app(CapacityReservationService::class);
    $reservations->reserve($order, $previousProduct, 'pterodactyl', 'pterodactyl:location-1', 1, ['memory' => 1024, 'disk' => 2048]);

    $reservations->revalidateAndReserveForInstance(
        $instance,
        static function (): void {},
        ['memory' => 2048, 'disk' => 4096],
        true,
        'pterodactyl:location-2',
        'pterodactyl:location-1',
        ['memory' => 1024, 'disk' => 2048],
        $previousProduct->id,
        'pterodactyl',
    );
    $reservations->commitForInstance(
        $instance,
        'pterodactyl:location-2',
        ['memory' => 2048, 'disk' => 4096],
        'pterodactyl:location-1',
        ['memory' => 1024, 'disk' => 2048],
        $previousProduct->id,
        'pterodactyl',
    );

    expect(CapacityReservation::query()->where('product_id', $previousProduct->id)->exists())->toBeFalse()
        ->and(CapacityReservation::query()->where('product_id', $targetProduct->id)->exists())->toBeFalse();
});

test('paid provisioning uses the placement snapshot after product configuration changes', function () {
    $api = enableStockPterodactyl();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('pending-test');
    $gateway->shouldReceive('label')->andReturn('Pending test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities);
    app(PaymentGatewayRegistry::class)->register($gateway);
    $product = stockProduct();
    $customer = Customer::factory()->create();
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => stockBilling(),
        'payment_method' => 'pending-test',
    ]);

    app(ProductCapabilityManager::class)->enable($product, 'provisionable', [
        'provider_key' => 'missing-provider',
        'provider_settings' => ['memory' => '8192', 'disk' => '16384'],
    ]);

    app(ProvisioningService::class)->createFromPaidOrder($order->fresh(['items']));
    $instance = ServiceInstance::query()->where('order_id', $order->id)->firstOrFail();

    expect($instance->provider_key)->toBe('pterodactyl')
        ->and($instance->meta['provider_settings']['memory'] ?? null)->toBe('1024')
        ->and($api->createCalls)->toBe(1);
});

test('paid provisioning reconciles missing quantity units idempotently', function () {
    enableStockPterodactyl();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('pending-test');
    $gateway->shouldReceive('label')->andReturn('Pending test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities);
    app(PaymentGatewayRegistry::class)->register($gateway);
    $product = stockProduct();
    $customer = Customer::factory()->create();
    app(CartService::class)->add($product->id, 2);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => stockBilling(),
        'payment_method' => 'pending-test',
    ]);
    $item = $order->fresh('items')->items->firstOrFail();

    ServiceInstance::query()->create([
        'number' => 'TEST-UNIT-0',
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'product_id' => $product->id,
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
        'customer_name' => $customer->name,
        'status' => ServiceInstanceStatus::Pending,
        'unit_index' => 0,
        'provider_key' => 'pterodactyl',
        'provider_settings_snapshot' => [
            'location_id' => '1',
            'nest_id' => '1',
            'egg_id' => '15',
            'memory' => '1024',
            'disk' => '2048',
        ],
        'meta' => ['provisioning_capacity_key' => 'pterodactyl:location-1'],
    ]);

    app(ProvisioningService::class)->createFromPaidOrder($order->fresh(['items']));
    app(ProvisioningService::class)->createFromPaidOrder($order->fresh(['items']));

    expect(ServiceInstance::query()->where('order_item_id', $item->id)->count())->toBe(2)
        ->and(ServiceInstance::query()->where('order_item_id', $item->id)->pluck('unit_index')->sort()->values()->all())->toBe([0, 1]);
});

test('checkout rejects a malformed server selection instead of using global settings', function () {
    enableStockPterodactyl();
    $customer = Customer::factory()->create();
    $product = stockProduct();
    app(ProductCapabilityManager::class)->enable($product, 'provisionable', [
        'provider_key' => 'pterodactyl',
        'server_id' => 'not-an-integer',
        'provider_settings' => [
            'location_id' => '1',
            'nest_id' => '1',
            'egg_id' => '15',
            'memory' => '1024',
            'disk' => '2048',
        ],
    ]);
    app(CartService::class)->add($product->id, 1);

    expect(fn () => app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => stockBilling(),
        'payment_method' => 'development',
    ]))->toThrow(ValidationException::class)
        ->and(Order::query()->count())->toBe(0)
        ->and(CapacityReservation::query()->count())->toBe(0);
});
test('checkout fails closed when pterodactyl has no deployable capacity', function () {
    $api = enableStockPterodactyl();
    $api->deployableNodeCount = 0;
    $customer = Customer::factory()->create();
    $product = stockProduct();
    app(CartService::class)->add($product->id, 1);

    expect(fn () => app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => stockBilling(),
        'payment_method' => 'development',
    ]))->toThrow(ValidationException::class)
        ->and(Order::query()->count())->toBe(0)
        ->and($api->capacityCalls)->toBe(1);
});

test('checkout fails closed when no single pterodactyl node fits the vector', function () {
    $api = enableStockPterodactyl();
    $api->deployableNodeOverride = [];
    $customer = Customer::factory()->create();
    $product = stockProduct();
    app(CartService::class)->add($product->id, 1);

    expect(fn () => app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => stockBilling(),
        'payment_method' => 'development',
    ]))->toThrow(ValidationException::class)
        ->and(Order::query()->count())->toBe(0);
});

test('checkout reserves checked capacity so it cannot be sold twice', function () {
    $api = enableStockPterodactyl();
    $api->deployableNodeCount = 1;
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('pending-test');
    $gateway->shouldReceive('label')->andReturn('Pending test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities);
    app(PaymentGatewayRegistry::class)->register($gateway);
    $product = stockProduct();

    $first = Customer::factory()->create();
    app(CartService::class)->add($product->id, 1);
    $firstOrder = app(PlaceOrder::class)->handle([
        'customer_name' => $first->name,
        'customer_email' => $first->email,
        'customer_id' => $first->id,
        'billing' => stockBilling(),
        'payment_method' => 'pending-test',
    ]);

    $second = Customer::factory()->create();
    app(CartService::class)->add($product->id, 1);

    expect($firstOrder->status)->toBe(OrderStatus::Pending)
        ->and(fn () => app(PlaceOrder::class)->handle([
            'customer_name' => $second->name,
            'customer_email' => $second->email,
            'customer_id' => $second->id,
            'billing' => stockBilling(),
            'payment_method' => 'pending-test',
        ]))->toThrow(ValidationException::class)
        ->and(Order::query()->count())->toBe(1)
        ->and($api->capacityCalls)->toBe(2);
});

test('expired checkout capacity reservations are released lazily', function () {
    $api = enableStockPterodactyl();
    $api->deployableNodeCount = 1;
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('pending-test');
    $gateway->shouldReceive('label')->andReturn('Pending test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities);
    app(PaymentGatewayRegistry::class)->register($gateway);
    $product = stockProduct();
    $first = Customer::factory()->create();

    app(CartService::class)->add($product->id, 1);
    app(PlaceOrder::class)->handle([
        'customer_name' => $first->name,
        'customer_email' => $first->email,
        'customer_id' => $first->id,
        'billing' => stockBilling(),
        'payment_method' => 'pending-test',
    ]);
    CapacityReservation::query()->firstOrFail()->update(['expires_at' => now()->subMinute()]);

    $second = Customer::factory()->create();
    app(CartService::class)->add($product->id, 1);

    expect(fn () => app(PlaceOrder::class)->handle([
        'customer_name' => $second->name,
        'customer_email' => $second->email,
        'customer_id' => $second->id,
        'billing' => stockBilling(),
        'payment_method' => 'pending-test',
    ]))->not->toThrow(ValidationException::class)
        ->and(Order::query()->count())->toBe(2)
        ->and(CapacityReservation::query()->count())->toBe(1);
});

test('reserve purges expired reservations without an expected quantity', function () {
    enableStockPterodactyl();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('pending-test');
    $gateway->shouldReceive('label')->andReturn('Pending test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities);
    app(PaymentGatewayRegistry::class)->register($gateway);
    $product = stockProduct();
    $customer = Customer::factory()->create();

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => stockBilling(),
        'payment_method' => 'pending-test',
    ]);
    $reservation = CapacityReservation::query()->firstOrFail();
    $reservation->update(['expires_at' => now()->subMinute()]);

    app(CapacityReservationService::class)->reserve(
        $order,
        $product,
        'pterodactyl',
        $reservation->capacity_key,
        1,
    );

    expect(CapacityReservation::query()->count())->toBe(1)
        ->and(CapacityReservation::query()->firstOrFail()->expires_at)->not->toBeNull();
});

test('paid orders keep provisioning capacity reserved until release', function () {
    $api = enableStockPterodactyl();
    $api->deployableNodeCount = 1;
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('pending-test');
    $gateway->shouldReceive('label')->andReturn('Pending test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities);
    app(PaymentGatewayRegistry::class)->register($gateway);
    $product = stockProduct();
    $customer = Customer::factory()->create();

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => stockBilling(),
        'payment_method' => 'pending-test',
    ]);
    $reservation = CapacityReservation::query()->firstOrFail();

    $paidEvent = new OrderPaid($order->fresh(['items', 'payment']));
    app(KeepProvisioningCapacityWhenOrderPaid::class)->handle($paidEvent);

    expect($reservation->fresh()->expires_at)->toBeNull();

    app(CreateServiceInstancesWhenOrderPaid::class)->handle($paidEvent);

    expect(CapacityReservation::query()->count())->toBe(0);
});

test('paid items that lose provisioning capability release their checkout reservation', function () {
    enableStockPterodactyl();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('pending-test');
    $gateway->shouldReceive('label')->andReturn('Pending test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities);
    app(PaymentGatewayRegistry::class)->register($gateway);
    $product = stockProduct();
    $customer = Customer::factory()->create();
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => stockBilling(),
        'payment_method' => 'pending-test',
    ]);
    $reservation = CapacityReservation::query()->firstOrFail();
    CapacityReservation::query()->create([
        'order_id' => $reservation->order_id,
        'order_item_id' => $reservation->order_item_id,
        'product_id' => $reservation->product_id,
        'provider_key' => $reservation->provider_key,
        'capacity_key' => $reservation->capacity_key,
        'quantity' => 1,
        'requirements' => ['memory' => 999],
        'requirements_fingerprint' => 'legacy-different-fingerprint',
        'expires_at' => null,
    ]);
    app(ProductCapabilityManager::class)->disable($product, 'provisionable');
    $event = new OrderPaid($order->fresh(['items', 'payment']));

    app(KeepProvisioningCapacityWhenOrderPaid::class)->handle($event);
    app(CreateServiceInstancesWhenOrderPaid::class)->handle($event);

    expect(CapacityReservation::query()->count())->toBe(0)
        ->and(ServiceInstance::query()->where('order_id', $order->id)->count())->toBe(0);
});

test('ambiguous legacy reservations fail closed during order item cleanup', function () {
    enableStockPterodactyl();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('pending-test');
    $gateway->shouldReceive('label')->andReturn('Pending test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities);
    app(PaymentGatewayRegistry::class)->register($gateway);
    $product = stockProduct();
    $customer = Customer::factory()->create();
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => stockBilling(),
        'payment_method' => 'pending-test',
    ]);
    $item = $order->items->firstOrFail();
    $sibling = $item->replicate();
    $sibling->save();
    CapacityReservation::query()->create([
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'product_id' => $product->id,
        'provider_key' => 'pterodactyl',
        'capacity_key' => 'pterodactyl:exact-cleanup',
        'quantity' => 1,
        'expires_at' => null,
    ]);
    CapacityReservation::query()->firstOrFail()->update(['order_item_id' => null]);

    expect(fn () => app(CapacityReservationService::class)->releaseAllForOrderItem($order, $product->id, $item->id))
        ->toThrow(ValidationException::class)
        ->and(CapacityReservation::query()->whereNull('order_item_id')->count())->toBe(1)
        ->and(CapacityReservation::query()->where('capacity_key', 'pterodactyl:exact-cleanup')->exists())->toBeFalse()
        ->and($item->fresh()->options_snapshot['provisioning_recovery']['reason'] ?? null)
        ->toBe('ambiguous_legacy_reservation_cleanup');
});

test('paid provisioning rechecks capacity after its checkout hold expires', function () {
    $api = enableStockPterodactyl();
    $api->deployableNodeCount = 1;
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('pending-test');
    $gateway->shouldReceive('label')->andReturn('Pending test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities);
    app(PaymentGatewayRegistry::class)->register($gateway);
    $product = stockProduct();
    $customer = Customer::factory()->create();

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => stockBilling(),
        'payment_method' => 'pending-test',
    ]);
    $reservation = CapacityReservation::query()->firstOrFail();
    $reservation->update(['expires_at' => now()->subMinute()]);
    $instance = ServiceInstance::query()->create([
        'number' => 'TEST-EXPIRED-HOLD',
        'order_id' => $order->id,
        'order_item_id' => $order->items->firstOrFail()->id,
        'product_id' => $product->id,
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
        'customer_name' => $customer->name,
        'status' => ServiceInstanceStatus::Pending,
        'provider_key' => 'pterodactyl',
        'provider_settings_snapshot' => [
            'location_id' => '1',
            'nest_id' => '1',
            'egg_id' => '15',
            'memory' => '1024',
            'disk' => '2048',
        ],
        'meta' => [
            'provider_settings' => [
                'location_id' => '1',
                'nest_id' => '1',
                'egg_id' => '15',
                'memory' => '1024',
                'disk' => '2048',
            ],
            'provisioning_capacity_key' => $reservation->capacity_key,
        ],
    ]);

    $api->deployableNodeCount = 0;
    $failed = app(ProvisioningOrchestrator::class)->provision($instance);

    expect($failed->fresh()->status)->toBe(ServiceInstanceStatus::Failed)
        ->and($api->createCalls)->toBe(0)
        ->and(CapacityReservation::query()->count())->toBe(0);
});

test('server-scoped paid provisioning fails closed when its server disappears', function () {
    $api = enableStockPterodactyl();
    $api->deployableNodeCount = 1;
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('pending-test');
    $gateway->shouldReceive('label')->andReturn('Pending test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities);
    app(PaymentGatewayRegistry::class)->register($gateway);
    $product = stockProduct();
    $customer = Customer::factory()->create();

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => stockBilling(),
        'payment_method' => 'pending-test',
    ]);
    CapacityReservation::query()->delete();
    $server = ProvisioningServer::query()->create([
        'name' => 'Pterodactyl test server',
        'provider_key' => 'pterodactyl',
        'settings' => [
            'panel_url' => 'https://server.example.test',
            'application_api_key' => '[REDACTED]',
            'user_id' => '1',
        ],
        'is_active' => false,
    ]);
    CapacityReservation::query()->create([
        'order_id' => $order->id,
        'order_item_id' => $order->items->firstOrFail()->id,
        'product_id' => $product->id,
        'provider_key' => 'pterodactyl',
        'capacity_key' => 'pterodactyl:server-'.$server->id,
        'quantity' => 1,
        'expires_at' => null,
    ]);
    $instance = ServiceInstance::query()->create([
        'number' => 'TEST-MISSING-SERVER',
        'order_id' => $order->id,
        'order_item_id' => $order->items->firstOrFail()->id,
        'product_id' => $product->id,
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
        'customer_name' => $customer->name,
        'status' => ServiceInstanceStatus::Pending,
        'provider_key' => 'pterodactyl',
        'provisioning_server_id' => $server->id,
        'meta' => [
            'provider_settings' => [
                'location_id' => '1',
                'nest_id' => '1',
                'egg_id' => '15',
                'memory' => '1024',
                'disk' => '2048',
            ],
            'provisioning_capacity_key' => 'pterodactyl:server-'.$server->id,
        ],
    ]);

    $failed = app(ProvisioningOrchestrator::class)->provision($instance);

    expect($failed->fresh()->status)->toBe(ServiceInstanceStatus::Failed)
        ->and($api->capacityCalls)->toBe(1)
        ->and($api->createCalls)->toBe(0)
        ->and(CapacityReservation::query()->count())->toBe(0);
});

test('same-order reservations accumulate quantity for duplicate capacity lines', function () {
    $api = enableStockPterodactyl();
    $api->deployableNodeCount = 3;
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('pending-test');
    $gateway->shouldReceive('label')->andReturn('Pending test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities);
    app(PaymentGatewayRegistry::class)->register($gateway);
    $product = stockProduct();
    $customer = Customer::factory()->create();

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => stockBilling(),
        'payment_method' => 'pending-test',
    ]);
    $reservation = CapacityReservation::query()->firstOrFail();

    app(CapacityReservationService::class)->reserve(
        order: $order,
        product: $product,
        providerKey: 'pterodactyl',
        capacityKey: $reservation->capacity_key,
        quantity: 2,
    );

    expect($reservation->fresh()->quantity)->toBe(3);
});

test('instance capacity metadata commits only the matching reservation pool', function () {
    enableStockPterodactyl();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('pending-test');
    $gateway->shouldReceive('label')->andReturn('Pending test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities);
    app(PaymentGatewayRegistry::class)->register($gateway);
    $product = stockProduct();
    $customer = Customer::factory()->create();

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => stockBilling(),
        'payment_method' => 'pending-test',
    ]);
    $primary = CapacityReservation::query()->firstOrFail();
    $secondary = CapacityReservation::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'provider_key' => 'pterodactyl',
        'capacity_key' => 'pterodactyl:location-secondary',
        'quantity' => 1,
        'expires_at' => null,
    ]);
    $instance = ServiceInstance::query()->create([
        'number' => 'TEST-CAPACITY-INSTANCE',
        'order_id' => $order->id,
        'order_item_id' => $order->items->firstOrFail()->id,
        'product_id' => $product->id,
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
        'customer_name' => $customer->name,
        'status' => 'pending',
        'provider_key' => 'pterodactyl',
        'provider_settings_snapshot' => [
            'location_id' => '1',
            'nest_id' => '1',
            'egg_id' => '15',
            'memory' => '1024',
            'disk' => '2048',
        ],
        'meta' => [
            'provisioning_capacity_key' => $primary->capacity_key,
            'provisioning_capacity_requirements' => $primary->requirements,
        ],
    ]);

    app(CapacityReservationService::class)->commitForInstance($instance);

    expect($primary->fresh())->toBeNull()
        ->and($secondary->fresh()->quantity)->toBe(1);
});

test('generic provider adapters fail closed without a verified capacity endpoint', function () {
    foreach ([
        HttpCPanelApi::class,
        HttpConvoyApi::class,
        HttpDirectAdminApi::class,
        HttpEnhanceApi::class,
        HttpPleskApi::class,
        HttpVirtfusionApi::class,
        HttpVirtualizorApi::class,
    ] as $apiClass) {
        expect(fn () => app($apiClass)->availableCapacity([]))
            ->toThrow(fn (ServerProviderException $exception): bool => $exception->errorKey === 'errors.capacity_unsupported');
    }
});

test('unknown providers fail closed and release instance reservations', function () {
    enableStockPterodactyl();
    $product = stockProduct();
    $order = Order::factory()->create();
    $instance = ServiceInstance::query()->create([
        'number' => 'TEST-UNKNOWN-PROVIDER',
        'order_id' => $order->id,
        'product_id' => $product->id,
        'customer_email' => $order->customer_email,
        'status' => ServiceInstanceStatus::Pending,
        'provider_key' => 'provider-that-is-gone',
        'meta' => [
            'provisioning_capacity_key' => 'provider-that-is-gone:pool',
            'provisioning_capacity_requirements' => [],
        ],
    ]);
    CapacityReservation::query()->create([
        'order_id' => $order->id,
        'order_item_id' => $instance->order_item_id,
        'product_id' => $product->id,
        'provider_key' => 'provider-that-is-gone',
        'capacity_key' => 'provider-that-is-gone:pool',
        'quantity' => 1,
        'expires_at' => null,
    ]);

    $updated = app(ProvisioningOrchestrator::class)->provision($instance);

    expect($updated->status)->toBe(ServiceInstanceStatus::Failed)
        ->and(CapacityReservation::query()->where('order_id', $order->id)->exists())->toBeFalse();
});

test('unknown provider outcome keeps the capacity reservation for manual recovery', function () {
    $api = enableStockPterodactyl();
    $api->failGet = true;
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('pending-test');
    $gateway->shouldReceive('label')->andReturn('Pending test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities);
    app(PaymentGatewayRegistry::class)->register($gateway);
    $product = stockProduct();
    $customer = Customer::factory()->create();
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => stockBilling(),
        'payment_method' => 'pending-test',
    ]);
    $reservation = CapacityReservation::query()->firstOrFail();
    $instance = ServiceInstance::query()->create([
        'number' => 'TEST-UNKNOWN-OUTCOME',
        'order_id' => $order->id,
        'order_item_id' => $order->items->firstOrFail()->id,
        'product_id' => $product->id,
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
        'customer_name' => $customer->name,
        'status' => ServiceInstanceStatus::Pending,
        'provider_key' => 'pterodactyl',
        'provider_settings_snapshot' => [
            'location_id' => '1',
            'nest_id' => '1',
            'egg_id' => '15',
            'memory' => '1024',
            'disk' => '2048',
        ],
        'meta' => [
            'provider_settings' => [
                'location_id' => '1',
                'nest_id' => '1',
                'egg_id' => '15',
                'memory' => '1024',
                'disk' => '2048',
            ],
            'provisioning_capacity_key' => $reservation->capacity_key,
        ],
    ]);

    $result = app(ProvisioningOrchestrator::class)->provision($instance);

    expect($result->fresh()->status)->toBe(ServiceInstanceStatus::ManualReview)
        ->and(CapacityReservation::query()->count())->toBe(1)
        ->and($api->createCalls)->toBe(1);
});

test('failed provider provisioning releases a reservation when no external instance exists', function () {
    $api = enableStockPterodactyl();
    $api->deployableNodeCount = 1;
    $api->failCreate = true;
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('pending-test');
    $gateway->shouldReceive('label')->andReturn('Pending test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities);
    app(PaymentGatewayRegistry::class)->register($gateway);
    $product = stockProduct();
    $customer = Customer::factory()->create();

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => stockBilling(),
        'payment_method' => 'pending-test',
    ]);
    $reservation = CapacityReservation::query()->firstOrFail();
    $instance = ServiceInstance::query()->create([
        'number' => 'TEST-PROVIDER-FAILURE',
        'order_id' => $order->id,
        'order_item_id' => $order->items->firstOrFail()->id,
        'product_id' => $product->id,
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
        'customer_name' => $customer->name,
        'status' => ServiceInstanceStatus::Pending,
        'provider_key' => 'pterodactyl',
        'provider_settings_snapshot' => [
            'location_id' => '1',
            'nest_id' => '1',
            'egg_id' => '15',
            'memory' => '1024',
            'disk' => '2048',
        ],
        'meta' => [
            'provider_settings' => [
                'location_id' => '1',
                'nest_id' => '1',
                'egg_id' => '15',
                'memory' => '1024',
                'disk' => '2048',
            ],
            'provisioning_capacity_key' => $reservation->capacity_key,
            'provisioning_capacity_requirements' => $reservation->requirements,
        ],
    ]);

    $failed = app(ProvisioningOrchestrator::class)->provision($instance);

    expect($failed->fresh()->status)->toBe(ServiceInstanceStatus::Failed)
        ->and($api->createCalls)->toBe(1)
        ->and(CapacityReservation::query()->count())->toBe(0);
});

test('OrderPaid dispatches only after its database transaction commits', function () {
    expect(new OrderPaid(new Order))->toBeInstanceOf(ShouldDispatchAfterCommit::class);
});
test('stock-aware provisioning fails closed without reservation identity', function () {
    $api = enableStockPterodactyl();
    $instance = ServiceInstance::query()->create([
        'number' => 'TEST-STOCK-IDENTITY',
        'customer_email' => 'stock@example.test',
        'status' => 'pending',
        'provider_key' => 'pterodactyl',
        'provider_settings_snapshot' => [
            'location_id' => '1',
            'nest_id' => '1',
            'egg_id' => '15',
            'memory' => '1024',
            'disk' => '2048',
        ],
        'meta' => [],
    ]);

    $failed = app(ProvisioningOrchestrator::class)->provision($instance);

    expect($failed->fresh()->status)->toBe(ServiceInstanceStatus::Failed)
        ->and($api->createCalls)->toBe(0);
});

test('mixed vector revalidation and commit use the instance fingerprint', function () {
    enableStockPterodactyl();
    $product = stockProduct();
    $customer = Customer::factory()->create();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('pending-test');
    $gateway->shouldReceive('label')->andReturn('Pending test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities);
    app(PaymentGatewayRegistry::class)->register($gateway);
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => stockBilling(),
        'payment_method' => 'pending-test',
    ]);
    CapacityReservation::query()->delete();
    $key = 'pterodactyl:location-1';
    $service = app(CapacityReservationService::class);
    $first = ['disk' => 2048, 'memory' => 1024];
    $second = ['disk' => 4096, 'memory' => 2048];
    $service->reserve($order, $product, 'pterodactyl', $key, 1, $first, allowMixedRequirements: true);
    $service->reserve($order, $product, 'pterodactyl', $key, 1, $second, allowMixedRequirements: true);
    $instance = ServiceInstance::query()->create([
        'number' => 'TEST-VECTOR-FINGERPRINT',
        'order_id' => $order->id,
        'product_id' => $product->id,
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
        'customer_name' => $customer->name,
        'status' => ServiceInstanceStatus::Provisioning,
        'provider_key' => 'pterodactyl',
        'provider_settings_snapshot' => [
            'location_id' => '1',
            'nest_id' => '1',
            'egg_id' => '15',
            'memory' => '1024',
            'disk' => '2048',
        ],
        'meta' => [
            'provisioning_capacity_key' => $key,
            'provisioning_capacity_requirements' => $second,
        ],
    ]);
    $observed = null;

    $service->revalidateAndReserveForInstance(
        $instance,
        function (int $quantity, array $requirements) use (&$observed): void {
            $observed = [$quantity, $requirements];
        },
        $second,
        allowMixedRequirements: true,
    );
    $service->commitForInstance($instance);

    $remaining = CapacityReservation::query()->get();
    expect($observed)->toBe([1, $first])
        ->and($remaining)->toHaveCount(1)
        ->and($remaining->first()->requirements)->toBe($first)
        ->and($remaining->first()->quantity)->toBe(1);
});

test('capacity reservations aggregate mixed resource vectors in one pool', function () {
    $api = enableStockPterodactyl();
    $product = stockProduct();
    $customer = Customer::factory()->create();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('pending-test');
    $gateway->shouldReceive('label')->andReturn('Pending test');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities);
    app(PaymentGatewayRegistry::class)->register($gateway);
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => stockBilling(),
        'payment_method' => 'pending-test',
    ]);
    $reservation = CapacityReservation::query()->firstOrFail();
    $reservation->delete();
    $service = app(CapacityReservationService::class);

    $service->reserve(
        order: $order,
        product: $product,
        providerKey: 'pterodactyl',
        capacityKey: 'pterodactyl:location-1',
        quantity: 1,
        requirements: ['disk' => 2048, 'memory' => 1024],
    );

    expect(app(ProvisionerRegistry::class)->get('pterodactyl'))
        ->toBeInstanceOf(ProvidesProvisioningCapacityRequirements::class);
    $service->reserve(
        order: $order,
        product: $product,
        providerKey: 'pterodactyl',
        capacityKey: 'pterodactyl:location-1',
        quantity: 1,
        requirements: ['disk' => 4096, 'memory' => 2048],
        allowMixedRequirements: true,
    );

    expect($service->heldRequirements('pterodactyl:location-1'))->toBe(['disk' => 6144, 'memory' => 3072]);
});
test('only providers with verified capacity adapters implement checkout stock checks', function () {
    app(ModuleManager::class)->discover();
    app(ExtensionManager::class)->discover();
    installAndEnableModule('provisioning');
    app(SyncRegisteredPermissions::class)(force: true);

    foreach ([
        'pterodactyl',
        'proxmox',
        'cpanel',
        'convoy',
        'directadmin',
        'enhance',
        'plesk',
        'virtfusion',
        'virtualizor',
    ] as $extension) {
        installAndEnableExtension($extension);
    }

    $registry = app(ProvisionerRegistry::class);
    foreach (['pterodactyl', 'proxmox'] as $provider) {
        expect($registry->get($provider))->toBeInstanceOf(ChecksProvisioningStock::class);
    }

    foreach (['cpanel', 'convoy', 'directadmin', 'enhance', 'plesk', 'virtfusion', 'virtualizor'] as $provider) {
        expect($registry->get($provider))->not->toBeInstanceOf(ChecksProvisioningStock::class);
    }
});
