<?php

declare(strict_types=1);

namespace App\Agovena\Shipping;

use App\Agovena\Shipping\Contracts\TracksShipments;
use Illuminate\Validation\ValidationException;

final class SyncCarrierTracking
{
    public function __construct(
        private readonly ShippingCarrierRegistry $carriers,
    ) {}

    /**
     * @return array{status: string, tracking_number: string|null, tracking_url: string|null}
     */
    public function handle(string $carrierId, string $externalId): array
    {
        $carrier = $this->carriers->get($carrierId);
        if (! $carrier instanceof TracksShipments) {
            throw ValidationException::withMessages([
                'shipping' => __('storefront.errors.shipping_method_unavailable'),
            ]);
        }

        return $carrier->tracking($externalId);
    }
}
