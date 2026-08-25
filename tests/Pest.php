<?php

use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Installation\InstallationState;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Payments\Gateways\ManualPaymentGateway;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Settings\SettingsRepository;
use Tests\MultiProcessTestCase;
use Tests\TestCase;
use Tests\UpgradeTestCase;

require_once __DIR__.'/Support/OptionalPackages.php';

/**
 * @param  list<string>  $ids
 */
function installAndEnableModules(array $ids): void
{
    $modules = app(ModuleManager::class);
    foreach ($ids as $id) {
        if (! $modules->isInstalled($id)) {
            $modules->install($id);
        }
        $modules->enable($id);
    }
}

function installAndEnableModule(string $id): void
{
    installAndEnableModules([$id]);
}

function installAndEnableExtension(string $id): ExtensionManager
{
    $extensions = app(ExtensionManager::class);
    if (! $extensions->isInstalled($id)) {
        $extensions->install($id);
    }
    $extensions->enable($id);

    return $extensions;
}

/**
 * Feature tests historically place unpaid orders then call RecordManualPayment.
 * Register the core ManualPaymentGateway in the test registry only - it is not a
 * storefront product and is not offered when real payment extensions are enabled.
 */
function registerTestPendingPaymentGateway(): void
{
    $registry = app(PaymentGatewayRegistry::class);
    if ($registry->all() !== []) {
        return;
    }

    $registry->register(app(ManualPaymentGateway::class));
}

pest()->extend(TestCase::class)->in('Feature', 'Unit', 'Concurrency', 'Performance');
pest()->extend(MultiProcessTestCase::class)->in('MultiProcess');
pest()->extend(UpgradeTestCase::class)->in('Upgrade');

pest()->beforeEach(function (): void {
    if (! $this->app->runningUnitTests()) {
        return;
    }

    // Feature tests assume a completed install unless reset in installer-focused suites.
    $state = app(InstallationState::class);
    if ($state->notInstalled()) {
        $state->markInstalled();
    }

    // Production defaults automatic VAT on; most suite fixtures expect untaxed amounts
    // unless a tax-focused test enables the setting explicitly.
    app(SettingsRepository::class)->set('store', 'automatic_tax_rates', false);
})->in('Feature', 'Concurrency', 'Performance', 'MultiProcess');

pest()->beforeEach(function (): void {
    registerTestPendingPaymentGateway();
})->in('Feature', 'Concurrency', 'MultiProcess', 'Upgrade', 'Performance');

pest()->beforeEach(function (): void {
    if (config('database.default') !== 'mysql') {
        test()->markTestSkipped('MariaDB concurrency suite');
    }
})->in('Concurrency', 'MultiProcess');
