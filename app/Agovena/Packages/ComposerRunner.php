<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

interface ComposerRunner
{
    /**
     * Require a Composer package into the isolated packages Composer project.
     * Arguments must already be validated — implementations must not interpolate a shell string.
     */
    public function require(string $packageName, string $constraint, ?string $repositoryUrl = null): ComposerInstallResult;

    public function remove(string $packageName): void;

    public function latestVersion(string $packageName): ?string;
}
