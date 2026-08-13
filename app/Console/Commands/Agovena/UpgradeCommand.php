<?php

declare(strict_types=1);

namespace App\Console\Commands\Agovena;

use App\Agovena\Installation\ApplicationSchemaStatus;
use App\Agovena\Modules\ModuleManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('agovena:upgrade')]
#[Description('Apply pending Agovena database migrations without destroying existing data')]
final class UpgradeCommand extends Command
{
    public function handle(ApplicationSchemaStatus $schema, ModuleManager $modules): int
    {
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

        $this->call('migrate', ['--force' => true]);
        $modules->migrateInstalled();

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
