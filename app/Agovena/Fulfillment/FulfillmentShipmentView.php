<?php

declare(strict_types=1);

namespace App\Agovena\Fulfillment;

/**
 * Theme-safe shipment view DTO (no Module models leaked).
 */
final readonly class FulfillmentShipmentView
{
    /**
     * @param  list<array{label: string, quantity: int}>  $items
     */
    public function __construct(
        public string $status,
        public string $statusLabel,
        public ?string $carrierName,
        public ?string $trackingNumber,
        public ?string $trackingUrl,
        public ?string $shippedAt,
        public ?string $deliveredAt,
        public array $items = [],
        public ?string $shippingMethod = null,
    ) {}
}
