<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

/**
 * Livewire component mounted on Admin order detail by Modules (generic hook).
 *
 * When a permission is set, the section is only mounted for users who hold it, so a
 * section never turns the whole order page into a 403.
 */
final readonly class OrderDetailSection
{
    /**
     * @param  class-string  $component
     */
    public function __construct(
        public string $id,
        public string $component,
        public int $sort = 100,
        public ?string $permission = null,
    ) {}
}
