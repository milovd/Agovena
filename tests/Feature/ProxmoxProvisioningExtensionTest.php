<?php

declare(strict_types=1);

use Agovena\Extensions\Proxmox\HttpProxmoxApi;
use Agovena\Extensions\Proxmox\ProxmoxApi;
use Agovena\Extensions\Proxmox\ProxmoxApiUrl;
use Agovena\Extensions\Proxmox\ProxmoxProviderException;
use Agovena\Extensions\Proxmox\ProxmoxProvisioner;
use Agovena\Extensions\Proxmox\ProxmoxVm;
use Agovena\Modules\Provisioning\EloquentProvisionedServiceResolver;
use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Http\Livewire\Admin\Servers as ProvisioningServers;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Provisioning\ProvisioningOrchestrator;
use Agovena\Modules\Provisioning\ProvisioningService;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\PaymentGatewayCapabilities;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Agovena\Provisioning\ServiceInstanceInfo;
use App\Models\AgovenaModule;
use App\Models\Customer;
use App\Models\ExtensionSetting;
use App\Models\Product;
use App\Models\ProvisioningServer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\Support\FakeProxmoxApi;

uses(CreatesStaff::class);

function enableProxmox(?FakeProxmoxApi $api = null): FakeProxmoxApi
{
    installAndEnableModule('provisioning');
    app(SyncRegisteredPermissions::class)(force: true);
    app(ExtensionManager::class)->discover();
    $api ??= new FakeProxmoxApi;
    app()->instance(ProxmoxApi::class, $api);
    installAndEnableExtension('proxmox');
    app()->forgetInstance(ProxmoxProvisioner::class);
    app(ExtensionManager::class)->rebuildRuntime();

    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('proxmox', 'api_url', 'https://pve.example.test:8006');
    $settings->set('proxmox', 'token_user', 'root@pam');
    $settings->set('proxmox', 'token_id', 'agovena');
    $settings->set('proxmox', 'token_secret', '[REDACTED]', secret: true);
    $settings->set('proxmox', 'node', 'pve1');
    $settings->set('proxmox', 'storage', 'local-lvm');
    $settings->set('proxmox', 'verify_tls', true);
    $settings->set('proxmox', 'timeout', '30');

    return $api;
}

function proxmoxBilling(): AddressData
{
    return AddressData::fromArray([
        'name' => 'VM Buyer',
        'line1' => 'Street 1',
        'city' => 'Amsterdam',
        'postal_code' => '1000 AA',
        'country' => 'NL',
    ]);
}

/** @param array<string, mixed> $settings */
function makeProxmoxProduct(array $settings = []): Product
{
    $product = Product::factory()->active()->create(['price_amount' => 5000]);
    app(ProductCapabilityManager::class)->enable($product, 'provisionable', [
        'provider_key' => 'proxmox',
        'provider_settings' => array_merge([
            'template_vmid' => '9000',
            'cores' => '2',
            'memory' => '2048',
            'disk' => '40',
            'autostart' => '1',
        ], $settings),
    ]);

    return $product->fresh(['capabilities']);
}

function payForProxmoxProduct(Product $product, ?Customer $customer = null): ServiceInstance
{
    config(['agovena.payments.allow_development_instant_pay' => true]);
    $customer ??= Customer::factory()->create();
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => proxmoxBilling(),
        'payment_method' => 'development',
    ]);

    return ServiceInstance::query()->where('order_id', $order->id)->firstOrFail();
}

test('proxmox checkout blocks when the selected node lacks capacity', function () {
    $api = enableProxmox();
    $api->nodeCapacity = [
        'memory_free' => 1024 * 1024 * 1024,
        'cpu_cores' => 100,
        'storage_free' => 1024 * 1024 * 1024 * 1024,
    ];

    expect(fn () => payForProxmoxProduct(makeProxmoxProduct()))
        ->toThrow(ValidationException::class)
        ->and($api->cloneCalls)->toBe(0);
});

test('proxmox canonical capacity urls reject paths queries fragments and credentials', function () {
    enableProxmox();

    foreach ([
        'https://pve.example.test/api2/json',
        'https://pve.example.test?cluster=one',
        'https://pve.example.test#cluster',
        'https://user:pass@pve.example.test',
    ] as $url) {
        expect(fn () => ProxmoxApiUrl::normalize($url))
            ->toThrow(ProxmoxProviderException::class);
    }

    expect(ProxmoxApiUrl::normalize('https://pve.example.test:8006/'))
        ->toBe('https://pve.example.test:8006');
});

