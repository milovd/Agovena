<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

final class InMemoryAdminRegistrar implements AdminRegistrar
{
    /** @var list<NavigationItem> */
    private array $navigation = [];

    /** @var list<PageDefinition> */
    private array $pages = [];

    /** @var array<string, string> */
    private array $permissions = [];

    public function navigation(NavigationItem $item): void
    {
        $this->navigation[] = $item;
    }

    public function page(PageDefinition $page): void
    {
        $this->pages[] = $page;
    }

    public function permission(string $ability, string $label): void
    {
        $this->permissions[$ability] = $label;
    }

    /** @return list<NavigationItem> */
    public function navigationItems(): array
    {
        $items = $this->navigation;
        usort($items, static fn (NavigationItem $a, NavigationItem $b): int => $a->sort <=> $b->sort);

        return $items;
    }

    /** @return list<PageDefinition> */
    public function pages(): array
    {
        return $this->pages;
    }

    /** @return array<string, string> */
    public function permissions(): array
    {
        return $this->permissions;
    }
}
