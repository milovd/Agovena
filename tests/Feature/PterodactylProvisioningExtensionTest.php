<?php

declare(strict_types=1);

use Agovena\Extensions\Pterodactyl\HttpPterodactylApi;
use Agovena\Extensions\Pterodactyl\PterodactylApi;
use Agovena\Extensions\Pterodactyl\PterodactylPanelUrl;
use Agovena\Extensions\Pterodactyl\PterodactylProviderException;
use Agovena\Extensions\Pterodactyl\PterodactylProvisioner;
use Agovena\Extensions\Pterodactyl\PterodactylServer;
use Agovena\Extensions\Pterodactyl\PterodactylStatusMapper;
use Agovena\Modules\Provisioning\CapacityReservationService;
use Agovena\Modules\Provisioning\EloquentProvisionedServiceResolver;
use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Http\Livewire\Admin\Servers as ProvisioningServers;
use Agovena\Modules\Provisioning\Http\Livewire\Customer\ServiceShow;
use Agovena\Modules\Provisioning\Models\CapacityReservation;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Provisioning\ProvisioningOrchestrator;
use Agovena\Modules\Provisioning\ProvisioningService;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Provisioning\Contracts\Provisioner;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Agovena\Provisioning\RunProvisionerAction;
use App\Enums\ProductOptionType;
use App\Livewire\Admin\Products\Create as CreateProductForm;
use App\Livewire\Admin\Products\Edit as EditProductForm;
use App\Models\Customer;
use App\Models\ExtensionSetting;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionChoice;
use App\Models\ProvisioningServer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\Support\FakePterodactylApi;

uses(CreatesStaff::class);

function enablePterodactyl(?FakePterodactylApi $api = null): FakePterodactylApi
{
    app(ModuleManager::class)->discover();
    app(ExtensionManager::class)->discover();
    $api ??= new FakePterodactylApi;
    app()->instance(PterodactylApi::class, $api);
    installAndEnableModule('provisioning');
    app(SyncRegisteredPermissions::class)(force: true);
    installAndEnableExtension('pterodactyl');
    app()->forgetInstance(PterodactylProvisioner::class);

    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('pterodactyl', 'panel_url', 'https://panel.example.test');
    $settings->set('pterodactyl', 'application_api_key', '[REDACTED]', secret: true);
    $settings->set('pterodactyl', 'client_api_key', '[REDACTED]', secret: true);
    $settings->set('pterodactyl', 'user_id', '1');
    $settings->set('pterodactyl', 'verify_tls', true);
    $settings->set('pterodactyl', 'timeout', '15');

    return $api;
}

test('server connections are extension driven and encrypt their credentials', function () {
    enablePterodactyl();

    Livewire::actingAs($this->createStaff())
        ->test(ProvisioningServers::class)
        ->set('name', 'Primary panel')
        ->set('providerKey', 'pterodactyl')
        ->set('settings.panel_url', 'https://panel.example.test')
        ->set('settings.application_api_key', '[REDACTED]')
        ->set('settings.user_id', '1')
        ->call('save')
        ->assertHasNoErrors();

    $server = ProvisioningServer::query()->where('name', 'Primary panel')->firstOrFail();
    $raw = (string) DB::table('provisioning_servers')->where('id', $server->id)->value('settings');

    expect($server->settings['application_api_key'])->toBe('[REDACTED]')
        ->and($raw)->not->toContain('[REDACTED]');
});

test('product creation exposes and persists pterodactyl automation only when the integration is enabled', function () {
    enablePterodactyl();
    $server = ProvisioningServer::query()->create([
        'name' => 'Primary panel',
        'provider_key' => 'pterodactyl',
        'settings' => [
            'panel_url' => 'https://panel.example.test',
            'application_api_key' => '[REDACTED]',
            'client_api_key' => '[REDACTED]',
            'user_id' => '1',
            'verify_tls' => true,
            'timeout' => '15',
        ],
        'is_active' => true,
    ]);

    Livewire::actingAs($this->createStaff())
        ->test(CreateProductForm::class)
        ->assertSee(__('admin.products.tabs.automation'))
        ->set('configureProvisioning', true)
        ->assertSee('Primary panel')
        ->set('provisioningServerId', $server->id)
        ->assertSee(__('pterodactyl::messages.product.location_id'))
        ->set('name', 'Managed Game Server')
        ->set('price', '19.99')
        ->set('currency', 'EUR')
        ->set('providerSettings.location_id', '1')
        ->set('providerSettings.nest_id', '1')
        ->set('providerSettings.egg_id', '15')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::query()->where('slug', 'managed-game-server')->firstOrFail();
    $capability = $product->capability('provisionable');

    expect($capability)->not->toBeNull()
        ->and($capability->config['server_id'] ?? null)->toBe($server->id)
        ->and($capability->config['provider_key'] ?? null)->toBe('pterodactyl')
        ->and($capability->config['provider_settings']['egg_id'] ?? null)->toBe('15');
});

test('pterodactyl product mappings require location nest and egg', function () {
    enablePterodactyl();
    $product = Product::factory()->create();

    Livewire::actingAs($this->createStaff())
        ->test(EditProductForm::class, ['product' => $product])
        ->call('applyPreset', 'hosted_service')
        ->set('providerKey', 'pterodactyl')
        ->set('providerSettings.location_id', '')
        ->set('providerSettings.nest_id', '')
        ->set('providerSettings.egg_id', '')
        ->call('saveCapabilities')
        ->assertHasErrors([
            'providerSettings.location_id',
            'providerSettings.nest_id',
            'providerSettings.egg_id',
        ]);
});

