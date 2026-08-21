<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Catalog\ListStorefrontCategories;
use App\Agovena\Catalog\ListStorefrontProducts;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Storefront\StorefrontPreferences;
use App\Agovena\Theme\ThemeManager;
use Livewire\Component;

final class CatalogIndex extends Component
{
    public string $search = '';

    public function mount(): void
    {
        $this->search = (string) request()->query('q', '');
    }

    public function render(
        ListStorefrontProducts $list,
        ListStorefrontCategories $categories,
        ThemeManager $themes,
        SettingsRepository $settings,
        StorefrontPreferences $preferences,
    ) {
        $theme = $themes->active();
        $config = $themes->config($theme);
        $query = trim($this->search);
        $products = $list->handle(
            search: $query !== '' ? $query : null,
            limit: $query !== '' ? 48 : 24,
            currency: $preferences->catalogCurrencyFilter(),
        );

        $siteName = (string) $settings->get('general', 'site_name', config('app.name', 'Shop'));

        return view($theme->view('catalog.index'), [
            'products' => $products,
            'categories' => $categories->handle(),
            'theme' => $theme,
            'themeConfig' => $config,
            'sections' => $config->sections(),
            'searchQuery' => $query,
            'siteName' => $siteName,
            'isSearch' => $query !== '',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => $query !== '' ? __('storefront.search.heading') : __('storefront.nav.home'),
            'theme' => $theme,
            'themeConfig' => $config,
        ]);
    }
}
