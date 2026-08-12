<?php

declare(strict_types=1);

namespace App\Agovena\Customer;

final readonly class AccountOverviewCard
{
    /**
     * @param  array<string, mixed>|null  $routeParams
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $countOrValue,
        public ?string $routeName = null,
        public ?array $routeParams = null,
        public int $sort = 0,
        public ?string $hint = null,
    ) {}
}