test('pterodactyl selected server capacity keys retain location boundaries', function () {
    enablePterodactyl();
    $provisioner = app(PterodactylProvisioner::class);

    $connection = [
        'panel_url' => 'https://panel.example.test',
        'application_api_key' => '[REDACTED]',
        'user_id' => '1',
    ];
    $first = $provisioner->capacityKeyForSettings(['location_id' => '1'], 7, $connection);
    $samePool = $provisioner->capacityKeyForSettings(['location_id' => '1'], 8, $connection);
    $samePoolTrailingSlash = $provisioner->capacityKeyForSettings(['location_id' => '1'], 8, array_merge($connection, [
        'panel_url' => 'https://panel.example.test/',
    ]));
    $secondPanel = $provisioner->capacityKeyForSettings(['location_id' => '1'], 8, array_merge($connection, [
        'panel_url' => 'https://secondary-panel.example.test',
    ]));

    expect($first)->toBe($samePool)
        ->and($first)->toBe($samePoolTrailingSlash)
        ->and($first)->not->toBe($secondPanel)
        ->and($first)->not->toBe('');
});

test('pterodactyl rejects invalid canonical relationship ids instead of using alternates', function () {
    enablePterodactyl();
    $provisioner = app(PterodactylProvisioner::class);
    $method = new ReflectionMethod($provisioner, 'serverRelatedId');

    expect($method->invoke($provisioner, [
        'node_id' => 0,
        'node' => ['id' => 7],
    ], ['node_id', 'node']))->toBeNull();
});

test('pterodactyl selected server without a snapshot has no capacity key', function () {
    enablePterodactyl();
    expect(app(PterodactylProvisioner::class)->capacityKeyForSettings(['location_id' => '1'], 7, null))
        ->toBe('');
});

test('pterodactyl capacity keys remove the default https port', function () {
    enablePterodactyl();
    $provisioner = app(PterodactylProvisioner::class);
    $base = ['panel_url' => 'https://panel.example.test', 'application_api_key' => '[REDACTED]', 'user_id' => '1'];
    $withPort = array_merge($base, ['panel_url' => 'https://panel.example.test:443']);

    expect($provisioner->capacityKeyForSettings(['location_id' => '1'], 7, $base))
        ->toBe($provisioner->capacityKeyForSettings(['location_id' => '1'], 7, $withPort));
});

test('pterodactyl maps malformed suspended values to manual review', function () {
    expect(PterodactylStatusMapper::lifecycleStatus(['status' => 'active', 'suspended' => 'false']))
        ->toBe('manual_review');
});

test('pterodactyl rejects an empty server identifier', function () {
    enablePterodactyl();
    $api = new HttpPterodactylApi(app(ExtensionSettingsRepository::class), [
        'panel_url' => 'https://panel.example.test',
        'application_api_key' => '[REDACTED]',
        'user_id' => '1',
    ]);
    Http::fake([
        'https://panel.example.test/api/application/servers/10*' => Http::response([
            'data' => ['attributes' => ['id' => 10, 'identifier' => '']],
        ]),
    ]);

    expect(fn () => $api->getServer(10))
        ->toThrow(PterodactylProviderException::class);
});

test('pterodactyl server validation requires a user id', function () {
    $api = enablePterodactyl();
    $result = app(PterodactylProvisioner::class)->testServer([
        'panel_url' => 'https://panel.example.test',
        'application_api_key' => '[REDACTED]',
        'user_id' => '',
    ]);

    expect($result->ok)->toBeFalse();
});

test('unknown pterodactyl statuses require manual review', function () {
    expect(PterodactylStatusMapper::lifecycleStatus(['status' => 'transferring']))
        ->toBe('manual_review')
        ->and(PterodactylStatusMapper::lifecycleStatus([]))
        ->toBe('manual_review');
});

test('pterodactyl credentials are encrypted and never redisplayed', function () {
    enablePterodactyl();
    $row = ExtensionSetting::query()
        ->where('extension_id', 'pterodactyl')
        ->where('key', 'application_api_key')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->is_secret)->toBeTrue()
        ->and($row->value)->not->toContain('[REDACTED]')
        ->and(Crypt::decryptString((string) $row->value))->toBe('[REDACTED]');
});

function pterodactylBilling(): AddressData
{
    return AddressData::fromArray([
        'name' => 'Panel Buyer',
        'line1' => 'Street 1',
        'city' => 'Amsterdam',
        'postal_code' => '1000 AA',
        'country' => 'NL',
    ]);
}

/**
 * @param  array<string, mixed>  $settings
 */
function makePterodactylProduct(array $settings = []): Product
{
    $product = Product::factory()->active()->create(['price_amount' => 5000]);
    app(ProductCapabilityManager::class)->enable($product, 'provisionable', [
        'provider_key' => 'pterodactyl',
        'provider_settings' => array_merge([
            'location_id' => '1',
            'nest_id' => '1',
            'egg_id' => '15',
            'memory' => '1024',
            'disk' => '2048',
            'cpu' => '200',
            'environment' => "SERVER_JARFILE=custom.jar\nFOO=bar",
        ], $settings),
    ]);

    return $product->fresh(['capabilities']);
}

function payForPterodactylProduct(Product $product, ?Customer $customer = null, array $selections = []): ServiceInstance
{
    config(['agovena.payments.allow_development_instant_pay' => true]);
    $customer ??= Customer::factory()->create();
    app(CartService::class)->add($product->id, 1, $selections);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => pterodactylBilling(),
        'payment_method' => 'development',
    ]);

    return ServiceInstance::query()->where('order_id', $order->id)->firstOrFail();
}

