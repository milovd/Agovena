<?php

declare(strict_types=1);

namespace App\Agovena\Backups;

final readonly class BackupVerificationResult
{
    /** @param list<string> $checked */
    /** @param list<string> $missing */
    public function __construct(
        public bool $valid,
        public array $checked,
        public array $missing,
    ) {}
}
