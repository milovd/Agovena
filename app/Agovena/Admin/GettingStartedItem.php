<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

final readonly class GettingStartedItem
{
    public function __construct(
        public string $id,
        public string $labelKey,
        public string $href,
        public bool $done,
    ) {}
}