test('pterodactyl registers only when the extension is enabled', function () {
    installAndEnableModule('provisioning');

    expect(app(ProvisionerRegistry::class)->get('pterodactyl'))->toBeNull()
        ->and(app(ProvisionerRegistry::class)->get('manual'))->not->toBeNull();

    enablePterodactyl();

    expect(app(ProvisionerRegistry::class)->get('pterodactyl'))->toBeInstanceOf(PterodactylProvisioner::class)
        ->and(app(ProvisionerRegistry::class)->get('manual'))->not->toBeNull();

    app(ExtensionManager::class)->disable('pterodactyl');

    expect(app(ProvisionerRegistry::class)->get('pterodactyl'))->toBeNull()
        ->and(app(ProvisionerRegistry::class)->get('manual'))->not->toBeNull();
});

test('provisioning module works without pterodactyl and keeps the manual provider', function () {
    installAndEnableModule('provisioning');
    app(SyncRegisteredPermissions::class)(force: true);
    config(['agovena.payments.allow_development_instant_pay' => true]);

    $product = Product::factory()->active()->create(['price_amount' => 1000]);
    app(ProductCapabilityManager::class)->enable($product, 'provisionable', ['provider_key' => 'manual']);
    $customer = Customer::factory()->create();
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => pterodactylBilling(),
        'payment_method' => 'development',
    ]);

    $instance = ServiceInstance::query()->firstOrFail();

    expect($instance->status)->toBe(ServiceInstanceStatus::Pending)
        ->and($instance->provider_key)->toBe('manual')
        ->and(app(ProvisionerRegistry::class)->get('pterodactyl'))->toBeNull()
        ->and($order->payment?->status->value)->toBe('paid');
});

test('multiple provisioners can coexist', function () {
    enablePterodactyl();
    app(ProvisionerRegistry::class)->register(new class implements Provisioner
    {
        public function id(): string
        {
            return 'other-panel';
        }

        public function label(): string
        {
            return 'Other panel';
        }
    });

    expect(collect(app(ProvisionerRegistry::class)->all())->map(fn (Provisioner $provisioner): string => $provisioner->id())->all())
        ->toContain('manual', 'pterodactyl', 'other-panel');
});

test('paid pterodactyl order provisions a server and stores extension-owned mapping', function () {
    $api = enablePterodactyl();
    $instance = payForPterodactylProduct(makePterodactylProduct());

    expect($instance->status)->toBe(ServiceInstanceStatus::Active)
        ->and($instance->provider_key)->toBe('pterodactyl')
        ->and($instance->external_ref)->toBe('10')
        ->and($api->createCalls)->toBe(1)
        ->and(PterodactylServer::query()->where('service_instance_id', $instance->id)->exists())->toBeTrue()
        ->and(Schema::hasColumn('products', 'egg_id'))->toBeFalse()
        ->and(Schema::hasColumn('products', 'nest_id'))->toBeFalse()
        ->and(Schema::hasColumn('products', 'pterodactyl_location'))->toBeFalse()
        ->and($instance->meta['provider_settings']['egg_id'] ?? null)->toBe('15');
});

test('pterodactyl verifies egg default environment when no override is configured', function () {
    $api = enablePterodactyl();
    $instance = payForPterodactylProduct(makePterodactylProduct(['environment' => '']));
    $api->serversById[10]['environment']['SERVER_JARFILE'] = 'drifted.jar';

    expect(fn () => app(PterodactylProvisioner::class)->syncStatus(EloquentProvisionedServiceResolver::info($instance)))
        ->toThrow(ValidationException::class);
});

test('pterodactyl environment is encrypted in order and service snapshots', function () {
    $api = enablePterodactyl();
    $instance = payForPterodactylProduct(makePterodactylProduct());

    $orderSnapshot = (string) DB::table('order_items')
        ->where('id', $instance->order_item_id)
        ->value('options_snapshot');
    $serviceMeta = (string) DB::table('service_instances')
        ->where('id', $instance->id)
        ->value('meta');
    $orderItemArray = OrderItem::query()->findOrFail($instance->order_item_id)->toArray();
    $serviceArray = $instance->fresh()->toArray();

    expect($api->lastCreatePayload['environment']['SERVER_JARFILE'] ?? null)->toBe('custom.jar')
        ->and($api->lastCreatePayload['environment']['FOO'] ?? null)->toBe('bar')
        ->and($orderSnapshot)->not->toContain('custom.jar')
        ->and($orderSnapshot)->not->toContain('FOO=bar')
        ->and($orderSnapshot)->not->toContain('value_encrypted')
        ->and($serviceMeta)->not->toContain('custom.jar')
        ->and($serviceMeta)->not->toContain('FOO=bar')
        ->and($orderItemArray)->not->toHaveKey('provisioning_provider_settings_snapshot')
        ->and($serviceArray)->not->toHaveKey('provider_settings_snapshot')
        ->and($instance->meta['provider_settings']['environment'] ?? null)->toBe('[REDACTED]');
});

test('semantic environment option values are redacted in order and service snapshots', function () {
    $api = enablePterodactyl();
    $product = makePterodactylProduct();
    ProductOption::query()->create([
        'product_id' => $product->id,
        'key' => 'environment',
        'label' => 'Environment',
        'type' => ProductOptionType::Text,
        'is_required' => false,
        'is_active' => true,
        'sort' => 0,
        'price_adjustment_amount' => 0,
        'constraints' => [],
    ]);

    $instance = payForPterodactylProduct($product, selections: ['environment' => 'LEAK=value']);
    $orderSnapshot = (string) DB::table('order_items')
        ->where('id', $instance->order_item_id)
        ->value('options_snapshot');
    $serviceMeta = (string) DB::table('service_instances')
        ->where('id', $instance->id)
        ->value('meta');

    expect($orderSnapshot)->not->toContain('LEAK=value')
        ->and($serviceMeta)->not->toContain('LEAK=value')
        ->and($api->createCalls)->toBe(1)
        ->and($api->lastCreatePayload['environment'] ?? null)->toMatchArray(['LEAK' => 'value']);
});