test('proxmox verify tls rejects non-boolean values before making a request', function () {
    enableProxmox();
    $api = new HttpProxmoxApi(app(ExtensionSettingsRepository::class), [
        'api_url' => 'https://pve.example.test:8006',
        'token_user' => 'root@pam',
        'token_id' => 'agovena',
        'token_secret' => '[REDACTED]',
        'verify_tls' => 'sometimes',
    ]);
    Http::fake();

    expect(fn () => $api->connectionTest())
        ->toThrow(ProxmoxProviderException::class);
    Http::assertNothingSent();
});

test('proxmox power and delete operations reject successful responses without valid tasks', function () {
    enableProxmox();
    $api = new HttpProxmoxApi(app(ExtensionSettingsRepository::class), [
        'api_url' => 'https://pve.example.test:8006',
        'token_user' => 'root@pam',
        'token_id' => 'agovena',
        'token_secret' => '[REDACTED]',
    ]);
    Http::fake(fn () => Http::response(['data' => 'not-a-upid'], 200));

    foreach (['start', 'stop', 'deleteVm'] as $operation) {
        expect(fn () => $api->{$operation}('pve1', 200))
            ->toThrow(ProxmoxProviderException::class);
    }
});

test('proxmox plan changes send the complete target vm configuration', function () {
    $api = enableProxmox();
    $instance = payForProxmoxProduct(makeProxmoxProduct());

    app(ProxmoxProvisioner::class)->changePlan(EloquentProvisionedServiceResolver::info($instance), [
        'id' => 'target-plan',
        'provider_settings' => [
            'cores' => '4',
            'sockets' => '2',
            'memory' => '4096',
            'disk' => '80',
            'cpu_type' => 'kvm64',
            'bridge' => 'vmbr1',
            'autostart' => false,
        ],
    ]);

    $mapping = ProxmoxVm::query()->where('service_instance_id', $instance->id)->firstOrFail();
    expect($api->vms[$mapping->vmid]['config'])->toMatchArray([
        'cores' => 4,
        'sockets' => 2,
        'memory' => 4096,
        'cpu' => 'kvm64',
        'scsi0' => 'local-lvm:vm-'.$mapping->vmid.'-disk-0,size=80G',
        'net0' => 'virtio=AA:BB:CC:DD:EE:FF,bridge=vmbr1',
        'onboot' => 0,
    ]);
});

test('proxmox rejects malformed vm ids instead of casting them', function () {
    enableProxmox();
    $api = new HttpProxmoxApi(app(ExtensionSettingsRepository::class), [
        'api_url' => 'https://pve.example.test:8006',
        'token_user' => 'root@pam',
        'token_id' => 'agovena',
        'token_secret' => '[REDACTED]',
    ]);
    Http::fake([
        'https://pve.example.test:8006/api2/json/nodes/pve1/qemu' => Http::response([
            'data' => [['name' => 'managed-vm', 'vmid' => 0]],
        ]),
    ]);

    expect(fn () => $api->findVmByName('pve1', 'managed-vm'))
        ->toThrow(ProxmoxProviderException::class);
});

test('proxmox treats a malformed successful vm config as an error', function () {
    enableProxmox();
    $api = new HttpProxmoxApi(app(ExtensionSettingsRepository::class), [
        'api_url' => 'https://pve.example.test:8006',
        'token_user' => 'root@pam',
        'token_id' => 'agovena',
        'token_secret' => '[REDACTED]',
    ]);
    Http::fake([
        'https://pve.example.test:8006/api2/json/nodes/pve1/qemu/200/config' => Http::response([
            'data' => 'malformed',
        ]),
    ]);

    expect(fn () => $api->findVmConfig('pve1', 200))
        ->toThrow(ProxmoxProviderException::class);
});

