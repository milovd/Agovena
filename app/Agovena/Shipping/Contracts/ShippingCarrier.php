<?php

declare(strict_types=1);

namespace App\Agovena\Shipping\Contracts;

/**
 * Future provider seam for shipping carrier Extensions.
 * Core and the Shipping Module must not hardcode carrier SDKs.
 */
interface ShippingCarrier
{
    public function id(): string;

    public function label(): string;
}
