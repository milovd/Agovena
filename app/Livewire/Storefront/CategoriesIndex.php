<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Catalog\ListStorefrontCategories;
use App\Agovena\Theme\ThemeManager;
use Livewire\Component;

final class CategoriesIndex extends Component
{
    public function render(ListStorefrontCategories $categories, ThemeManager $themes)
    {
        $theme = $themes->active();
        $config = $themes->config($theme);

        return view($theme->view('catalog.categories'), [
            'categories' => $categories->handle(onlyWithProducts: false, rootsOnly: true),
            'theme' => $theme,
            'themeConfig' => $config,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('storefront.categories.title'),
            'theme' => $theme,
            'themeConfig' => $config,
        ]);
    }
}
