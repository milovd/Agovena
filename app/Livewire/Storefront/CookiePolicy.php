<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Theme\ThemeManager;
use Livewire\Component;

final class CookiePolicy extends Component
{
    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();
        $config = $themes->config($theme);

        return view($theme->view('pages.cookie-policy'), [
            'theme' => $theme,
            'themeConfig' => $config,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('storefront.cookie_consent.policy'),
            'theme' => $theme,
            'themeConfig' => $config,
        ]);
    }
}
