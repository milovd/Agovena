<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

use Illuminate\Support\Collection;

final class AdminNavigation
{
    /**
     * Canonical sidebar group order (translation keys).
     *
     * @return list<string>
     */
    public static function groupOrder(): array
    {
        return [
            'admin.nav_groups.overview',
            'admin.nav_groups.commerce',
            'admin.nav_groups.fulfillment',
            'admin.nav_groups.operations',
            'admin.nav_groups.system',
            // Legacy aliases kept so older Module registrations still sort sensibly.
            'admin.nav_groups.services',
            'admin.nav_groups.support',
            'admin.nav_groups.configuration',
            'admin.nav_groups.administration',
            'admin.nav_groups.appearance',
        ];
    }

    /**
     * @param  Collection<int, NavigationItem>  $items
     * @return Collection<string, Collection<int, NavigationItem>>
     */
    public static function groupItems(Collection $items): Collection
    {
        $grouped = $items->groupBy(fn (NavigationItem $item): string => $item->group);
        $order = array_flip(self::groupOrder());

        return $grouped->sortBy(function (Collection $groupItems, string $group) use ($order): int {
            return $order[$group] ?? (1000 + $groupItems->min(fn (NavigationItem $item): int => $item->sort));
        });
    }

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
