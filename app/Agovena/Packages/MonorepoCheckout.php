<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

interface MonorepoCheckout
{
    /**
     * Resolve a package subdirectory inside a monorepo checkout at the given ref.
     */
    public function resolve(string $repositoryUrl, string $ref, string $subdirectory): string;
}
