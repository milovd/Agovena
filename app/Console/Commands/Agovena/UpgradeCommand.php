<?php

declare(strict_types=1);

namespace App\Console\Commands\Agovena;

use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Installation\ApplicationSchemaStatus;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Packages\MonorepoRemoteCatalog;
use App\Agovena\Packages\PackageCompatibility;
use App\Agovena\Packages\PackageInstaller;
use App\Enums\PackageKind;
use App\Enums\PackageSourceType;
use App\Models\AgovenaPackage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('agovena:upgrade {--without-package-updates : Do not update installed Modules and Extensions}')]
#[Description('Apply pending Agovena database migrations and update installed packages without destroying existing data')]
final class UpgradeCommand extends Command
{
    public function handle(
        ApplicationSchemaStatus $schema,
        ModuleManager $modules,
        ExtensionManager $extensions,
        PackageInstaller $packages,
        MonorepoRemoteCatalog $monorepo,
        PackageCompatibility $compatibility,
    ): int {
        $packages->recover();
        $pending = $schema->pending();
        if ($pending !== []) {
            $this->info('Pending application migrations:');
            foreach ($pending as $migration) {
                $this->line('  - '.$migration);
            }
            $this->newLine();
        } else {
            $this->line('No pending Core migrations detected before upgrade.');
        }

        if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
            $this->error('Core migrations failed. Package migrations were not started.');

            return self::FAILURE;
        }

        $updatesFailed = $this->option('without-package-updates')
            ? false
            : ! $this->updatePackages($packages, $monorepo, $modules, $extensions);

        $modules->refresh();
        $extensions->refresh();
        $modules->migrateInstalled();
        $extensions->migrateInstalled();

        $schema->refresh();

        if (! $schema->isCurrent()) {
            $this->error('Pending migrations remain:');
            foreach ($schema->pending() as $migration) {
                $this->line('  - '.$migration);
            }
            $this->newLine();
            $this->error('Resolve these with php artisan migrate --force, then run agovena:upgrade again.');

            return self::FAILURE;
        }

        $blocked = $this->enabledPackagesThatCannotRun($modules, $extensions, $compatibility);
        if ($blocked !== []) {
            $this->error('These enabled packages cannot run on Agovena '.$compatibility->platformVersion().' and are not loaded until they are updated:');
            foreach ($blocked as $line) {
                $this->line('  - '.$line);
            }

            return self::FAILURE;
        }

        if ($updatesFailed) {
            return self::FAILURE;
        }

        $this->info('Application schema is current. Existing data was not rebuilt.');

        return self::SUCCESS;
    }

    /**
     * Update every installed package that has a newer version available or cannot run on this Core.
     * Each update is atomic: a failed update rolls back to the previously installed copy.
     */
    private function updatePackages(PackageInstaller $packages, MonorepoRemoteCatalog $monorepo, ModuleManager $modules, ExtensionManager $extensions): bool
    {
        $monorepo->syncAvailableVersions();

        $succeeded = true;
        $updatable = AgovenaPackage::query()
            ->whereNotIn('source_type', [PackageSourceType::Bundled, PackageSourceType::Zip])
            ->where('is_bundled', false)
            ->orderBy('kind')
            ->orderBy('agovena_id')
            ->get();

        foreach ($updatable as $package) {
            $manager = $package->kind === PackageKind::Module ? $modules : $extensions;
            $cannotRun = $manager->isInstalled($package->agovena_id)
                && ! $manager->status($package->agovena_id)['compatible'];
            if (! $packages->hasUpdate($package) && ! $cannotRun) {
                continue;
            }

            try {
                $updated = $packages->update($package->kind, $package->agovena_id);
                $this->line(sprintf('Updated %s %s to %s.', $package->kind->value, $package->agovena_id, $updated->installed_version));
            } catch (\Throwable $e) {
                $succeeded = false;
                $reason = $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage();
                $this->error(sprintf('Could not update %s %s: %s', $package->kind->value, $package->agovena_id, $reason));
            }
        }

        return $succeeded;
    }

    /**
     * @return list<string>
     */
    private function enabledPackagesThatCannotRun(ModuleManager $modules, ExtensionManager $extensions, PackageCompatibility $compatibility): array
    {
        $blocked = [];
        foreach ([$modules, $extensions] as $manager) {
            foreach ($manager->discover() as $manifest) {
                if (! $manager->isEnabled($manifest->id)) {
                    continue;
                }
                $problem = $compatibility->problem($manifest->id, $manifest->version, $manifest->agovena);
                if ($problem !== null) {
                    $blocked[] = $manifest->id.': '.$problem;
                }
            }
        }

        return $blocked;
    }
}
