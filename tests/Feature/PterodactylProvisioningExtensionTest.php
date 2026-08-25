<?php

declare(strict_types=1);

use Agovena\Extensions\Pterodactyl\HttpPterodactylApi;
use Agovena\Extensions\Pterodactyl\PterodactylApi;
use Agovena\Extensions\Pterodactyl\PterodactylPanelUrl;
use Agovena\Extensions\Pterodactyl\PterodactylProviderException;
use Agovena\Extensions\Pterodactyl\PterodactylProvisioner;
use Agovena\Extensions\Pterodactyl\PterodactylServer;
use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Http\Livewire\Admin\Servers as ProvisioningServers;
use Agovena\Modules\Provisioning\Http\Livewire\Customer\ServiceShow;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Provisioning\ProvisioningOrchestrator;
use Agovena\Modules\Provisioning\ProvisioningService;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Provisioning\Contracts\Provisioner;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Agovena\Provisioning\RunProvisionerAction;
use App\Enums\ProductOptionType;
use App\Livewire\Admin\Products\Create as CreateProductForm;
use App\Livewire\Admin\Products\Edit as EditProductForm;
use App\Models\Customer;
use App\Models\ExtensionSetting;
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
    $api ??= new FakePterodactylApi;
    app()->instance(PterodactylApi::class, $api);
    installAndEnableModule('provisioning');
    app(SyncRegisteredPermissions::class)(force: true);
    installAndEnableExtension('pterodactyl');

    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('pterodactyl', 'panel_url', 'https://panel.example.test');
    $settings->set('pterodactyl', 'application_api_key', 'ptla_NEVER_LOG_THIS_SECRET', secret: true);
    $settings->set('pterodactyl', 'client_api_key', 'ptlc_NEVER_LOG_CLIENT_SECRET', secret: true);
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
        ->set('settings.application_api_key', 'ptla_SERVER_LEVEL_SECRET')
        ->set('settings.user_id', '1')
        ->call('save')
        ->assertHasNoErrors();

    $server = ProvisioningServer::query()->where('name', 'Primary panel')->firstOrFail();
    $raw = (string) DB::table('provisioning_servers')->where('id', $server->id)->value('settings');

    expect($server->settings['application_api_key'])->toBe('ptla_SERVER_LEVEL_SECRET')
        ->and($raw)->not->toContain('ptla_SERVER_LEVEL_SECRET');
});

