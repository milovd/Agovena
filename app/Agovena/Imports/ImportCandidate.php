<?php

declare(strict_types=1);

namespace App\Agovena\Imports;

final readonly class ImportCandidate
{
    /**
     * @param  array<string, scalar|null>  $payload
     */
    public function __construct(
        public string $entity,
        public string $externalId,
        public array $payload,
        public int $line = 0,
    ) {}
}
