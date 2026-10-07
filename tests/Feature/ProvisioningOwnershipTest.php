<?php

declare(strict_types=1);

use App\Agovena\Modules\ModuleManager;
use App\Agovena\Operations\CronStatistics;
use App\Agovena\Provisioning\Contracts\PollsProvisionedInstances;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

/*
 * Core asks whether provisioning can run through the contract the Provisioning
 * Module binds (PollsProvisionedInstances), not by the Module's package ID.
 */

function syncProvisioningIsActive(): bool
{
    // The schedule itself is only registered once per test process, so ask the rule directly.
    return (fn (): bool => $this->taskIsActive('sync-provisioning'))->call(app(CronStatistics::class));
}

test('provisioning sync is inactive without the provisioning module', function () {
    expect(app()->bound(PollsProvisionedInstances::class))->toBeFalse()
        ->and(syncProvisioningIsActive())->toBeFalse();

    $this->artisan('agovena:sync-provisioning')
        ->expectsOutputToContain('Provisioning module is not enabled.')
        ->assertSuccessful();
});

test('provisioning sync stays inactive while the provisioning module is installed but not enabled', function () {
    app(ModuleManager::class)->install('provisioning');

    expect(app()->bound(PollsProvisionedInstances::class))->toBeFalse()
        ->and(syncProvisioningIsActive())->toBeFalse();

    $this->artisan('agovena:sync-provisioning')
        ->expectsOutputToContain('Provisioning module is not enabled.')
        ->assertSuccessful();
});

test('provisioning sync runs while the provisioning module is enabled', function () {
    installAndEnableModules(['provisioning']);

    expect(syncProvisioningIsActive())->toBeTrue();

    $this->artisan('agovena:sync-provisioning')
        ->expectsOutputToContain('Synced 0 provisioning instance(s).')
        ->assertSuccessful();
});

test('the product form loads without provisioning servers while no provisioner configures servers', function () {
    installAndEnableModules(['provisioning']);

    $this->actingAs($this->createStaff())
        ->get(route('admin.products.create'))
        ->assertOk()
        ->assertDontSee('wire:model="provisioningServerId"', false);
});
