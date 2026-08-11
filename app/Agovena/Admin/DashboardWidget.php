<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

final readonly class DashboardWidget
{
    public function __construct(
        public string $id,
        public string $label,
        public string $view,
        public ?string $permission = null,
        public int $sort = 0,
        public string $group = 'overview',
    ) {}
}