test('proxmox rejects malformed nonmatching vm entries', function () {
    enableProxmox();
    $api = new HttpProxmoxApi(app(ExtensionSettingsRepository::class), [
        'api_url' => 'https://pve.example.test:8006',
        'token_user' => 'root@pam',
        'token_id' => 'agovena',
        'token_secret' => '[REDACTED]',
    ]);
    Http::fake([
        'https://pve.example.test:8006/api2/json/nodes/pve1/qemu' => Http::response([
            'data' => [
                ['name' => 'other-vm', 'vmid' => 0],
                ['name' => 'managed-vm', 'vmid' => 200],
            ],
        ]),
    ]);

    expect(fn () => $api->findVmByName('pve1', 'managed-vm'))
        ->toThrow(ProxmoxProviderException::class);
});

test('proxmox rejects a successful status response without status', function () {
    enableProxmox();
    $api = new HttpProxmoxApi(app(ExtensionSettingsRepository::class), [
        'api_url' => 'https://pve.example.test:8006',
        'token_user' => 'root@pam',
        'token_id' => 'agovena',
        'token_secret' => '[REDACTED]',
    ]);
    Http::fake([
        'https://pve.example.test:8006/api2/json/nodes/pve1/qemu/200/status/current' => Http::response(['data' => []]),
    ]);

    expect(fn () => $api->currentStatus('pve1', 200))
        ->toThrow(ProxmoxProviderException::class);
});

test('proxmox rejects a stopped task without an exit status', function () {
    enableProxmox();
    $api = new HttpProxmoxApi(app(ExtensionSettingsRepository::class), [
        'api_url' => 'https://pve.example.test:8006',
        'token_user' => 'root@pam',
        'token_id' => 'agovena',
        'token_secret' => '[REDACTED]',
    ]);
    Http::fake([
        'https://pve.example.test:8006/api2/json/nodes/pve1/qemu/9000/clone' => Http::response(['data' => 'UPID:test']),
        'https://pve.example.test:8006/api2/json/nodes/pve1/tasks/UPID%3Atest/status' => Http::response(['data' => ['status' => 'stopped']]),
    ]);

    expect(fn () => $api->cloneVm('pve1', 9000, ['newid' => 200]))
        ->toThrow(ProxmoxProviderException::class);
});

test('proxmox server validation checks the configured node and storage', function () {
    $api = enableProxmox();
    $result = app(ProxmoxProvisioner::class)->testServer([
        'api_url' => 'https://pve.example.test:8006',
        'token_user' => 'root@pam',
        'token_id' => 'agovena',
        'token_secret' => '[REDACTED]',
        'node' => '',
        'storage' => '',
    ]);

    expect($result->ok)->toBeFalse()
        ->and($api->nodeCapacity)->not->toBe([]);
});

test('proxmox plan changes fail closed when target placement differs from the mapped vm', function () {
    $api = enableProxmox();
    $instance = payForProxmoxProduct(makeProxmoxProduct());
    $info = EloquentProvisionedServiceResolver::info($instance);
    $targetServerSettings = [
        'api_url' => 'https://pve.example.test:8006',
        'token_user' => 'root@pam',
        'token_id' => 'agovena',
        'token_secret' => '[REDACTED]',
        'node' => 'pve2',
        'storage' => 'fast-lvm',
    ];
    $info = new ServiceInstanceInfo(
        id: $info->id,
        label: $info->label,
        status: $info->status,
        providerKey: $info->providerKey,
        externalRef: $info->externalRef,
        meta: array_merge($info->meta, [
            'server_settings_required' => true,
            'server_settings' => $targetServerSettings,
        ]),
    );

    expect(fn () => app(ProxmoxProvisioner::class)->changePlan($info, [
        'id' => 'target-plan',
        'provider_settings' => ['cores' => '4'],
    ]))->toThrow(ValidationException::class);
});

test('plan normalization rejects an explicit false server target', function () {
    enableProxmox();
    $instance = payForProxmoxProduct(makeProxmoxProduct());

    expect(fn () => app(ProvisioningOrchestrator::class)->changePlan($instance, [
        'id' => 'target-plan',
        'server_id' => false,
    ]))->toThrow(ValidationException::class);
});

test('proxmox registers only when the extension is enabled', function () {
    installAndEnableModule('provisioning');

    expect(app(ProvisionerRegistry::class)->get('proxmox'))->toBeNull();

    enableProxmox();

    expect(app(ProvisionerRegistry::class)->get('proxmox'))->toBeInstanceOf(ProxmoxProvisioner::class);

    app(ExtensionManager::class)->disable('proxmox');

    expect(app(ProvisionerRegistry::class)->get('proxmox'))->toBeNull();
});

