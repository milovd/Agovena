<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

/**
 * Livewire component mounted on Admin order detail by Modules (generic hook).
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
    ) {}
}
