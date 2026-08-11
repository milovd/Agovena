<?php

declare(strict_types=1);

namespace App\Agovena\Theme;

final class ThemeManager
{
    private Theme $active;

    public function __construct()
    {
        $base = base_path('themes/default');

        $this->active = new Theme(
            id: 'default',
            name: 'Default',
            viewsPath: $base.DIRECTORY_SEPARATOR.'views',
            cssEntry: 'themes/default/resources/css/theme.css',
        );
    }

    public function active(): Theme
    {
        return $this->active;
    }
}
