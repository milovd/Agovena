<?php

declare(strict_types=1);

namespace App\Agovena\Checkout\AddressAutocomplete;

final readonly class ResolvedAddress
{
    public function __construct(
        public string $line1,
        public ?string $line2,
        public string $city,
        public ?string $region,
        public string $postalCode,
        public string $country,
    ) {}
}
