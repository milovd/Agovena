<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning;

final readonly class ProvisionerAction
{
    public function __construct(
        public string $id,
        public string $label,
        public bool $dangerous = false,
    ) {}
}
