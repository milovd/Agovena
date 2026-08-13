<?php

declare(strict_types=1);

namespace App\Agovena\Shipping\Contracts;

/**
 * Optional carrier capability for tracking status sync.
 */
interface TracksShipments
{
    /**
     * @return array{status: string, tracking_number: string|null, tracking_url: string|null}
     */
    public function tracking(string $externalId): array;
}
