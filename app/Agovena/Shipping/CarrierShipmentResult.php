<?php

declare(strict_types=1);

namespace App\Agovena\Shipping;

final readonly class CarrierShipmentResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $externalId,
        public ?string $trackingNumber = null,
        public ?string $trackingUrl = null,
        public ?string $labelUrl = null,
        public ?string $labelPath = null,
        public string $status = 'processing',
        public array $metadata = [],
    ) {}
}