test('product capability secrets are not copied into the public product editor state', function () {
    enablePterodactyl();
    $product = makePterodactylProduct(['environment' => 'SECRET=value']);

    Livewire::actingAs($this->createStaff())
        ->test(EditProductForm::class, ['product' => $product])
        ->assertSet('providerSettings.environment', '[REDACTED]');
});

test('legacy plaintext environment is redacted before runtime resolution', function () {
    enablePterodactyl();
    $instance = payForPterodactylProduct(makePterodactylProduct());
    $meta = $instance->meta;
    unset($meta['provider_settings_encrypted']);
    $meta['provider_settings']['environment'] = 'LEGACY=value';
    $meta['options_snapshot']['__provisioning']['provider_settings']['environment'] = 'LEGACY=value';
    $instance->meta = $meta;
    $instance->provider_settings_snapshot = null;
    $instance->save();

    $info = EloquentProvisionedServiceResolver::info($instance->fresh());

    expect($info->meta['provider_settings']['environment'] ?? null)->toBe('[REDACTED]')
        ->and($info->meta['options_snapshot']['__provisioning']['provider_settings']['environment'] ?? null)->toBe('[REDACTED]');

    $provisioner = app(ProvisionerRegistry::class)->get('pterodactyl');
    expect(fn () => $provisioner?->provision($info))->toThrow(ValidationException::class);
});

test('legacy service settings are encrypted before metadata redaction', function () {
    enablePterodactyl();
    $instance = payForPterodactylProduct(makePterodactylProduct());
    $legacyProviderSettings = [
        'panel_url' => 'https://panel.example.test',
        'application_api_key' => '[REDACTED]',
        'environment' => '[REDACTED]',
    ];
    $legacyServerSettings = [
        'panel_url' => 'https://panel.example.test',
        'application_api_key' => '[REDACTED]',
        'user_id' => '1',
    ];
    DB::table('service_instances')->where('id', $instance->id)->update([
        'meta' => json_encode([
            'provider_settings' => $legacyProviderSettings,
            'server_settings' => $legacyServerSettings,
        ], JSON_THROW_ON_ERROR),
        'server_settings_snapshot' => null,
    ]);

    $migration = require base_path('../optional-packages/modules/provisioning/database/migrations/2026_08_29_000100_redact_legacy_service_meta.php');
    $migration->up();
    $row = DB::table('service_instances')->where('id', $instance->id)->first();
    $meta = json_decode((string) $row->meta, true);

    expect($meta['provider_settings']['application_api_key'] ?? null)->toBe('[REDACTED]')
        ->and($meta['provider_settings_encrypted'] ?? null)->toBeString()->not->toBe('')
        ->and(Crypt::decryptString((string) $meta['provider_settings_encrypted']))
        ->toBe(json_encode($legacyProviderSettings))
        ->and(Crypt::decryptString((string) $row->server_settings_snapshot))
        ->toBe(json_encode($legacyServerSettings));

    $providerCipher = $meta['provider_settings_encrypted'];
    $serverCipher = $row->server_settings_snapshot;
    $migration->up();
    $rerun = DB::table('service_instances')->where('id', $instance->id)->first();
    $rerunMeta = json_decode((string) $rerun->meta, true);

    expect($rerunMeta['provider_settings_encrypted'] ?? null)->toBe($providerCipher)
        ->and($rerun->server_settings_snapshot)->toBe($serverCipher);
});

test('pterodactyl uses the selected server user id when creating a server', function () {
    $api = enablePterodactyl();
    $server = ProvisioningServer::query()->create([
        'name' => 'Secondary panel',
        'provider_key' => 'pterodactyl',
        'settings' => [
            'panel_url' => 'https://secondary-panel.example.test',
            'application_api_key' => '[REDACTED]',
            'user_id' => '42',
        ],
        'is_active' => true,
    ]);
    $product = makePterodactylProduct();
    $capability = $product->capability('provisionable');
    $capability->config = array_merge($capability->config, ['server_id' => $server->id]);
    $capability->save();
    payForPterodactylProduct($product);

    $instance = ServiceInstance::query()->latest('id')->firstOrFail();

    expect($instance->server_settings_snapshot['user_id'] ?? null)->toBe('42');
    expect($api->lastCreatePayload['user'] ?? null)->toBe(42);
});

test('pterodactyl does not fall back to global credentials for an unavailable selected server', function () {
    $api = enablePterodactyl();
    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-PANEL-UNAVAILABLE',
        'status' => ServiceInstanceStatus::Provisioning,
        'provider_key' => 'pterodactyl',
        'customer_email' => 'panel@example.test',
        'meta' => [
            'provider_settings' => [
                'location_id' => '1',
                'nest_id' => '1',
                'egg_id' => '15',
            ],
            'server_settings_required' => true,
            'server_settings' => [],
            'server_settings_unavailable' => true,
        ],
    ]);

    $provisioner = app(ProvisionerRegistry::class)->get('pterodactyl');
    expect($provisioner)->toBeInstanceOf(PterodactylProvisioner::class);

    expect(fn () => $provisioner->provision(EloquentProvisionedServiceResolver::info($instance)))
        ->toThrow(ValidationException::class)
        ->and($api->createCalls)->toBe(0);
});

