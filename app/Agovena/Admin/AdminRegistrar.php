<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

interface AdminRegistrar
{
    public function navigation(NavigationItem $item): void;

    public function page(PageDefinition $page): void;

    public function permission(string $ability, string $label): void;
}
