<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

final class OptionalPackagesPath
{
    public static function root(): ?string
    {
        $path = config('agovena.packages.optional_packages_path');
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);
        $candidates = [$path];
        if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            $candidates[] = base_path($path);
        }

        foreach ($candidates as $candidate) {
            $resolved = realpath($candidate);
            if ($resolved !== false && is_dir($resolved)) {
                return $resolved;
            }
        }

        return null;
    }

    public static function modulesRoot(): ?string
    {
        $root = self::root();
        if ($root === null) {
            return null;
        }

        $modules = $root.DIRECTORY_SEPARATOR.'modules';

        return is_dir($modules) ? $modules : null;
    }

    public static function extensionsRoot(): ?string
    {
        $root = self::root();
        if ($root === null) {
            return null;
        }

        $extensions = $root.DIRECTORY_SEPARATOR.'extensions';

        return is_dir($extensions) ? $extensions : null;
    }
}
