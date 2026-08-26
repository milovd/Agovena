<?php

declare(strict_types=1);

namespace App\Agovena\Backups;

final readonly class BackupRestoreVerificationResult
{
    public function __construct(
        public bool $valid,
        public ?string $errorCode = null,
    ) {}
}