test('server connections are extension driven and encrypt proxmox token secrets', function () {
    enableProxmox();

    Livewire::actingAs($this->createStaff())
        ->test(ProvisioningServers::class)
        ->set('name', 'Primary cluster')
        ->set('providerKey', 'proxmox')
        ->set('settings.api_url', 'https://pve.example.test:8006')
        ->set('settings.token_user', 'root@pam')
        ->set('settings.token_id', 'agovena')
        ->set('settings.token_secret', '[REDACTED]')
        ->set('settings.node', 'pve1')
        ->set('settings.storage', 'local-lvm')
        ->call('save')
        ->assertHasNoErrors();

    $server = ProvisioningServer::query()->where('provider_key', 'proxmox')->firstOrFail();
    $raw = (string) DB::table('provisioning_servers')->where('id', $server->id)->value('settings');

    expect($server->settings['token_secret'])->toBe('[REDACTED]')
        ->and($raw)->not->toContain('[REDACTED]');

    $row = ExtensionSetting::query()
        ->where('extension_id', 'proxmox')
        ->where('key', 'token_secret')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->is_secret)->toBeTrue()
        ->and(Crypt::decryptString((string) $row->value))->toBe('[REDACTED]');
});

test('proxmox sync recovers an unmapped vm by deterministic hostname', function () {
    $api = enableProxmox();
    $api->vms[321] = [
        'node' => 'pve1',
        'name' => 'agovena-777',
        'config' => [
            'description' => 'agovena-service-instance:777',
            'scsi0' => 'local-lvm:vm-321-disk-0,size=40G',
            'net0' => 'virtio=AA:BB:CC:DD:EE:FF,bridge=vmbr0',
        ],
    ];
    $api->statusByKey['pve1:321'] = ['status' => 'running'];
    $instance = new ServiceInstanceInfo(
        id: 777,
        label: 'Recovered VM',
        status: 'provisioning',
        providerKey: 'proxmox',
        externalRef: null,
        meta: [
            'server_settings' => ['node' => 'pve1', 'storage' => 'local-lvm'],
            'provider_settings' => [
                'template_vmid' => '9000',
                'cores' => '2',
                'memory' => '2048',
                'disk' => '40',
                'sockets' => '1',
                'cpu_type' => 'host',
                'bridge' => 'vmbr0',
                'autostart' => true,
            ],
        ],
    );

    $updated = app(ProxmoxProvisioner::class)->syncStatus($instance);

    expect(ProxmoxVm::query()->where('service_instance_id', 777)->value('vmid'))->toBe(321)
        ->and($updated->externalRef)->toBe('321');
});

test('proxmox unknown task status fails closed', function () {
    Http::fake([
        'https://pve.invalid/*' => Http::response(['data' => ['status' => 'queued']], 200),
    ]);
    $api = new HttpProxmoxApi(app(ExtensionSettingsRepository::class), [
        'api_url' => 'https://pve.invalid',
        'token_user' => 'test-user',
        'token_id' => 'test-token',
        'token_secret' => '[REDACTED]',
    ]);
    $method = new ReflectionMethod(HttpProxmoxApi::class, 'waitForTask');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($api, 'pve1', 'UPID:test', 1))
        ->toThrow(ProxmoxProviderException::class);
});

test('proxmox malformed next vm id fails closed', function () {
    enableProxmox();
    Http::fake([
        'https://pve.invalid/*' => Http::response(['data' => 'not-a-vm-id'], 200),
    ]);
    $api = new HttpProxmoxApi(app(ExtensionSettingsRepository::class), [
        'api_url' => 'https://pve.invalid',
        'token_user' => 'test-user',
        'token_id' => 'test-token',
        'token_secret' => '[REDACTED]',
    ]);

    expect(fn () => $api->nextVmId())
        ->toThrow(ProxmoxProviderException::class);
});

