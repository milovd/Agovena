<?php

declare(strict_types=1);

namespace App\Agovena\Content;

use App\Models\Menu;
use App\Models\MenuItem;

final class MenuResolver
{
    /**
     * @return list<array{label: string, url: string|null, children: list<array{label: string, url: string|null}>}>
     */
    public function handle(string $handle): array
    {
        $menu = Menu::query()
            ->where('handle', $handle)
            ->with(['items.children.page', 'items.children.category', 'items.page', 'items.category'])
            ->first();

        if ($menu === null) {
            return [];
        }

        return $menu->items->map(fn (MenuItem $item): array => $this->mapItem($item))->all();
    }

    /**
     * @return array{label: string, url: string|null, children: list<array{label: string, url: string|null}>}
     */
    private function mapItem(MenuItem $item): array
    {
        $children = $item->children->map(fn (MenuItem $child): array => [
            'label' => $child->label,
            'url' => $child->resolvedUrl(),
        ])->all();

        return [
            'label' => $item->label,
            'url' => $item->resolvedUrl(),
            'children' => $children,
        ];
    }
}