test('matching product option keys override provider defaults for that order', function () {
    $api = enablePterodactyl();
    $product = makePterodactylProduct(['memory' => '1024']);
    $option = ProductOption::query()->create([
        'product_id' => $product->id,
        'key' => 'memory',
        'label' => 'Memory',
        'type' => ProductOptionType::Select,
        'is_required' => true,
        'is_active' => true,
        'sort' => 0,
        'price_adjustment_amount' => 0,
        'constraints' => null,
    ]);
    ProductOptionChoice::query()->create([
        'product_option_id' => $option->id,
        'value' => '2048',
        'label' => '2 GiB',
        'price_adjustment_amount' => 0,
        'sort' => 0,
        'is_active' => true,
    ]);

    payForPterodactylProduct($product, selections: ['memory' => '2048']);
    $created = array_values($api->serversById)[0];

    expect($created['limits']['memory'] ?? null)->toBe(2048);
});

test('installing panel status leaves the service provisioning', function () {
    $api = enablePterodactyl();
    $api->nextStatus = 'installing';
    $instance = payForPterodactylProduct(makePterodactylProduct());

    expect($instance->status)->toBe(ServiceInstanceStatus::Provisioning)
        ->and($instance->external_ref)->toBe('10')
        ->and(CapacityReservation::query()->where('order_id', $instance->order_id)->exists())->toBeTrue();

    $api->serversById[10]['status'] = 'active';
    $instance = app(ProvisioningOrchestrator::class)->sync($instance);

    expect($instance->status)->toBe(ServiceInstanceStatus::Active);
});

test('pterodactyl manual-review async states retain capacity reservations', function () {
    $api = enablePterodactyl();
    $api->nextStatus = 'transferring';
    $instance = payForPterodactylProduct(makePterodactylProduct());

    expect($instance->status)->toBe(ServiceInstanceStatus::ManualReview)
        ->and(CapacityReservation::query()->where('order_id', $instance->order_id)->exists())->toBeTrue();
});

test('pterodactyl readback rejects a persisted identifier or uuid mismatch before actions', function () {
    $api = enablePterodactyl();
    $instance = payForPterodactylProduct(makePterodactylProduct());
    $api->serversById[10]['uuid'] = 'different-uuid';

    expect(fn () => app(PterodactylProvisioner::class)->runAction(
        EloquentProvisionedServiceResolver::info($instance),
        'start',
    ))->toThrow(ValidationException::class);
    expect($api->powerCalls)->toBe([]);
});

test('duplicate provisioning retry does not create a second server', function () {
    $api = enablePterodactyl();
    $instance = payForPterodactylProduct(makePterodactylProduct());
    app(ProvisioningOrchestrator::class)->provision($instance);

    expect($api->createCalls)->toBe(1)
        ->and(PterodactylServer::query()->count())->toBe(1);
});

test('pterodactyl refuses to adopt a server owned by another panel user', function () {
    $api = enablePterodactyl();
    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-ADOPT-OWNER',
        'status' => ServiceInstanceStatus::Provisioning,
        'provider_key' => 'pterodactyl',
        'customer_email' => 'adopt@example.test',
        'meta' => ['provider_settings' => [
            'location_id' => '1',
            'nest_id' => '1',
            'egg_id' => '15',
            'memory' => '1024',
            'disk' => '2048',
        ]],
    ]);
    $server = [
        'id' => 77,
        'external_id' => 'agovena-'.$instance->id,
        'identifier' => 'adopt-owner',
        'uuid' => 'adopt-owner-uuid',
        'user' => 999,
        'node_id' => 1,
        'location_id' => 1,
        'nest' => 1,
        'egg' => 15,
        'limits' => ['memory' => 1024, 'swap' => 0, 'disk' => 2048, 'io' => 500, 'cpu' => 100],
        'feature_limits' => ['databases' => 0, 'allocations' => 1, 'backups' => 0],
    ];
    $api->serversByExternalId[$server['external_id']] = $server;
    $api->serversById[77] = $server;

    expect(fn () => app(PterodactylProvisioner::class)->provision(EloquentProvisionedServiceResolver::info($instance)))
        ->toThrow(ValidationException::class);
    expect(PterodactylServer::query()->where('service_instance_id', $instance->id)->exists())->toBeFalse();
});

test('pterodactyl refuses to adopt a server with a different product configuration', function () {
    $api = enablePterodactyl();
    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-ADOPT-CONFIG',
        'status' => ServiceInstanceStatus::Provisioning,
        'provider_key' => 'pterodactyl',
        'customer_email' => 'adopt-config@example.test',
        'meta' => ['provider_settings' => [
            'location_id' => '1',
            'nest_id' => '1',
            'egg_id' => '15',
            'memory' => '1024',
            'disk' => '2048',
        ]],
    ]);
    $server = [
        'id' => 78,
        'external_id' => 'agovena-'.$instance->id,
        'identifier' => 'adopt-config',
        'uuid' => 'adopt-config-uuid',
        'user' => 1,
        'node_id' => 1,
        'location_id' => 1,
        'nest' => 1,
        'egg' => 99,
        'limits' => ['memory' => 1024, 'swap' => 0, 'disk' => 2048, 'io' => 500, 'cpu' => 100],
        'feature_limits' => ['databases' => 0, 'allocations' => 1, 'backups' => 0],
    ];
    $api->serversByExternalId[$server['external_id']] = $server;
    $api->serversById[78] = $server;

    expect(fn () => app(PterodactylProvisioner::class)->provision(EloquentProvisionedServiceResolver::info($instance)))
        ->toThrow(ValidationException::class);
    expect(PterodactylServer::query()->where('service_instance_id', $instance->id)->exists())->toBeFalse();
});

