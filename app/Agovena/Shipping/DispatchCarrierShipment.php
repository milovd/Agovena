<?php

declare(strict_types=1);

namespace App\Agovena\Shipping;

use App\Agovena\Shipping\Contracts\CreatesCarrierShipments;
use App\Agovena\Shipping\Contracts\ShippingCarrier;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

/**
 * Asks a ShippingCarrier to create a provider shipment. Modules must not call carrier APIs.
 */
final class DispatchCarrierShipment
{
    public function __construct(
        private readonly ShippingCarrierRegistry $carriers,
    ) {}

    public function handle(Order $order, string $carrierId, string $serviceCode): CarrierShipmentResult
    {
        $carrier = $this->requireCarrier($carrierId);
        if (! $carrier instanceof CreatesCarrierShipments) {
            throw ValidationException::withMessages([
                'shipping' => __('storefront.errors.shipping_method_unavailable'),
            ]);
        }

        return $carrier->createShipment($order, $serviceCode);
    }

    public function cancel(string $carrierId, string $externalId): void
    {
        $carrier = $this->requireCarrier($carrierId);
        if (! $carrier instanceof CreatesCarrierShipments) {
            return;
        }

        $carrier->cancelShipment($externalId);
    }

    private function requireCarrier(string $carrierId): ShippingCarrier
    {
        $carrier = $this->carriers->get($carrierId);
        if ($carrier === null) {
            throw ValidationException::withMessages([
                'shipping' => __('storefront.errors.shipping_method_unavailable'),
            ]);
        }

        return $carrier;
    }
}
