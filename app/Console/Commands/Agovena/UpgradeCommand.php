<?php

declare(strict_types=1);

namespace App\Console\Commands\Agovena;

use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Installation\ApplicationSchemaStatus;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Packages\PackageInstaller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('agovena:upgrade')]
#[Description('Apply pending Agovena database migrations without destroying existing data')]
final class UpgradeCommand extends Command
{
    public function handle(ApplicationSchemaStatus $schema, ModuleManager $modules, ExtensionManager $extensions, PackageInstaller $packages): int
    {
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

        $this->info('Application schema is current. Existing data was not rebuilt.');

        return self::SUCCESS;
    }
}
