<?php

declare(strict_types=1);

use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Models\ExtensionSetting;

it('does not let a sensitive environment override bypass encrypted settings', function (): void {
    putenv('AGOVENA_EXT_MOLLIE_API_KEY=env-fixture');

    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('mollie', 'api_key', '[REDACTED]', secret: true);

    expect($settings->get('mollie', 'api_key'))->toBe('[REDACTED]');

    putenv('AGOVENA_EXT_MOLLIE_API_KEY');
});

it('quarantines malformed encrypted settings until explicitly repaired', function (): void {
    $settings = app(ExtensionSettingsRepository::class);
    $row = ExtensionSetting::query()->create([
        'extension_id' => 'mollie',
        'key' => 'api_key',
        'value' => 'malformed-ciphertext',
        'is_secret' => true,
    ]);

    expect($settings->get('mollie', 'api_key', 'fallback'))->toBe('fallback')
        ->and($settings->isConfigured('mollie', 'api_key'))->toBeFalse()
        ->and($row->fresh()->is_corrupt)->toBeTrue();

    $settings->set('mollie', 'api_key', '[REDACTED]', secret: true);

    expect($settings->isConfigured('mollie', 'api_key'))->toBeTrue();
});
