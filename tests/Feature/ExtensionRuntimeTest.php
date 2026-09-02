<?php

declare(strict_types=1);

use Agovena\Extensions\Mollie\MollieApi;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Catalog\GetStorefrontProduct;
use App\Agovena\Catalog\ListStorefrontProducts;
use App\Agovena\Extensions\ExtensionCategory;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionManifest;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Payments\AvailablePaymentMethods;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Livewire\Admin\Extensions\Index as ExtensionsIndex;
use App\Models\AgovenaExtension;
use App\Models\AgovenaModule;
use App\Models\ExtensionSetting;
use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\Support\FakeMollieApi;

uses(CreatesStaff::class);

function enableMollieForRuntimeTests(): ExtensionManager
{
    app(ExtensionManager::class)->discover();
    app()->instance(MollieApi::class, new FakeMollieApi);

    return installAndEnableExtension('mollie');
}

test('extension enable fails when extension is not installed', function () {
    AgovenaExtension::query()->where('extension_id', 'mollie')->delete();

    expect(fn () => app(ExtensionManager::class)->enable('mollie'))
        ->toThrow(ValidationException::class, 'Install Extension mollie before enabling it.');
});

test('module-bound extensions cannot be enabled without their parent module', function () {
    AgovenaModule::query()->where('module_id', 'provisioning')->delete();
    $extensions = app(ExtensionManager::class);

    expect(fn () => $extensions->install('pterodactyl'))
        ->toThrow(ValidationException::class, 'Install Module provisioning before installing Extension pterodactyl.');
});

test('disabling a parent module disables its extensions and preserves configured products', function (): void {
    $modules = app(ModuleManager::class);
    $extensions = app(ExtensionManager::class);

    installAndEnableModule('provisioning');
    installAndEnableExtension('pterodactyl');

    $product = Product::factory()->active()->create(['name' => 'Game server']);
    app(ProductCapabilityManager::class)->enable($product, 'provisionable', [
        'provider_key' => 'pterodactyl',
        'server_id' => null,
        'provider_settings' => ['nest_id' => 1],
    ]);
    app(ExtensionSettingsRepository::class)->set('pterodactyl', 'panel_url', 'https://panel.example.test');

    $modules->disable('provisioning');

    expect($modules->isEnabled('provisioning'))->toBeFalse()
        ->and($extensions->isEnabled('pterodactyl'))->toBeFalse()
        ->and($product->refresh()->capability('provisionable'))->not->toBeNull()
        ->and($product->capability('provisionable')?->runtimeConfig())->toMatchArray([
            'provider_key' => 'pterodactyl',
            'provider_settings' => ['nest_id' => 1],
        ])
        ->and(app(ExtensionSettingsRepository::class)->get('pterodactyl', 'panel_url'))->toBe('https://panel.example.test');
});

test('products using a disabled module capability cannot be exposed or added to the cart', function (): void {
    installAndEnableModule('provisioning');
    installAndEnableExtension('pterodactyl');

    $product = Product::factory()->active()->create(['name' => 'Unavailable game server']);
    app(ProductCapabilityManager::class)->enable($product, 'provisionable', [
        'provider_key' => 'pterodactyl',
    ]);

    app(ModuleManager::class)->disable('provisioning');

    expect(app(ListStorefrontProducts::class)->handle())->not->toContain($product)
        ->and(fn () => app(GetStorefrontProduct::class)->handle($product->slug))
        ->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(CartService::class)->add($product->id))
        ->toThrow(ValidationException::class, 'This product is not available for purchase.');
});

test('disabling a provider extension invalidates existing product use without deleting its configuration', function (): void {
    installAndEnableModule('provisioning');
    installAndEnableExtension('pterodactyl');

    $product = Product::factory()->active()->create(['name' => 'Provider-backed game server']);
    app(ProductCapabilityManager::class)->enable($product, 'provisionable', [
        'provider_key' => 'pterodactyl',
        'provider_settings' => ['nest_id' => 1],
    ]);
    app(CartService::class)->add($product->id);

    app(ExtensionManager::class)->disable('pterodactyl');

    expect(app(ExtensionManager::class)->isEnabled('pterodactyl'))->toBeFalse()
        ->and($product->refresh()->capability('provisionable'))->not->toBeNull()
        ->and($product->capability('provisionable')?->runtimeConfig())->toMatchArray([
            'provider_key' => 'pterodactyl',
            'provider_settings' => ['nest_id' => 1],
        ])
        ->and(app(CartService::class)->lines())->toBe([])
        ->and(fn () => app(GetStorefrontProduct::class)->handle($product->slug))
        ->toThrow(ModelNotFoundException::class);
});

test('extension manager discovers mollie payment extension', function () {
    $extensions = app(ExtensionManager::class);
    $manifest = $extensions->manifest('mollie');

    expect($manifest)->not->toBeNull()
        ->and($manifest->category)->toBe(ExtensionCategory::PaymentGateway)
        ->and($manifest->author)->toBe('Agovena')
        ->and($extensions->isEnabled('mollie'))->toBeFalse()
        ->and(str_replace('\\', '/', $manifest->path))->toContain('extensions/payments/mollie');
});

