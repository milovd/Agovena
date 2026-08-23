<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

use Composer\Autoload\ClassLoader;

final class PackageAutoload
{
    /**
     * @param  array<string, string>  $psr4  prefix => relative directory
     */
    public function register(string $packagePath, array $psr4): void
    {
        $loader = $this->classLoader();
        if ($loader === null) {
            return;
        }

        $root = rtrim($packagePath, '/\\').DIRECTORY_SEPARATOR;
        foreach ($psr4 as $prefix => $relative) {
            if (! str_ends_with($prefix, '\\') || str_contains($relative, '..')) {
                continue;
            }

            $dir = $root.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relative, '/\\'));
            if (! is_dir($dir)) {
                continue;
            }

            $loader->addPsr4($prefix, $dir);
        }
    }

    private function classLoader(): ?ClassLoader
    {
        foreach (spl_autoload_functions() as $function) {
            if (is_array($function) && $function[0] instanceof ClassLoader) {
                return $function[0];
            }
        }

        return null;
    }
}
