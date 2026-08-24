<?php

declare(strict_types=1);

use App\Agovena\Extensions\ExtensionCategory;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionManifest;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Payments\AvailablePaymentMethods;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Livewire\Admin\Extensions\Index as ExtensionsIndex;
use App\Models\AgovenaExtension;
use App\Models\AgovenaModule;
use App\Models\ExtensionSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function enableManualPaymentExtension(): ExtensionManager
{
    return installAndEnableExtension('manual-payment');
}

test('extension enable fails when extension is not installed', function () {
    AgovenaExtension::query()->where('extension_id', 'manual-payment')->delete();

    expect(fn () => app(ExtensionManager::class)->enable('manual-payment'))
        ->toThrow(ValidationException::class, 'Install Extension manual-payment before enabling it.');
});

test('module-bound extensions cannot be enabled without their parent module', function () {
    AgovenaModule::query()->where('module_id', 'provisioning')->delete();
    $extensions = app(ExtensionManager::class);

    expect(fn () => $extensions->install('pterodactyl'))
        ->toThrow(ValidationException::class, 'Install Module provisioning before installing Extension pterodactyl.');
});

test('extension manager discovers manual payment extension', function () {
    $extensions = app(ExtensionManager::class);
    $manifest = $extensions->manifest('manual-payment');

    expect($manifest)->not->toBeNull()
        ->and($manifest->category)->toBe(ExtensionCategory::PaymentGateway)
        ->and($manifest->author)->toBe('Agovena')
        ->and($manifest->version)->toBe('1.0.0')
        ->and($extensions->isEnabled('manual-payment'))->toBeFalse()
        ->and(str_replace('\\', '/', $manifest->path))->toContain('extensions/payments/manual-payment');
});

test('extension discovery keeps stable ids across category folder layout', function () {
    $extensions = app(ExtensionManager::class);
    $ids = collect($extensions->discover())->pluck('id')->sort()->values()->all();

    expect($ids)->toContain('manual-payment', 'mollie', 'stripe', 'paypal', 'pterodactyl', 'proxmox', 'postnl');

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
    config(['agovena.version' => '0.0.1']);

    expect(fn () => app(ExtensionManager::class)->enable('manual-payment'))
        ->toThrow(ValidationException::class);
});

test('enable registers payment gateway and disable removes it while preserving settings', function () {
    $extensions = enableManualPaymentExtension();
    $settings = app(ExtensionSettingsRepository::class);

    expect($extensions->isEnabled('manual-payment'))->toBeTrue()
        ->and(app(PaymentGatewayRegistry::class)->has('manual'))->toBeTrue()
        ->and(app(AvailablePaymentMethods::class)->ids())->toContain('manual');

    $settings->set('manual-payment', 'instructions', 'Pay via IBAN', secret: false);
    $settings->set('manual-payment', 'webhook_secret', 'super-secret-value', secret: true);

    $row = ExtensionSetting::query()
        ->where('extension_id', 'manual-payment')
        ->where('key', 'webhook_secret')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->is_secret)->toBeTrue()
        ->and($row->value)->not->toBe('super-secret-value')
        ->and(Crypt::decryptString((string) $row->value))->toBe('super-secret-value')
        ->and($settings->get('manual-payment', 'webhook_secret'))->toBe('super-secret-value');

    $extensions->disable('manual-payment');

    expect($extensions->isEnabled('manual-payment'))->toBeFalse()
        ->and(app(PaymentGatewayRegistry::class)->has('manual'))->toBeFalse()
        ->and(AgovenaExtension::query()->where('extension_id', 'manual-payment')->exists())->toBeTrue()
        ->and($settings->get('manual-payment', 'instructions'))->toBe('Pay via IBAN')
        ->and($settings->get('manual-payment', 'webhook_secret'))->toBe('super-secret-value');
});

test('checkout discovers enabled extension payment methods', function () {
    enableManualPaymentExtension();

    $options = app(AvailablePaymentMethods::class)->options();

    expect($options)->not->toBeEmpty()
        ->and(collect($options)->pluck('id')->all())->toContain('manual');
});

test('admin extensions page lists manual payment', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(ExtensionsIndex::class)
        ->set('tab', 'available')
        ->assertOk()
        ->assertSee('Manual Payment');
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
