<?php

declare(strict_types=1);

namespace App\Agovena\Shipping\Contracts;

use App\Agovena\Shipping\CarrierShipmentResult;
use App\Models\Order;

/**
 * Optional carrier capability for creating a shipment and returning label/tracking data.
 */
interface CreatesCarrierShipments
{
    public function createShipment(Order $order, string $serviceCode): CarrierShipmentResult;

    public function cancelShipment(string $externalId): void;
}
