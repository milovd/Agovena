<?php

declare(strict_types=1);

namespace App\Agovena\Money;

/**
 * @phpstan-type Source 'native'|'manual'|'converted'
 */
final readonly class ResolvedProductPrice
{
    public function __construct(
        public Money $money,
        public string $source,
    ) {}
}
