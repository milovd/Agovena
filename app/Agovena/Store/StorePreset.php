<?php

declare(strict_types=1);

namespace App\Agovena\Store;

final readonly class StorePreset
{
    /**
     * @param  list<string>  $moduleIds
     */
    public function __construct(
        public string $id,
        public string $labelKey,
        public string $ledeKey,
        public array $moduleIds,
        public bool $isCustom = false,
    ) {}
}
