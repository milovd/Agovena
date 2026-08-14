<?php

declare(strict_types=1);

namespace App\Agovena\Media;

use Illuminate\Support\Facades\Storage;

/**
 * Public-disk URLs only when the file is actually present.
 *
 * URLs are origin-relative (`/storage/...`) so `<img src>` stays same-origin
 * when APP_URL (localhost vs 127.0.0.1) does not match the browser.
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

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return '/storage/'.implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
