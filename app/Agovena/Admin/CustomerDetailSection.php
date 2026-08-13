<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

/**
 * Livewire component mounted on Admin customer detail by Modules.
 */
final readonly class CustomerDetailSection
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
