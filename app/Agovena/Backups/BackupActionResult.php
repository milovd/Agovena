<?php

declare(strict_types=1);

namespace App\Agovena\Backups;

final readonly class BackupActionResult
{
    public function __construct(
        public bool $success,
        public ?string $errorCode = null,
    ) {}
}
