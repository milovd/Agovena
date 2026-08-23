<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

use App\Enums\PackageKind;
use App\Enums\PackageSourceType;

final readonly class PackageSource
{
    public function __construct(
        public PackageKind $kind,
        public PackageSourceType $sourceType,
        public string $locator,
        public string $constraint = '*',
        public ?string $composerName = null,
        public ?string $subdirectory = null,
    ) {}
}
