<?php

declare(strict_types=1);

namespace App\Agovena\Installation;

final readonly class RequirementCheck
{
    public function __construct(
        public string $id,
        public string $label,
        public bool $passed,
        public bool $required = true,
        public ?string $detail = null,
        public ?string $technicalDetail = null,
    ) {}
}
