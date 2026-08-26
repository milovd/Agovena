<?php

declare(strict_types=1);

namespace App\Agovena\Backups;

final readonly class BackupRunResult
{
    public function __construct(
        public bool $success,
        public ?string $path,
        public int $prunedCount = 0,
        public ?string $errorCode = null,
    ) {}
}
