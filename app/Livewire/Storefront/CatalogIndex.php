<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Catalog\ListStorefrontProducts;
use App\Agovena\Theme\ThemeManager;
use Livewire\Component;

final class CatalogIndex extends Component
{
    public function render(ListStorefrontProducts $list, ThemeManager $themes)
    {
        $theme = $themes->active();

        return view($theme->view('catalog.index'), [
            'products' => $list->handle(),
            'theme' => $theme,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => 'Shop',
            'theme' => $theme,
        ]);
    }
}