test('pterodactyl recovery removes a mapping when recovered ownership is invalid', function () {
    $api = enablePterodactyl();
    $instance = payForPterodactylProduct(makePterodactylProduct());
    PterodactylServer::query()->where('service_instance_id', $instance->id)->delete();
    $api->serversById[10]['user'] = 999;
    $api->serversByExternalId['agovena-pterodactyl-'.$instance->id]['user'] = 999;

    expect(fn () => app(PterodactylProvisioner::class)->syncStatus(EloquentProvisionedServiceResolver::info($instance)))
        ->toThrow(ValidationException::class);
    expect(PterodactylServer::query()->where('service_instance_id', $instance->id)->exists())->toBeFalse();
});

test('uncertain provisioning status keeps the mapping for manual review and retry is idempotent', function () {
    $api = enablePterodactyl();
    $api->failGet = true;
    $instance = payForPterodactylProduct(makePterodactylProduct());

    expect($instance->status)->toBe(ServiceInstanceStatus::ManualReview)
        ->and($api->createCalls)->toBe(1)
        ->and(PterodactylServer::query()->count())->toBe(1);

    $api->failGet = false;
    $instance = app(ProvisioningOrchestrator::class)->provision($instance);

    expect($instance->status)->toBe(ServiceInstanceStatus::Active)
        ->and($api->createCalls)->toBe(1);
});

test('invalid egg and quota become safe agovena failures', function () {
    $api = enablePterodactyl();
    $api->failEgg = true;
    $eggFailed = payForPterodactylProduct(makePterodactylProduct());
    expect($eggFailed->status)->toBe(ServiceInstanceStatus::Failed)
        ->and($eggFailed->failure_message)->toBe(__('pterodactyl::messages.errors.invalid_egg'))
        ->and($eggFailed->failure_message)->not->toContain('[REDACTED]');

    $api->failEgg = false;
    $api->failQuota = true;
    $quotaFailed = payForPterodactylProduct(makePterodactylProduct());
    expect($quotaFailed->status)->toBe(ServiceInstanceStatus::Failed)
        ->and($quotaFailed->failure_message)->toBe(__('pterodactyl::messages.errors.quota'));
});

test('invalid credentials and unreachable panel fail closed before checkout', function () {
    $api = enablePterodactyl();
    $api->unauthorized = true;
    expect(fn () => payForPterodactylProduct(makePterodactylProduct()))
        ->toThrow(ValidationException::class);

    $api->unauthorized = false;
    $api->unreachable = true;
    expect(fn () => payForPterodactylProduct(makePterodactylProduct()))
        ->toThrow(ValidationException::class);

    $api->unreachable = false;
    $api->timeout = true;
    expect(fn () => payForPterodactylProduct(makePterodactylProduct()))
        ->toThrow(ValidationException::class);
});

test('malformed provider response is a safe failure at checkout', function () {
    $api = enablePterodactyl();
    $api->malformed = true;

    expect(fn () => payForPterodactylProduct(makePterodactylProduct()))
        ->toThrow(ValidationException::class);
});

test('missing product mapping fails before checkout', function () {
    $api = enablePterodactyl();

    expect(fn () => payForPterodactylProduct(makePterodactylProduct([
        'location_id' => '',
        'nest_id' => '',
        'egg_id' => '',
    ])))->toThrow(ValidationException::class)
        ->and($api->createCalls)->toBe(0);
});

test('suspend unsuspend and terminate go through the provider', function () {
    $api = enablePterodactyl();
    $instance = payForPterodactylProduct(makePterodactylProduct());
    $orchestrator = app(ProvisioningOrchestrator::class);

    $instance = $orchestrator->suspend($instance);
    expect($instance->status)->toBe(ServiceInstanceStatus::Suspended)
        ->and($api->serversById[10]['suspended'])->toBeTrue();

    $instance = $orchestrator->unsuspend($instance);
    expect($instance->status)->toBe(ServiceInstanceStatus::Active)
        ->and($api->serversById[10]['suspended'])->toBeFalse();

    $instance = $orchestrator->terminate($instance);
    expect($instance->status)->toBe(ServiceInstanceStatus::Terminated)
        ->and(isset($api->serversById[10]))->toBeFalse()
        ->and(PterodactylServer::query()->where('service_instance_id', $instance->id)->exists())->toBeFalse();
});

test('suspend failure does not mark the agovena service suspended', function () {
    $api = enablePterodactyl();
    $api->failSuspend = true;
    $instance = payForPterodactylProduct(makePterodactylProduct());

    expect(fn () => app(ProvisioningOrchestrator::class)->suspend($instance))
        ->toThrow(ValidationException::class);
    expect($instance->fresh()->status)->toBe(ServiceInstanceStatus::Active);
});

test('terminate failure does not mark the agovena service terminated', function () {
    $api = enablePterodactyl();
    $api->failDelete = true;
    $instance = payForPterodactylProduct(makePterodactylProduct());

    expect(fn () => app(ProvisioningOrchestrator::class)->terminate($instance))
        ->toThrow(ValidationException::class);
    expect($instance->fresh()->status)->toBe(ServiceInstanceStatus::Active);
});

