<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning;

final readonly class ServiceInstanceInfo
{
    /** @param array<string, mixed> $meta */
    public function __construct(
        public int $id,
        public string $label,
        public string $status,
        public ?string $providerKey,
        public ?string $externalRef,
        public array $meta = [],
    ) {}
}
