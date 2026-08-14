<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

final readonly class ShippingDestination
{
    public function __construct(
        public string $country,
        public string $postalCode = '',
        public string $city = '',
        public string $line1 = '',
    ) {}

    public function isComplete(): bool
    {
        return $this->country !== ''
            && $this->postalCode !== ''
            && $this->city !== ''
            && $this->line1 !== '';
    }
}
