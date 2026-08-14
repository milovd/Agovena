<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

enum CheckoutStep: string
{
    case Details = 'details';
    case Delivery = 'delivery';
    case Configuration = 'configuration';
    case Fulfillment = 'fulfillment';
    case Payment = 'payment';

    public function labelKey(): string
    {
        return 'storefront.checkout.steps.'.$this->value;
    }

    public function includesDelivery(): bool
    {
        return $this === self::Delivery || $this === self::Fulfillment;
    }

    public function includesConfiguration(): bool
    {
        return $this === self::Configuration || $this === self::Fulfillment;
    }
}
