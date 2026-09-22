<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

use App\Agovena\Money\Money;

/**
 * Generic checkout shipping quote. Core physical commerce provides real quotes;
 * provider-specific behavior stays in Extensions.
 */
final readonly class ShippingQuote
{
    public function __construct(
        public int $methodId,
        public string $label,
        public Money $amount,
        public ?string $carrierId = null,
        public ?string $serviceCode = null,
    ) {}

    public function key(): string
    {
        if ($this->carrierId !== null && $this->serviceCode !== null) {
            return 'carrier:'.$this->carrierId.':'.$this->serviceCode;
        }

        return 'method:'.$this->methodId;
    }

    public function isCarrierQuote(): bool
    {
        return $this->carrierId !== null && $this->serviceCode !== null;
    }
}
