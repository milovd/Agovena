<?php

declare(strict_types=1);

use Agovena\Extensions\Proxmox\ProxmoxApi;
use Agovena\Extensions\Proxmox\ProxmoxProvisioner;
use Agovena\Extensions\Proxmox\ProxmoxVm;
use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Http\Livewire\Admin\Servers as ProvisioningServers;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Provisioning\ProvisioningOrchestrator;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Models\AgovenaModule;
use App\Models\Customer;
use App\Models\ExtensionSetting;
use App\Models\Product;
use App\Models\ProvisioningServer;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
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
    $settings->set('proxmox', 'token_secret', 'NEVER_LOG_THIS_SECRET', secret: true);
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
    $customer ??= Customer::factory()->create();
    $staff = User::factory()->create();
    $staff->assignRole('owner');
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => proxmoxBilling(),
    ]);
    app(RecordManualPayment::class)->handle($order, $staff);

    return ServiceInstance::query()->where('order_id', $order->id)->firstOrFail();
}

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
        ->set('settings.token_secret', 'SERVER_LEVEL_SECRET')
        ->set('settings.node', 'pve1')
        ->set('settings.storage', 'local-lvm')
        ->call('save')
        ->assertHasNoErrors();

    $server = ProvisioningServer::query()->where('provider_key', 'proxmox')->firstOrFail();
    $raw = (string) DB::table('provisioning_servers')->where('id', $server->id)->value('settings');

    expect($server->settings['token_secret'])->toBe('SERVER_LEVEL_SECRET')
        ->and($raw)->not->toContain('SERVER_LEVEL_SECRET');

    $row = ExtensionSetting::query()
        ->where('extension_id', 'proxmox')
        ->where('key', 'token_secret')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->is_secret)->toBeTrue()
        ->and(Crypt::decryptString((string) $row->value))->toBe('NEVER_LOG_THIS_SECRET');
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
