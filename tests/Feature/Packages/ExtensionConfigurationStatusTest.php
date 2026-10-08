<?php

declare(strict_types=1);

use Agovena\Extensions\Postnl\PostnlCarrier;
use App\Agovena\Extensions\ExtensionConfigurationStatus;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Shipping\ShippingCarrierRegistry;
use Illuminate\Support\Facades\DB;

function freshConfigurationStatus(): ExtensionConfigurationStatus
{
    app()->forgetScopedInstances();

    return app(ExtensionConfigurationStatus::class);
}

test('an enabled extension with every required setting filled is configured', function () {
    installAndEnableExtension('mollie');
    app(ExtensionSettingsRepository::class)->set('mollie', 'api_key', 'test_configuredkey1234567890', secret: true);

    $status = freshConfigurationStatus()->for('mollie');

    expect($status)->not->toBeNull()
        ->and($status?->configured())->toBeTrue()
        ->and($status?->missingKeys)->toBe([])
        ->and($status?->missingLabels)->toBe([])
        ->and(freshConfigurationStatus()->isConfigured('mollie'))->toBeTrue();
});

test('a seeded empty secret does not count as a filled required setting', function () {
    installAndEnableExtension('mollie');

    $status = freshConfigurationStatus()->for('mollie');

    expect($status?->configured())->toBeFalse()
        ->and($status?->missingKeys)->toBe(['api_key'])
        ->and($status?->missingLabels)->toBe([__('mollie::messages.settings.api_key')]);
});

test('missing required settings are reported by label and never by value', function () {
    installAndEnableModule('shipping');
    installAndEnableExtension('postnl');
    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('postnl', 'api_key', 'super-secret-postnl-key', secret: true);
    $settings->set('postnl', 'customer_code', 'DEVC');

    $status = freshConfigurationStatus()->for('postnl');

    expect($status?->configured())->toBeFalse()
        ->and($status?->missingKeys)->toBe(['customer_number'])
        ->and(json_encode($status))->not->toContain('super-secret-postnl-key')
        ->and(json_encode($status))->not->toContain('DEVC');
});

test('an environment override fills a non secret required setting', function () {
    installAndEnableModule('shipping');
    installAndEnableExtension('postnl');
    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('postnl', 'api_key', 'super-secret-postnl-key', secret: true);
    $settings->set('postnl', 'customer_code', 'DEVC');
    $settings->forget('postnl', 'customer_number');
    putenv('AGOVENA_EXT_POSTNL_CUSTOMER_NUMBER=12345678');
    $_ENV['AGOVENA_EXT_POSTNL_CUSTOMER_NUMBER'] = '12345678';

    try {
        expect(freshConfigurationStatus()->isConfigured('postnl'))->toBeTrue();
    } finally {
        putenv('AGOVENA_EXT_POSTNL_CUSTOMER_NUMBER');
        unset($_ENV['AGOVENA_EXT_POSTNL_CUSTOMER_NUMBER']);
    }
});

test('an extension without required settings counts as configured', function () {
    installAndEnableModule('domains');
    installAndEnableExtension('cloudflare-domain');

    $status = freshConfigurationStatus()->for('cloudflare-domain');

    expect($status?->configured())->toBeTrue()
        ->and($status?->missingKeys)->toBe([]);
});

test('disabled and unknown extensions are ignored', function () {
    app(ExtensionManager::class)->install('mollie');
    $service = freshConfigurationStatus();

    expect($service->for('mollie'))->toBeNull()
        ->and($service->for('does-not-exist'))->toBeNull()
        ->and($service->isConfigured('mollie'))->toBeFalse()
        ->and(array_keys($service->all()))->not->toContain('mollie');

    installAndEnableExtension('mollie');

    expect(array_keys(freshConfigurationStatus()->all()))->toContain('mollie');
});

test('a registered runtime provider resolves to the configuration of its extension', function () {
    installAndEnableModule('shipping');
    installAndEnableExtension('postnl');
    installAndEnableExtension('mollie');

    $service = freshConfigurationStatus();
    $carrier = app(ShippingCarrierRegistry::class)->get('postnl');
    $gateway = app(PaymentGatewayRegistry::class)->get('mollie');

    expect($carrier)->toBeInstanceOf(PostnlCarrier::class)
        ->and($service->forRuntime($carrier)?->extensionId)->toBe('postnl')
        ->and($service->forRuntime($gateway)?->extensionId)->toBe('mollie')
        ->and($service->forRuntime(new stdClass))->toBeNull();
});

test('statuses are cached for the rest of the request', function () {
    installAndEnableExtension('mollie');
    $service = freshConfigurationStatus();
    $service->for('mollie');

    DB::enableQueryLog();
    DB::flushQueryLog();
    $service->for('mollie');
    $service->isConfigured('mollie');

    expect(DB::getQueryLog())->toBe([]);
});
