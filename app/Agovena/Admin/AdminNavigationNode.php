<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

use Illuminate\Support\Collection;

final class AdminNavigationNode
{
    /**
     * @param  list<NavigationItem>  $children
     */
    public function __construct(
        public readonly NavigationItem $item,
        public readonly array $children = [],
    ) {}

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /** @param  Collection<int, NavigationItem>|null  $visibleItems */
    public function isActive(?Collection $visibleItems = null): bool
    {
        if (AdminNavigation::isActive($this->item->href, $visibleItems)) {
            return true;
        }

        return $this->childIsActive($visibleItems);
    }

    /** @param  Collection<int, NavigationItem>|null  $visibleItems */
    public function childIsActive(?Collection $visibleItems = null): bool
    {
        foreach ($this->children as $child) {
            if (AdminNavigation::isActive($child->href, $visibleItems)) {
                return true;
            }
        }

        return false;
    }
}
