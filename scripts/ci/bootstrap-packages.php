<?php

declare(strict_types=1);

/**
 * Ensure smoke/e2e fixtures can install Modules and Extensions when Core ships without bundled packages.
 * Uses on-disk optional-packages when configured; otherwise installs from the configured monorepo.
 *
 * Usage: php scripts/ci/bootstrap-packages.php [smoke|e2e]
 */

use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Packages\PackageInstaller;
use App\Agovena\Packages\PackageSource;
use App\Enums\PackageKind;
use App\Enums\PackageSourceType;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$profile = $argv[1] ?? 'smoke';
$moduleKeys = $profile === 'e2e'
    ? ['inventory', 'shipping', 'digital', 'subscriptions', 'provisioning', 'events']
    : ['inventory'];
$extensionKeys = ['manual-payment'];

$installer = app(PackageInstaller::class);
$modules = app(ModuleManager::class);
$extensions = app(ExtensionManager::class);

foreach ($moduleKeys as $key) {
    bootstrapPackage(
        $installer,
        PackageKind::Module,
        $key,
        fn (string $id): bool => $modules->isInstalled($id),
        fn (string $id): bool => $modules->manifest($id) !== null,
        fn (string $id) => $modules->install($id),
    );
}

foreach ($extensionKeys as $key) {
    bootstrapPackage(
        $installer,
        PackageKind::Extension,
        $key,
        fn (string $id): bool => $extensions->isInstalled($id),
        fn (string $id): bool => $extensions->manifest($id) !== null,
        fn (string $id) => $extensions->install($id),
    );
}

fwrite(STDOUT, "Packages bootstrapped (profile={$profile}).\n");

/**
 * @param  callable(string): bool  $isInstalled
 * @param  callable(string): bool  $isDiscoverable
 * @param  callable(string): mixed  $installLifecycle
 */
function bootstrapPackage(
    PackageInstaller $installer,
    PackageKind $kind,
    string $key,
    callable $isInstalled,
    callable $isDiscoverable,
    callable $installLifecycle,
): void {
    if ($isInstalled($key)) {
        return;
    }

    if ($isDiscoverable($key)) {
        $installLifecycle($key);

        return;
    }

    $installer->install(new PackageSource(
        kind: $kind,
        sourceType: PackageSourceType::Monorepo,
        locator: '',
        constraint: (string) config('agovena.packages.monorepo.default_ref', 'main'),
        composerName: $key,
    ));
}