test('plan change resizes the panel server using extension-owned settings', function () {
    $api = enablePterodactyl();
    $instance = payForPterodactylProduct(makePterodactylProduct());
    $meta = $instance->meta;
    $meta['provider_settings']['memory'] = '2048';
    $meta['provider_settings']['disk'] = '4096';
    $meta['provider_settings_encrypted'] = Crypt::encryptString(json_encode($meta['provider_settings'], JSON_THROW_ON_ERROR));
    $instance->meta = $meta;
    $instance->save();

    $instance = app(ProvisioningOrchestrator::class)->changePlan($instance, [
        'id' => '2048',
        'provider_key' => 'pterodactyl',
        'provider_settings' => array_merge($meta['provider_settings'], [
            'memory' => '2048',
            'disk' => '4096',
            'docker_image' => 'ghcr.io/pterodactyl/games:java',
            'startup' => 'java -jar server.jar',
            'environment' => 'FOO=changed',
            'dedicated_ip' => true,
        ]),
        'capacity_key' => 'pterodactyl:location-1',
        'requirements' => ['memory' => 2048, 'disk' => 4096],
    ]);

    expect($api->buildCalls)->toBe(1)
        ->and($api->serversById[10]['limits']['memory'])->toBe(2048)
        ->and($api->lastBuildPayload['dedicated_ip'] ?? null)->toBeTrue()
        ->and($api->startupCalls)->toBe(1)
        ->and($api->lastStartupPayload['image'] ?? null)->toBe('ghcr.io/pterodactyl/games:java')
        ->and($api->lastStartupPayload['startup'] ?? null)->toBe('java -jar server.jar')
        ->and($api->lastStartupPayload['environment']['SERVER_JARFILE'] ?? null)->toBe('server.jar')
        ->and($api->lastStartupPayload['environment']['FOO'] ?? null)->toBe('changed')
        ->and($instance->status)->toBe(ServiceInstanceStatus::Active);
});

test('plan change compensation failure requires manual review', function () {
    $api = enablePterodactyl();
    $api->failBuild = true;
    $instance = payForPterodactylProduct(makePterodactylProduct());

    expect(fn () => app(ProvisioningOrchestrator::class)->changePlan($instance, [
        'id' => '4096',
        'provider_key' => 'pterodactyl',
        'provider_settings' => $instance->meta['provider_settings'] ?? [],
        'capacity_key' => 'pterodactyl:location-1',
        'requirements' => ['memory' => 1024, 'disk' => 2048],
    ]))
        ->toThrow(ValidationException::class);
    expect($instance->fresh()->status)->toBe(ServiceInstanceStatus::ManualReview)
        ->and($instance->fresh()->meta['provisioning_recovery']['reason'] ?? null)
        ->toBe('plan_change_compensation_failed')
        ->and(CapacityReservation::query()->where('order_id', $instance->order_id)->exists())->toBeTrue();
});

test('plan change revalidates target capacity before changing the panel server', function () {
    $api = enablePterodactyl();
    $instance = payForPterodactylProduct(makePterodactylProduct());

    expect(fn () => app(ProvisioningOrchestrator::class)->changePlan($instance, [
        'id' => 'oversized',
        'provider_key' => 'pterodactyl',
        'provider_settings' => array_merge($instance->meta['provider_settings'] ?? [], [
            'memory' => '999999',
            'disk' => '999999',
        ]),
        'capacity_key' => 'pterodactyl:location-1',
        'requirements' => ['memory' => 999999, 'disk' => 999999],
    ]))->toThrow(ValidationException::class);

    expect($api->buildCalls)->toBe(0)
        ->and(CapacityReservation::query()->count())->toBe(0)
        ->and($instance->fresh()->status)->toBe(ServiceInstanceStatus::Active);
});

test('status sync failure does not change service state', function () {
    $api = enablePterodactyl();
    $instance = payForPterodactylProduct(makePterodactylProduct());
    $api->failGet = true;

    expect(fn () => app(ProvisioningOrchestrator::class)->sync($instance))
        ->toThrow(ValidationException::class);
    expect($instance->fresh()->status)->toBe(ServiceInstanceStatus::Active);
});

test('missing pterodactyl resources terminate the service and release its reservation', function () {
    $api = enablePterodactyl();
    $instance = payForPterodactylProduct(makePterodactylProduct());
    CapacityReservation::query()->create([
        'order_id' => $instance->order_id,
        'order_item_id' => $instance->order_item_id,
        'product_id' => $instance->product_id,
        'provider_key' => 'pterodactyl',
        'capacity_key' => $instance->meta['provisioning_capacity_key'],
        'quantity' => 1,
        'requirements' => $instance->meta['provisioning_capacity_requirements'],
        'requirements_fingerprint' => app(CapacityReservationService::class)
            ->requirementsFingerprint($instance->meta['provisioning_capacity_requirements']),
        'expires_at' => null,
    ]);
    $api->serversById = [];

    $updated = app(ProvisioningOrchestrator::class)->sync($instance);

    expect($updated->status)->toBe(ServiceInstanceStatus::Terminated)
        ->and($updated->fresh()->meta['provider_reconciliation'] ?? null)->toBe('absent')
        ->and(CapacityReservation::query()->where('order_id', $instance->order_id)->exists())->toBeFalse();
});

test('customer portal exposes panel link and safe power actions', function () {
    $api = enablePterodactyl();
    $customer = Customer::factory()->create();
    $instance = payForPterodactylProduct(makePterodactylProduct(), $customer);

    Livewire::actingAs($customer->user)
        ->test(ServiceShow::class, ['instance' => $instance])
        ->assertSee(__('pterodactyl::messages.actions.start'))
        ->assertSee('https://panel.example.test/server/abc10')
        ->assertDontSee('[REDACTED]')
        ->assertDontSee('[REDACTED]')
        ->call('runAction', 'start')
        ->assertHasNoErrors();

    expect($api->powerCalls)->toContain('abc10:start');
});

