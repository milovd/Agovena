<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

final readonly class PaymentFee
{
    /**
     * @param  array<string, int|string|bool>  $snapshot
     */
    public function __construct(
        public int $amount,
        public string $currency,
        public array $snapshot,
    ) {}
}
