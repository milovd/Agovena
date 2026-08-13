<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

final readonly class ComposerInstallResult
{
    public function __construct(
        public string $packageName,
        public string $version,
        public string $path,
    ) {}
}