test('extension discovery keeps stable ids across category folder layout', function () {
    $extensions = app(ExtensionManager::class);
    $ids = collect($extensions->discover())->pluck('id')->sort()->values()->all();

    expect($ids)->toContain('mollie', 'stripe', 'paypal', 'pterodactyl', 'proxmox', 'postnl')
        ->and($ids)->not->toContain('manual-payment');

    foreach (['mollie' => 'payments', 'paypal' => 'payments', 'pterodactyl' => 'provisioning', 'proxmox' => 'provisioning', 'postnl' => 'shipping'] as $id => $categoryDir) {
        $path = str_replace('\\', '/', (string) $extensions->manifest($id)?->path);
        expect($path)->toContain($categoryDir.'/'.$id);
    }
});

test('extension manifest validation rejects unknown category', function () {
    expect(fn () => ExtensionManifest::fromArray([
        'id' => 'bad',
        'name' => 'Bad',
        'provider' => 'App\\DoesNotExist',
        'category' => 'not-a-real-category',
    ], storage_path('app/packages/extensions/bad')))->toThrow(InvalidArgumentException::class);
});

test('extension enable fails when platform version is incompatible', function () {
    app(ExtensionManager::class)->install('mollie');
    config(['agovena.version' => '0.1.0']);

    expect(fn () => app(ExtensionManager::class)->enable('mollie'))
        ->toThrow(ValidationException::class);
});

test('extensions without production readiness cannot be installed or enabled in production', function () {
    app()['env'] = 'production';
    installAndEnableModule('provisioning');
    $extensions = app(ExtensionManager::class);
    $manifest = $extensions->manifest('cpanel');

    expect($manifest)->not->toBeNull()
        ->and($manifest?->productionReady)->toBeFalse()
        ->and(fn () => $extensions->install('cpanel'))->toThrow(ValidationException::class)
        ->and(fn () => $extensions->enable('cpanel'))->toThrow(ValidationException::class);
});

test('extensions without production readiness cannot be installed or enabled in staging', function () {
    app()['env'] = 'staging';
    installAndEnableModule('provisioning');
    $extensions = app(ExtensionManager::class);

    expect(fn () => $extensions->install('cpanel'))->toThrow(ValidationException::class)
        ->and(fn () => $extensions->enable('cpanel'))->toThrow(ValidationException::class);
});

test('runtime does not boot a non-production-ready extension from a legacy enabled row', function () {
    app()['env'] = 'production';
    installAndEnableModule('provisioning');
    $extensions = app(ExtensionManager::class);
    AgovenaExtension::query()->updateOrCreate(
        ['extension_id' => 'cpanel'],
        ['version' => '1.0.0', 'installed_at' => now(), 'enabled' => true],
    );

    $extensions->rebuildRuntime();

    expect(app(ProvisionerRegistry::class)->get('cpanel'))->toBeNull();
});

test('enable registers payment gateway and disable removes it while preserving settings', function () {
    $extensions = enableMollieForRuntimeTests();
    $settings = app(ExtensionSettingsRepository::class);

    expect($extensions->isEnabled('mollie'))->toBeTrue()
        ->and(app(PaymentGatewayRegistry::class)->has('mollie'))->toBeTrue();

    $settings->set('mollie', 'api_key', 'test_key_not_real', secret: true);

    $row = ExtensionSetting::query()
        ->where('extension_id', 'mollie')
        ->where('key', 'api_key')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->is_secret)->toBeTrue()
        ->and($row->value)->not->toBe('test_key_not_real')
        ->and(Crypt::decryptString((string) $row->value))->toBe('test_key_not_real')
        ->and($settings->get('mollie', 'api_key'))->toBe('test_key_not_real');

    $extensions->disable('mollie');

    expect($extensions->isEnabled('mollie'))->toBeFalse()
        ->and(app(PaymentGatewayRegistry::class)->has('mollie'))->toBeFalse()
        ->and(AgovenaExtension::query()->where('extension_id', 'mollie')->exists())->toBeTrue()
        ->and($settings->get('mollie', 'api_key'))->toBe('test_key_not_real');
});

test('checkout discovers enabled extension payment methods', function () {
    enableMollieForRuntimeTests();

    $options = app(AvailablePaymentMethods::class)->options();

    expect($options)->not->toBeEmpty()
        ->and(collect($options)->pluck('id')->all())->toContain('mollie');
});

test('admin extensions page lists mollie', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(ExtensionsIndex::class)
        ->set('tab', 'available')
        ->assertOk()
        ->assertSee('Mollie');
});

test('core payment contracts do not import vendor SDKs', function () {
    $files = glob(base_path('app/Agovena/Payments/**/*.php')) ?: [];
    $files = array_merge($files, glob(base_path('app/Agovena/Payments/*.php')) ?: []);
    $files = array_merge($files, glob(base_path('app/Agovena/Payments/Contracts/*.php')) ?: []);
    $files = array_merge($files, glob(base_path('app/Agovena/Payments/Gateways/*.php')) ?: []);

    foreach (array_unique($files) as $file) {
        $contents = (string) file_get_contents($file);
        expect($contents)
            ->not->toContain('Mollie')
            ->not->toContain('Stripe\\')
            ->not->toContain('mollie/')
            ->not->toContain('stripe/');
    }
});

test('without payment extensions checkout offers development when enabled', function () {
    app(PaymentGatewayRegistry::class)->clear();
    config(['agovena.payments.allow_development_instant_pay' => true]);

    expect(app(AvailablePaymentMethods::class)->ids())->toBe(['development']);
});

test('without payment extensions checkout offers no methods when development pay is disabled', function () {
    app(PaymentGatewayRegistry::class)->clear();
    config(['agovena.payments.allow_development_instant_pay' => false]);

    expect(app(AvailablePaymentMethods::class)->ids())->toBe([]);
});
