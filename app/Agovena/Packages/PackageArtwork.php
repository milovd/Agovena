<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

final class PackageArtwork
{
    public function resolve(string $root, mixed $reference, bool $themePreview = false): ?string
    {
        if (! is_string($reference)) {
            return null;
        }

        $pattern = $themePreview
            ? '~\A(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.png\z~D'
            : '~\Aresources/images/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.png\z~D';

        if (preg_match($pattern, $reference) !== 1) {
            return null;
        }

        $base = realpath($root);
        if ($base === false || ! is_dir($base)) {
            return null;
        }

        $cursor = $base;
        foreach (explode('/', $reference) as $part) {
            $cursor .= DIRECTORY_SEPARATOR.$part;
            if (is_link($cursor)) {
                return null;
            }
        }

        $path = realpath($cursor);
        if ($path === false || ! str_starts_with($path, $base.DIRECTORY_SEPARATOR) || ! is_file($path)) {
            return null;
        }

        $size = filesize($path);
        if ($size === false || $size === 0 || $size > 512 * 1024) {
            return null;
        }

        $image = @getimagesize($path);
        if ($image === false || $image[2] !== IMAGETYPE_PNG || $image[0] < 1 || $image[1] < 1 || $image[0] > 1024 || $image[1] > 1024) {
            return null;
        }

        return $path;
    }
}
