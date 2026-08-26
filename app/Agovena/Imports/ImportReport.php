<?php

declare(strict_types=1);

namespace App\Agovena\Imports;

final readonly class ImportReport
{
    /**
     * @param  list<ImportCandidate>  $candidates
     * @param  array<int, string>  $rowErrors
     */
    public function __construct(
        public bool $dryRun,
        public int $read,
        public int $valid,
        public int $duplicates,
        public int $errors,
        public array $candidates,
        public array $rowErrors,
    ) {}
}
