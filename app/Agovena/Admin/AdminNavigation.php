<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

final class AdminNavigation
{
    public static function isActive(?string $href): bool
    {
        if ($href === null || $href === '' || $href === '#') {
            return false;
        }

        $path = trim((string) (parse_url($href, PHP_URL_PATH) ?? $href), '/');

        if ($path === 'admin') {
            return request()->is('admin');
        }

        return request()->is($path) || request()->is($path.'/*');
    }
}
