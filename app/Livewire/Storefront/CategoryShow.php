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
            ->firstOrFail();

        $products = $list->handle($category->id);

        $query = trim($this->search);
        if ($query !== '') {
            $products = $products->filter(fn ($p) => str_contains(
                mb_strtolower($p->name),
                mb_strtolower($query),
            ))->values();
        }

        $products = match ($this->sort) {
            'price_asc' => $products->sortBy('price_amount')->values(),
            'price_desc' => $products->sortByDesc('price_amount')->values(),
            default => $products->sortBy('name')->values(),
        };

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
