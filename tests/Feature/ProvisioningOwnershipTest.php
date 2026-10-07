<?php

declare(strict_types=1);

use App\Agovena\Modules\ModuleManager;
use App\Agovena\Operations\CronStatistics;
use App\Agovena\Provisioning\Contracts\PollsProvisionedInstances;
use Illuminate\Support\Facades\File;
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

/*
 * Package-specific checks that remain in Core until their Module registers the
 * behaviour itself (each needs a package release; see CONTRIBUTING "Package versions").
 * Do not add to this list: give the Module an extension point instead.
 */
test('core does not branch on optional package ids or query their tables outside the known follow-ups', function () {
    $followUps = [
        'Agovena/Admin/DashboardMetrics.php',
        'Agovena/Admin/GettingStartedChecklist.php',
        'Agovena/Imports/ImportExecutor.php',
        'Agovena/Recurring/Http/Livewire/Customer/SubscriptionShow.php',
        'Agovena/Recurring/SubscriptionService.php',
        'Console/Commands/AgovenaVerifyProvidersCommand.php',
        // Seeds sample data for every first-party package by design.
        'Console/Commands/AgovenaSeedDemoCommand.php',
    ];
    $packageIds = 'provisioning|downloads|digital-delivery|domains|events|postnl|mollie|stripe|paypal|paddle|tebex';
    $packageTables = 'provisioning_servers|service_instances|digital_assets|digital_secret_items|postnl_shipments|domain_registrations|event_tickets';

    $offenders = [];
    foreach (File::allFiles(app_path()) as $file) {
        $relative = str_replace('\\', '/', $file->getRelativePathname());
        if ($file->getExtension() !== 'php' || in_array($relative, $followUps, true) || str_contains($relative, '/migrations/')) {
            continue;
        }

        if (preg_match("/isEnabled\\('({$packageIds})'\\)/", $file->getContents()) === 1) {
            $offenders[] = $relative.' checks whether an optional package is enabled by its ID';
        }
        if (preg_match("/(hasTable|table|exists)\\('({$packageTables})'/", $file->getContents()) === 1) {
            $offenders[] = $relative.' uses a table an optional package owns';
        }
    }

    expect($offenders)->toBe([]);
});
