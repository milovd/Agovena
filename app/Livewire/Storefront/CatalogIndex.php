<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Catalog\ListStorefrontProducts;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Theme\ThemeManager;
use App\Models\Product;
use Illuminate\Support\Collection;
use Livewire\Component;

final class CatalogIndex extends Component
{
    public string $search = '';

    public function mount(): void
    {
        $this->search = (string) request()->query('q', '');
    }

    public function render(ListStorefrontProducts $list, ThemeManager $themes, SettingsRepository $settings)
    {
        $theme = $themes->active();
        /** @var Collection<int, Product> $products */
        $products = $list->handle();

        $query = trim($this->search);
        if ($query !== '') {
            $products = $products
                ->filter(fn (Product $product): bool => str_contains(
                    mb_strtolower($product->name),
                    mb_strtolower($query),
                ))
                ->values();
        }

        $siteName = (string) $settings->get('general', 'site_name', config('app.name', 'Shop'));

        return view($theme->view('catalog.index'), [
            'products' => $products,
            'theme' => $theme,
            'searchQuery' => $query,
            'siteName' => $siteName,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => $query !== '' ? 'Search' : 'Shop',
            'theme' => $theme,
        ]);
    }
}
