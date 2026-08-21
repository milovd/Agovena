<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

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

    public function isActive(): bool
    {
        if (AdminNavigation::isActive($this->item->href)) {
            return true;
        }

        return $this->childIsActive();
    }

    public function childIsActive(): bool
    {
        foreach ($this->children as $child) {
            if (AdminNavigation::isActive($child->href)) {
                return true;
            }
        }

        return false;
    }
}
