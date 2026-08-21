<?php

declare(strict_types=1);

namespace App\Agovena\Theme;

enum ThemeSurface: string
{
    case Storefront = 'storefront';
    case Admin = 'admin';
}
