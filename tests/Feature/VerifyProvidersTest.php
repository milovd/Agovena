<?php

declare(strict_types=1);

use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;

test('provider verification runs health checks without creating remote resources', function () {
    $this->artisan('agovena:verify-providers')
        ->expectsOutputToContain('This only tests connectivity')
        ->assertSuccessful();
});

test('provider verification can target a single extension and refuse live mollie keys in sandbox mode', function () {
    app(ExtensionManager::class)->enable('mollie');
    app(ExtensionSettingsRepository::class)->set('mollie', 'api_key', 'live_abcdefghijklmnopqrstuvwxyz123456', secret: true);

    $this->artisan('agovena:verify-providers', ['extension' => 'mollie', '--sandbox' => true])
        ->expectsOutputToContain('Sandbox mode refuses Mollie live_')
        ->assertFailed();
});
