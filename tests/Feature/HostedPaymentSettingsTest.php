<?php

declare(strict_types=1);

use Agovena\Extensions\Paddle\PaddleApi;
use Agovena\Extensions\Tebex\TebexApi;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Livewire\Admin\Extensions\Index;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\Support\FakePaddleApi;
use Tests\Support\FakeTebexApi;

uses(CreatesStaff::class);

function enablePaddleForSettings(?FakePaddleApi $api = null): FakePaddleApi
{
    app(ExtensionManager::class)->discover();
    $api ??= new FakePaddleApi;
    app()->instance(PaddleApi::class, $api);
    installAndEnableExtension('paddle');

    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('paddle', 'api_key', 'test-paddle-api-key', secret: true);
    $settings->set('paddle', 'webhook_secret', 'test-paddle-webhook-secret', secret: true);
    $settings->set('paddle', 'sandbox', true);

    return $api;
}

function enableTebexForSettings(?FakeTebexApi $api = null): FakeTebexApi
{
    app(ExtensionManager::class)->discover();
    $api ??= new FakeTebexApi;
    app()->instance(TebexApi::class, $api);
    installAndEnableExtension('tebex');

    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('tebex', 'project_id', 'test-tebex-project');
    $settings->set('tebex', 'secret_key', 'test-tebex-secret-key', secret: true);
    $settings->set('tebex', 'webhook_secret', 'test-tebex-webhook-secret', secret: true);
    $settings->set('tebex', 'package_map', '{}');

    return $api;
}

test('paddle settings automatically checks the connection without method discovery', function () {
    $api = enablePaddleForSettings();
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->call('openSettings', 'paddle')
        ->assertSet('settingsMethodsLoaded', false)
        ->assertSet('settingsMethodOptions', [])
        ->assertSet('settingsConnectionState', 'success')
        ->assertSet('settingsConnectionMessage', __('admin.extensions.settings_connection_ok_without_methods'))
        ->assertDontSee('Refresh payment methods');

    expect($api->pingCalls)->toBe(1);
});

test('tebex settings automatically checks the connection without method discovery', function () {
    $api = enableTebexForSettings();
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->call('openSettings', 'tebex')
        ->assertSet('settingsMethodsLoaded', false)
        ->assertSet('settingsMethodOptions', [])
        ->assertSet('settingsConnectionState', 'success')
        ->assertSet('settingsConnectionMessage', __('admin.extensions.settings_connection_ok_without_methods'))
        ->assertDontSee('Refresh payment methods');

    expect($api->pingCalls)->toBe(1);
});

test('paddle settings reuses the cached health snapshot when reopened', function () {
    $api = enablePaddleForSettings();
    $staff = $this->createStaff();
    $component = Livewire::actingAs($staff)->test(Index::class)->call('openSettings', 'paddle');

    $component->call('closeSettings')->call('openSettings', 'paddle');

    expect($api->pingCalls)->toBe(1)
        ->and($component->get('settingsConnectionCached'))->toBeTrue();
});

test('tebex settings reuses the cached health snapshot when reopened', function () {
    $api = enableTebexForSettings();
    $staff = $this->createStaff();
    $component = Livewire::actingAs($staff)->test(Index::class)->call('openSettings', 'tebex');

    $component->call('closeSettings')->call('openSettings', 'tebex');

    expect($api->pingCalls)->toBe(1)
        ->and($component->get('settingsConnectionCached'))->toBeTrue();
});

test('paddle settings automatically checks after the final credential is entered', function () {
    app(ExtensionManager::class)->discover();
    $api = new FakePaddleApi;
    app()->instance(PaddleApi::class, $api);
    installAndEnableExtension('paddle');
    $settings = app(ExtensionSettingsRepository::class);
    $settings->forget('paddle', 'api_key');
    $settings->forget('paddle', 'webhook_secret');
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->call('openSettings', 'paddle')
        ->set('settingsForm.api_key', 'test-paddle-api-key')
        ->set('settingsForm.webhook_secret', 'test-paddle-webhook-secret')
        ->assertSet('settingsConnectionState', 'success')
        ->assertSet('settingsConnectionMessage', __('admin.extensions.settings_connection_ok_without_methods'));

    expect($api->pingCalls)->toBe(1)
        ->and($settings->isConfigured('paddle', 'api_key'))->toBeFalse();
});

test('tebex settings automatically checks after the final credential is entered', function () {
    app(ExtensionManager::class)->discover();
    $api = new FakeTebexApi;
    app()->instance(TebexApi::class, $api);
    installAndEnableExtension('tebex');
    $settings = app(ExtensionSettingsRepository::class);
    $settings->forget('tebex', 'project_id');
    $settings->forget('tebex', 'secret_key');
    $settings->forget('tebex', 'webhook_secret');
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->call('openSettings', 'tebex')
        ->set('settingsForm.project_id', 'test-tebex-project')
        ->set('settingsForm.webhook_secret', 'test-tebex-webhook-secret')
        ->set('settingsForm.secret_key', 'test-tebex-secret-key')
        ->assertSet('settingsConnectionState', 'success')
        ->assertSet('settingsConnectionMessage', __('admin.extensions.settings_connection_ok_without_methods'));

    expect($api->pingCalls)->toBe(1)
        ->and($settings->isConfigured('tebex', 'secret_key'))->toBeFalse();
});

test('paddle settings reports a failed connection without persisting entered credentials', function () {
    app(ExtensionManager::class)->discover();
    $api = new FakePaddleApi;
    $api->pingFails = true;
    app()->instance(PaddleApi::class, $api);
    installAndEnableExtension('paddle');
    $settings = app(ExtensionSettingsRepository::class);
    $settings->forget('paddle', 'api_key');
    $settings->forget('paddle', 'webhook_secret');
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->call('openSettings', 'paddle')
        ->set('settingsForm.api_key', 'test-paddle-api-key')
        ->set('settingsForm.webhook_secret', 'test-paddle-webhook-secret')
        ->assertSet('settingsConnectionState', 'error')
        ->assertSet('settingsMethodOptions', []);

    expect($api->pingCalls)->toBe(1)
        ->and($settings->isConfigured('paddle', 'api_key'))->toBeFalse();
});

test('tebex settings reports a failed connection without persisting entered credentials', function () {
    app(ExtensionManager::class)->discover();
    $api = new FakeTebexApi;
    $api->pingFails = true;
    app()->instance(TebexApi::class, $api);
    installAndEnableExtension('tebex');
    $settings = app(ExtensionSettingsRepository::class);
    $settings->forget('tebex', 'project_id');
    $settings->forget('tebex', 'secret_key');
    $settings->forget('tebex', 'webhook_secret');
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->call('openSettings', 'tebex')
        ->set('settingsForm.project_id', 'test-tebex-project')
        ->set('settingsForm.webhook_secret', 'test-tebex-webhook-secret')
        ->set('settingsForm.secret_key', 'test-tebex-secret-key')
        ->assertSet('settingsConnectionState', 'error')
        ->assertSet('settingsMethodOptions', []);

    expect($api->pingCalls)->toBe(1)
        ->and($settings->isConfigured('tebex', 'secret_key'))->toBeFalse();
});
