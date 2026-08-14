<?php

declare(strict_types=1);

namespace App\Agovena\Media;

use Illuminate\Support\Facades\Storage;

/**
 * Public-disk URLs only when the file is actually present.
 */
final class PublicMedia
{
    public static function url(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }

        return $disk->url($path);
    }
}
