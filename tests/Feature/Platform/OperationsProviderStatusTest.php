<?php

declare(strict_types=1);

use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Payments\HealthResult;
use App\Livewire\Admin\System\Updates;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

/**
 * Replace the extension's connection check with a local probe that records whether it ran.
 *
 * @param  array{probed: bool}  $probe
 */
function recordProviderProbe(string $extensionId, array &$probe): void
{
    $context = app(ExtensionManager::class)->context($extensionId);
    expect($context)->not->toBeNull();
    $context?->health(static function () use (&$probe): HealthResult {
        $probe['probed'] = true;

        return HealthResult::ok('Probe reached the provider');
    });
}

test('operations health marks an unconfigured provider and skips its network probe', function () {
    installAndEnableExtension('mollie');
    $probe = ['probed' => false];
    recordProviderProbe('mollie', $probe);

    Livewire::actingAs($this->createStaff())
        ->test(Updates::class)
        ->assertSee(__('admin.provider_status.not_configured'))
        ->assertSee(__('admin.provider_status.missing', ['fields' => __('mollie::messages.settings.api_key')]))
        ->assertSee(__('admin.provider_status.not_checked'))
        ->assertSee(route('admin.extensions.index'), false)
        ->assertDontSee('Probe reached the provider')
        ->assertDontSee(__('admin.updates.providers_fail'));

    expect($probe['probed'])->toBeFalse();
});

test('operations health probes a configured provider and shows its configured badge', function () {
    installAndEnableExtension('mollie');
    app(ExtensionSettingsRepository::class)->set('mollie', 'api_key', 'test_configuredkey1234567890', secret: true);
    $probe = ['probed' => false];
    recordProviderProbe('mollie', $probe);

    Livewire::actingAs($this->createStaff())
        ->test(Updates::class)
        ->assertSee(__('admin.provider_status.configured'))
        ->assertDontSee(__('admin.provider_status.not_configured'))
        ->assertSee(__('admin.updates.providers_ok'))
        ->assertSee('Probe reached the provider')
        ->assertDontSee('test_configuredkey1234567890');

    expect($probe['probed'])->toBeTrue();
});

test('provider verification reports an unconfigured provider without probing it', function () {
    installAndEnableExtension('mollie');
    $probe = ['probed' => false];
    recordProviderProbe('mollie', $probe);

    $this->artisan('agovena:verify-providers', ['extension' => 'mollie'])
        ->expectsOutputToContain('NOT CONFIGURED mollie')
        ->assertFailed();

    expect($probe['probed'])->toBeFalse();
});