test('server scoped provisioning uses the encrypted placement snapshot after server changes', function () {
    $api = enableProxmox();
    $server = ProvisioningServer::query()->create([
        'name' => 'Snapshot server',
        'provider_key' => 'proxmox',
        'settings' => [
            'api_url' => 'https://pve.invalid:8006',
            'token_user' => 'test-user',
            'token_id' => 'test-token',
            'token_secret' => '[REDACTED]',
            'node' => 'pve-a',
            'storage' => 'local-lvm',
        ],
        'is_active' => true,
    ]);
    $product = makeProxmoxProduct();
    $capability = $product->capability('provisionable');
    $config = $capability->config;
    $config['server_id'] = $server->id;
    $capability->config = $config;
    $capability->save();
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
        'billing' => proxmoxBilling(),
        'payment_method' => 'pending-test',
    ]);

    $server->settings = array_merge($server->settings, ['node' => 'pve-b']);
    $server->save();
    app(ProvisioningService::class)->createFromPaidOrder($order->fresh(['items']));

    $instance = ServiceInstance::query()->where('order_id', $order->id)->firstOrFail();
    expect($api->vms[200]['node'] ?? null)->toBe('pve-a')
        ->and($instance->server_settings_snapshot['node'] ?? null)->toBe('pve-a');
});

test('paid proxmox order provisions a vm and stores extension-owned mapping', function () {
    $api = enableProxmox();
    $instance = payForProxmoxProduct(makeProxmoxProduct());

    expect($instance->status)->toBe(ServiceInstanceStatus::Active)
        ->and($instance->provider_key)->toBe('proxmox')
        ->and($api->cloneCalls)->toBe(1)
        ->and(ProxmoxVm::query()->where('service_instance_id', $instance->id)->exists())->toBeTrue()
        ->and(Schema::hasTable('proxmox_vms'))->toBeTrue();
});

test('proxmox retries a post-clone failure without cloning a second vm', function () {
    $api = enableProxmox();
    $api->failStart = true;
    $instance = payForProxmoxProduct(makeProxmoxProduct());
    $firstCloneCalls = $api->cloneCalls;
    $mapping = ProxmoxVm::query()->where('service_instance_id', $instance->id)->firstOrFail();

    $api->failStart = false;
    $retried = app(ProvisioningOrchestrator::class)->provision($instance->fresh());

    expect($mapping->vmid)->toBe(200)
        ->and($api->cloneCalls)->toBe($firstCloneCalls)
        ->and($retried->fresh()->status)->toBe(ServiceInstanceStatus::Active);
});

test('proxmox suspend and unsuspend mutate vm power state', function () {
    $api = enableProxmox();
    $instance = payForProxmoxProduct(makeProxmoxProduct());
    $mapping = ProxmoxVm::query()->where('service_instance_id', $instance->id)->firstOrFail();
    $orchestrator = app(ProvisioningOrchestrator::class);

    $instance = $orchestrator->suspend($instance);
    expect($instance->status)->toBe(ServiceInstanceStatus::Suspended)
        ->and($api->currentStatus($mapping->node, $mapping->vmid)['status'])->toBe('stopped');

    $instance = $orchestrator->unsuspend($instance);
    expect($instance->status)->toBe(ServiceInstanceStatus::Active)
        ->and($api->currentStatus($mapping->node, $mapping->vmid)['status'])->toBe('running');
});

test('proxmox terminate removes mapping and vm', function () {
    $api = enableProxmox();
    $instance = payForProxmoxProduct(makeProxmoxProduct());
    $mapping = ProxmoxVm::query()->where('service_instance_id', $instance->id)->firstOrFail();
    $vmid = $mapping->vmid;

    $instance = app(ProvisioningOrchestrator::class)->terminate($instance);

    expect($instance->status)->toBe(ServiceInstanceStatus::Terminated)
        ->and(ProxmoxVm::query()->where('service_instance_id', $instance->id)->exists())->toBeFalse()
        ->and($api->findVmConfig($mapping->node, $vmid))->toBeNull()
        ->and($api->deleteCalls)->toBe(1);
});

test('proxmox install fails without provisioning module', function () {
    AgovenaModule::query()->where('module_id', 'provisioning')->delete();

    expect(fn () => app(ExtensionManager::class)->install('proxmox'))
        ->toThrow(ValidationException::class);
});

test('core and modules do not import proxmox extension types', function () {
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
                ->not->toContain('Agovena\\Extensions\\Proxmox\\');
        }
    }
});
