<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Theme\ThemeManager;
use App\Models\Page;
use Livewire\Component;

final class ContentPage extends Component
{
    public string $slug;

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();
        $config = $themes->config($theme);
        $page = Page::query()->published()->where('slug', $this->slug)->firstOrFail();

        return view($theme->view('pages.show'), [
            'page' => $page,
            'theme' => $theme,
            'themeConfig' => $config,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => $page->title,
            'theme' => $theme,
            'themeConfig' => $config,
        ]);
    }
}
