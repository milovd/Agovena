<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

use App\Agovena\Modules\ModuleManager;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

final class AdminNavigation
{
    /**
     * Nav item ids owned by optional Modules when the item id differs from the module id.
     *
     * @var array<string, string>
     */
    private const NAV_MODULE_OWNERS = [
        'digital-assets' => 'downloads',
        'digital-downloads' => 'downloads',
        'digital-delivery-secrets' => 'digital-delivery',
        'digital-secrets' => 'digital-delivery',
        'events-checkin' => 'events',
        'event-tickets' => 'events',
        'provisioning' => 'provisioning',
    ];

    private const CORE_NAV_IDS = [
        'inventory-stocks',
        'shipping-methods',
        'shipping-returns',
        'shipping-fulfillment',
        'subscriptions',
        'plan-changes',
    ];

    /** @var array<string, string> */
    private const LEGACY_GROUPS = [
        'admin.nav_groups.appearance' => 'online_store',
        'admin.nav_groups.operations' => 'fulfillment',
        'admin.nav_groups.commerce' => 'catalog',
        'admin.nav_groups.services' => 'fulfillment',
        'admin.nav_groups.support' => 'customers',
        'admin.nav_groups.configuration' => 'system',
        'admin.nav_groups.administration' => 'system',
    ];

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
            'admin.nav_groups.online_store',
            'admin.nav_groups.integrations',
            'admin.nav_groups.monitoring',
            'admin.nav_groups.system',
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
            static fn (AdminNavigationNode $node): string => self::displayGroup($node->item),
        )->sortBy(function (Collection $nodes, string $group) use ($order): int {
            return $order[$group] ?? (1000 + $nodes->min(
                static fn (AdminNavigationNode $node): int => $node->item->sort,
            ));
        });
    }

    /**
     * Hide Module-owned navigation unless the owning Module is enabled and the href resolves.
     *
     * @param  Collection<int, NavigationItem>  $items
     * @return Collection<int, NavigationItem>
     */
    public static function filterVisible(Collection $items, ModuleManager $modules, ?Router $router = null): Collection
    {
        $router ??= app('router');

        return $items->filter(function (NavigationItem $item) use ($modules, $router): bool {
            $moduleId = self::moduleOwner($item, $modules);

            if ($moduleId !== null && ! $modules->isEnabled($moduleId)) {
                return false;
            }

            if (! is_string($item->href) || $item->href === '') {
                return false;
            }

            return self::hrefIsReachable($item->href, $router);
        })->values();
    }

    /**
     * @param  Collection<int, NavigationItem>  $items
     * @return Collection<int, NavigationItem>
     */
    public static function filterForStaff(Collection $items, ?User $staff): Collection
    {
        return $items->filter(static fn (NavigationItem $item): bool => ($item->permission === null || $staff?->can($item->permission))
            && ($item->hideWhenPermission === null || ! $staff?->can($item->hideWhenPermission))
        )->values();
    }

    /**
     * @param  Collection<int, NavigationItem>  $items
     * @return Collection<string, Collection<int, NavigationItem>>
     */
    public static function groupItems(Collection $items): Collection
    {
        $grouped = $items->groupBy(fn (NavigationItem $item): string => self::displayGroup($item));
        $order = array_flip(self::groupOrder());

        return $grouped->sortBy(function (Collection $groupItems, string $group) use ($order): int {
            return $order[$group] ?? (1000 + $groupItems->min(fn (NavigationItem $item): int => $item->sort));
        });
    }

    private static function displayGroup(NavigationItem $item): string
    {
        $legacyGroup = self::LEGACY_GROUPS[$item->group] ?? null;

        return $legacyGroup === null ? $item->group : 'admin.nav_groups.'.$legacyGroup;
    }

    /** @param  Collection<int, NavigationItem>|null  $visibleItems */
    public static function isActive(?string $href, ?Collection $visibleItems = null): bool
    {
        if ($href === null || $href === '' || $href === '#') {
            return false;
        }

        $path = trim((string) (parse_url($href, PHP_URL_PATH) ?? $href), '/');

        if ($path === 'admin') {
            return request()->is('admin');
        }

        if (! self::matches($path)) {
            return false;
        }

        // More specific visible links win; hidden tabs still belong to their visible section.
        foreach ($visibleItems ?? app(AdminRegistrar::class)->navigationItems() as $other) {
            $otherPath = trim((string) (parse_url((string) $other->href, PHP_URL_PATH) ?? ''), '/');
            if (strlen($otherPath) > strlen($path) && str_starts_with($otherPath, $path.'/') && self::matches($otherPath)) {
                return false;
            }
        }

        return true;
    }

    public static function isCurrentPage(?string $href): bool
    {
        if ($href === null || $href === '') {
            return false;
        }

        $path = trim((string) (parse_url($href, PHP_URL_PATH) ?? ''), '/');

        return $path !== '' && request()->is($path);
    }

    private static function matches(string $path): bool
    {
        return request()->is($path) || request()->is($path.'/*');
    }

    private static function moduleOwner(NavigationItem $item, ModuleManager $modules): ?string
    {
        if ($item->moduleId !== null) {
            return $item->moduleId;
        }

        if (in_array($item->id, self::CORE_NAV_IDS, true)) {
            return null;
        }

        if ($modules->manifest($item->id) !== null) {
            return $item->id;
        }

        return self::NAV_MODULE_OWNERS[$item->id] ?? null;
    }

    private static function hrefIsReachable(string $href, Router $router): bool
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return true;
        }

        try {
            $router->getRoutes()->match(Request::create($href, 'GET'));

            return true;
        } catch (RouteNotFoundException|MethodNotAllowedHttpException) {
            return false;
        }
    }
}
