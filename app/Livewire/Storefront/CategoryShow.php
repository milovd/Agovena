<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Catalog\ListStorefrontProducts;
use App\Agovena\Theme\ThemeManager;
use App\Models\Category;
use Livewire\Component;

final class CategoryShow extends Component
{
    public string $slug;

    public string $search = '';

    public string $sort = 'name';

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->search = (string) request()->query('q', '');
        $this->sort = (string) request()->query('sort', 'name');
    }

    public function render(ListStorefrontProducts $list, ThemeManager $themes)
    {
        $theme = $themes->active();
        $config = $themes->config($theme);
        $category = Category::query()
            ->where('slug', $this->slug)
            ->where('is_active', true)
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->firstOrFail();

        $categoryIds = collect([$category->id])
            ->merge($category->children->pluck('id'))
            ->map(fn ($id): int => (int) $id)
            ->all();

        $products = $list->handle(
            categoryIds: $categoryIds,
            search: trim($this->search) !== '' ? trim($this->search) : null,
            sort: $this->sort,
            limit: 96,
        );

        $query = trim($this->search);

        return view($theme->view('catalog.category'), [
            'category' => $category,
            'products' => $products,
            'theme' => $theme,
            'themeConfig' => $config,
            'sort' => $this->sort,
            'searchQuery' => $query,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => $category->name,
            'theme' => $theme,
            'themeConfig' => $config,
        ]);
    }
}
