<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning;

final readonly class ProvisionerPanelData
{
    /** @param list<array{label: string, value: string}> $fields */
    public function __construct(
        public string $title,
        public array $fields,
    ) {}
}
