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
            'admin.nav_groups.catalog',
            'admin.nav_groups.sales',
            'admin.nav_groups.customers',
            'admin.nav_groups.fulfillment',
            'admin.nav_groups.operations',
            'admin.nav_groups.appearance',
            'admin.nav_groups.system',
            // Legacy aliases kept so older Module registrations still sort sensibly.
            'admin.nav_groups.commerce',
            'admin.nav_groups.services',
            'admin.nav_groups.support',
            'admin.nav_groups.configuration',
            'admin.nav_groups.administration',
        ];
    }

    /**
     * @param  Collection<int, NavigationItem>  $items
     * @return Collection<int, AdminNavigationNode>
     */
    public static function nest(Collection $items): Collection
    {
        $ids = $items->pluck('id')->all();
        $childrenByParent = $items
            ->filter(static fn (NavigationItem $item): bool => is_string($item->parent) && in_array($item->parent, $ids, true))
            ->sortBy(static fn (NavigationItem $item): int => $item->sort)
            ->groupBy(static fn (NavigationItem $item): string => (string) $item->parent);

        return $items
            ->filter(static fn (NavigationItem $item): bool => $item->parent === null || ! in_array($item->parent, $ids, true))
            ->map(static function (NavigationItem $item) use ($childrenByParent): AdminNavigationNode {
                $children = $childrenByParent->get($item->id, collect())
                    ->values()
                    ->all();

                return new AdminNavigationNode($item, $children);
            })
            ->values();
    }

    /**
     * @param  Collection<int, NavigationItem>  $items
     * @return Collection<string, Collection<int, AdminNavigationNode>>
     */
    public static function groupedTree(Collection $items): Collection
    {
        $order = array_flip(self::groupOrder());

        return self::nest($items)->groupBy(
            static fn (AdminNavigationNode $node): string => $node->item->group,
        )->sortBy(function (Collection $nodes, string $group) use ($order): int {
            return $order[$group] ?? (1000 + $nodes->min(
                static fn (AdminNavigationNode $node): int => $node->item->sort,
            ));
        });
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
