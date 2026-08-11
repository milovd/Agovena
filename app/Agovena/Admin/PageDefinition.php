<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

final class PageDefinition
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $livewireComponent,
        public readonly ?string $permission = null,
    ) {}
}