test('manual services do not receive pterodactyl customer actions', function () {
    enablePterodactyl();
    installAndEnableModule('provisioning');
    config(['agovena.payments.allow_development_instant_pay' => true]);
    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 1000]);
    app(ProductCapabilityManager::class)->enable($product, 'provisionable', ['provider_key' => 'manual']);
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => pterodactylBilling(),
        'payment_method' => 'development',
    ]);
    $instance = app(ProvisioningService::class)->activate(ServiceInstance::query()->firstOrFail(), 'manual-ref');

    Livewire::actingAs($customer->user)
        ->test(ServiceShow::class, ['instance' => $instance])
        ->assertDontSee(__('pterodactyl::messages.actions.start'))
        ->assertSee(__('notifications.provisioning.refresh_status'));

    app(RunProvisionerAction::class)->handle($customer, $instance->id, 'refresh_status');
});

test('disabling pterodactyl does not delete service data', function () {
    enablePterodactyl();
    $instance = payForPterodactylProduct(makePterodactylProduct());

    app(ExtensionManager::class)->disable('pterodactyl');

    expect(ServiceInstance::query()->whereKey($instance->id)->exists())->toBeTrue()
        ->and(PterodactylServer::query()->where('service_instance_id', $instance->id)->exists())->toBeTrue()
        ->and(app(ProvisionerRegistry::class)->get('pterodactyl'))->toBeNull();
});

test('health check reports a safe connection result', function () {
    $api = enablePterodactyl();
    $result = app(PterodactylProvisioner::class)->health();

    expect($result->ok)->toBeTrue()
        ->and($result->message)->toContain('https://panel.example.test')
        ->and($result->message)->not->toContain('[REDACTED]');

    $api->unauthorized = true;
    $failed = app(PterodactylProvisioner::class)->health();
    expect($failed->ok)->toBeFalse()
        ->and($failed->message)->not->toContain('[REDACTED]');
});

test('panel url validation allows private hosts and rejects credentials', function () {
    expect(PterodactylPanelUrl::normalize('https://192.168.10.20:8443'))->toBe('https://192.168.10.20:8443')
        ->and(fn () => PterodactylPanelUrl::normalize('ftp://panel.example.test'))
        ->toThrow(PterodactylProviderException::class)
        ->and(fn () => PterodactylPanelUrl::normalize('https://user:pass@panel.example.test'))
        ->toThrow(PterodactylProviderException::class);
});

test('http adapter calculates deployable capacity from real node resources', function () {
    installAndEnableModule('provisioning');
    installAndEnableExtension('pterodactyl');

    Http::fake([
        'https://panel.example.test/api/application/nodes*' => Http::response([
            'data' => [[
                'attributes' => [
                    'id' => 7,
                    'maintenance_mode' => false,
                    'memory' => 2048,
                    'memory_overallocate' => 50,
                    'disk' => 4096,
                    'disk_overallocate' => 0,
                    'allocated_resources' => ['memory' => 1024, 'disk' => 2048],
                ],
            ]],
            'meta' => ['pagination' => ['total_pages' => 1]],
        ]),
    ]);

    $nodes = app(HttpPterodactylApi::class)->withConnection([
        'panel_url' => 'https://panel.example.test',
        'application_api_key' => '[REDACTED]',
        'verify_tls' => true,
        'timeout' => 5,
    ])->getDeployableNodes(3, 512, 1024);

    expect($nodes)->toHaveCount(1)
        ->and($nodes[0]['capacity'])->toBe(2);
});

test('http adapter maps panel errors without leaking secrets', function () {
    installAndEnableModule('provisioning');
    installAndEnableExtension('pterodactyl');
    app(ExtensionSettingsRepository::class)->set('pterodactyl', 'panel_url', 'https://panel.example.test');
    app(ExtensionSettingsRepository::class)->set('pterodactyl', 'application_api_key', '[REDACTED]', secret: true);

    Http::fake([
        'https://panel.example.test/*' => Http::response(['errors' => [['detail' => '[REDACTED] bad']]], 500),
    ]);

    try {
        app(HttpPterodactylApi::class)->connectionTest();
        $this->fail('Expected provider exception');
    } catch (PterodactylProviderException $exception) {
        expect($exception->errorKey)->not->toContain('[REDACTED]')
            ->and($exception->getMessage())->not->toContain('[REDACTED]');
    }
});

test('http adapter treats timeouts as a safe failure', function () {
    installAndEnableModule('provisioning');
    installAndEnableExtension('pterodactyl');
    app(ExtensionSettingsRepository::class)->set('pterodactyl', 'panel_url', 'https://panel.example.test');
    app(ExtensionSettingsRepository::class)->set('pterodactyl', 'application_api_key', '[REDACTED]', secret: true);

    Http::fake(function () {
        throw new ConnectionException('timed out talking to [REDACTED]');
    });

    expect(fn () => app(HttpPterodactylApi::class)->connectionTest())
        ->toThrow(PterodactylProviderException::class);
});

test('core and modules do not import pterodactyl or mollie extension types', function () {
    foreach ([base_path('app'), optionalModuleRoot()] as $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            expect($contents)
                ->not->toContain('Agovena\\Extensions\\Pterodactyl\\')
                ->not->toContain('Agovena\\Extensions\\Mollie\\')
                ->not->toContain('Mollie\\Api\\');
        }
    }
});
