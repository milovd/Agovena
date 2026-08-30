<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

final readonly class ResolvedPackageOrigin
{
    public function __construct(
        public string $path,
        public ?string $cleanupPath = null,
    ) {}
}
