<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

/**
 * Module-owned Livewire tab mounted on the Admin product edit screen.
 */
final readonly class ProductTab
{
    /** @param class-string $component */
    public function __construct(
        public string $id,
        public string $label,
        public string $component,
        public int $sort = 100,
        public ?string $permission = null,
    ) {}
}
