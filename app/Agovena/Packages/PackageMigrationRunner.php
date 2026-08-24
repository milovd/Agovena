<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

use App\Agovena\Support\RecoversTestTransaction;
use Illuminate\Database\Migrations\Migrator;
use RuntimeException;

final class PackageMigrationRunner
{
    public function __construct(
        private readonly Migrator $migrator,
    ) {}

    public function run(string $packageId, string $packageRoot): void
    {
        $path = $packageRoot.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
        if (! is_dir($path)) {
            return;
        }

        $resolved = realpath($path);
        if ($resolved === false) {
            throw new RuntimeException("Package [{$packageId}] migrations path could not be resolved.");
        }

        try {
            $this->migrator->run([$resolved]);
        } finally {
            RecoversTestTransaction::afterDdl();
        }
    }
}
