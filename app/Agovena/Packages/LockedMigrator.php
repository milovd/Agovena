<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

use Closure;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Cache;

final class LockedMigrator extends Migrator
{
    public function run($paths = [], array $options = [])
    {
        return $this->withMigrationLock(fn () => parent::run($paths, $options));
    }

    public function rollback($paths = [], array $options = [])
    {
        return $this->withMigrationLock(fn () => parent::rollback($paths, $options));
    }

    private function withMigrationLock(Closure $callback): mixed
    {
        $lock = Cache::lock('agovena:database-migrations', max(60, (int) config('agovena.packages.migration_lock_seconds', 3600)));
        $lock->block(60);

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
