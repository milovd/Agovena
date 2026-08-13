<?php

declare(strict_types=1);

namespace App\Agovena\Shipping;

final readonly class ShippingRateQuote
{
    public function __construct(
        public string $serviceCode,
        public string $serviceLabel,
        public int $amount,
        public string $currency,
        public ?int $transitDays = null,
    ) {}
}