test('product creation exposes and persists pterodactyl automation only when the integration is enabled', function () {
    enablePterodactyl();
    $server = ProvisioningServer::query()->create([
        'name' => 'Primary panel',
        'provider_key' => 'pterodactyl',
        'settings' => [
            'panel_url' => 'https://panel.example.test',
            'application_api_key' => 'ptla_SERVER_SECRET',
            'client_api_key' => 'ptlc_SERVER_SECRET',
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

test('pterodactyl credentials are encrypted and never redisplayed', function () {
    enablePterodactyl();
    $row = ExtensionSetting::query()
        ->where('extension_id', 'pterodactyl')
        ->where('key', 'application_api_key')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->is_secret)->toBeTrue()
        ->and($row->value)->not->toContain('ptla_NEVER_LOG_THIS_SECRET')
        ->and(Crypt::decryptString((string) $row->value))->toBe('ptla_NEVER_LOG_THIS_SECRET');
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
        ->and($instance->external_ref)->toBe('10');

    $api->serversById[10]['status'] = null;
    $instance = app(ProvisioningOrchestrator::class)->sync($instance);

    expect($instance->status)->toBe(ServiceInstanceStatus::Active);
});

test('duplicate provisioning retry does not create a second server', function () {
    $api = enablePterodactyl();
    $instance = payForPterodactylProduct(makePterodactylProduct());
    app(ProvisioningOrchestrator::class)->provision($instance);

    expect($api->createCalls)->toBe(1)
        ->and(PterodactylServer::query()->count())->toBe(1);
});

test('partial provisioning failure keeps the mapping and retry is idempotent', function () {
    $api = enablePterodactyl();
    $api->failGet = true;
    $instance = payForPterodactylProduct(makePterodactylProduct());

    expect($instance->status)->toBe(ServiceInstanceStatus::Failed)
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
        ->and($eggFailed->failure_message)->not->toContain('ptla_NEVER_LOG_THIS_SECRET');

    $api->failEgg = false;
    $api->failQuota = true;
    $quotaFailed = payForPterodactylProduct(makePterodactylProduct());
    expect($quotaFailed->status)->toBe(ServiceInstanceStatus::Failed)
        ->and($quotaFailed->failure_message)->toBe(__('pterodactyl::messages.errors.quota'));
});

test('invalid credentials and unreachable panel fail without leaking secrets', function () {
    $api = enablePterodactyl();
    $api->unauthorized = true;
    $unauthorized = payForPterodactylProduct(makePterodactylProduct());
    expect($unauthorized->failure_message)->toBe(__('pterodactyl::messages.errors.unauthorized'))
        ->and($unauthorized->failure_message)->not->toContain('ptla_NEVER_LOG_THIS_SECRET');

    $api->unauthorized = false;
    $api->unreachable = true;
    $unreachable = payForPterodactylProduct(makePterodactylProduct());
    expect($unreachable->failure_message)->toBe(__('pterodactyl::messages.errors.unreachable'));

    $api->unreachable = false;
    $api->timeout = true;
    $timeout = payForPterodactylProduct(makePterodactylProduct());
    expect($timeout->failure_message)->toBe(__('pterodactyl::messages.errors.timeout'));
});

test('malformed provider response is a safe failure', function () {
    $api = enablePterodactyl();
    $api->malformed = true;
    $instance = payForPterodactylProduct(makePterodactylProduct());

    expect($instance->status)->toBe(ServiceInstanceStatus::Failed)
        ->and($instance->failure_message)->toBe(__('pterodactyl::messages.errors.malformed'));
});

test('missing product mapping fails before creating a server', function () {
    $api = enablePterodactyl();
    $instance = payForPterodactylProduct(makePterodactylProduct([
        'location_id' => '',
        'nest_id' => '',
        'egg_id' => '',
    ]));

    expect($instance->status)->toBe(ServiceInstanceStatus::Failed)
        ->and($instance->failure_message)->toBe(__('pterodactyl::messages.errors.invalid_mapping'))
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
    $instance->meta = $meta;
    $instance->save();

    $instance = app(ProvisioningOrchestrator::class)->changePlan($instance, '2048');

    expect($api->buildCalls)->toBe(1)
        ->and($api->serversById[10]['limits']['memory'])->toBe(2048)
        ->and($instance->status)->toBe(ServiceInstanceStatus::Active);
});

test('plan change failure keeps the service active', function () {
    $api = enablePterodactyl();
    $api->failBuild = true;
    $instance = payForPterodactylProduct(makePterodactylProduct());

    expect(fn () => app(ProvisioningOrchestrator::class)->changePlan($instance, '4096'))
        ->toThrow(ValidationException::class);
    expect($instance->fresh()->status)->toBe(ServiceInstanceStatus::Active);
});

test('status sync failure does not change service state', function () {
    $api = enablePterodactyl();
    $instance = payForPterodactylProduct(makePterodactylProduct());
    $api->failGet = true;

    expect(fn () => app(ProvisioningOrchestrator::class)->sync($instance))
        ->toThrow(ValidationException::class);
    expect($instance->fresh()->status)->toBe(ServiceInstanceStatus::Active);
});

test('customer portal exposes panel link and safe power actions', function () {
    $api = enablePterodactyl();
    $customer = Customer::factory()->create();
    $instance = payForPterodactylProduct(makePterodactylProduct(), $customer);

    Livewire::actingAs($customer->user)
        ->test(ServiceShow::class, ['instance' => $instance])
        ->assertSee(__('pterodactyl::messages.actions.start'))
        ->assertSee('https://panel.example.test/server/abc10')
        ->assertDontSee('ptla_NEVER_LOG_THIS_SECRET')
        ->assertDontSee('ptlc_NEVER_LOG_CLIENT_SECRET')
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
        ->and($result->message)->not->toContain('ptla_NEVER_LOG_THIS_SECRET');

    $api->unauthorized = true;
    $failed = app(PterodactylProvisioner::class)->health();
    expect($failed->ok)->toBeFalse()
        ->and($failed->message)->not->toContain('ptla_NEVER_LOG_THIS_SECRET');
});

test('panel url validation allows private hosts and rejects credentials', function () {
    expect(PterodactylPanelUrl::normalize('https://192.168.10.20:8443'))->toBe('https://192.168.10.20:8443')
        ->and(fn () => PterodactylPanelUrl::normalize('ftp://panel.example.test'))
        ->toThrow(PterodactylProviderException::class)
        ->and(fn () => PterodactylPanelUrl::normalize('https://user:pass@panel.example.test'))
        ->toThrow(PterodactylProviderException::class);
});

test('http adapter maps panel errors without leaking secrets', function () {
    installAndEnableModule('provisioning');
    installAndEnableExtension('pterodactyl');
    app(ExtensionSettingsRepository::class)->set('pterodactyl', 'panel_url', 'https://panel.example.test');
    app(ExtensionSettingsRepository::class)->set('pterodactyl', 'application_api_key', 'ptla_NEVER_LOG_THIS_SECRET', secret: true);

    Http::fake([
        'https://panel.example.test/*' => Http::response(['errors' => [['detail' => 'ptla_NEVER_LOG_THIS_SECRET bad']]], 500),
    ]);

    try {
        app(HttpPterodactylApi::class)->connectionTest();
        $this->fail('Expected provider exception');
    } catch (PterodactylProviderException $exception) {
        expect($exception->errorKey)->not->toContain('ptla_NEVER_LOG_THIS_SECRET')
            ->and($exception->getMessage())->not->toContain('ptla_NEVER_LOG_THIS_SECRET');
    }
});

test('http adapter treats timeouts as a safe failure', function () {
    installAndEnableModule('provisioning');
    installAndEnableExtension('pterodactyl');
    app(ExtensionSettingsRepository::class)->set('pterodactyl', 'panel_url', 'https://panel.example.test');
    app(ExtensionSettingsRepository::class)->set('pterodactyl', 'application_api_key', 'ptla_NEVER_LOG_THIS_SECRET', secret: true);

    Http::fake(function () {
        throw new ConnectionException('timed out talking to ptla_NEVER_LOG_THIS_SECRET');
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
