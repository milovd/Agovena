<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

enum CheckoutStep: string
{
    case Details = 'details';
    case Delivery = 'delivery';
    case Configuration = 'configuration';
    case Payment = 'payment';
    case Review = 'review';

    public function labelKey(): string
    {
        return 'storefront.checkout.steps.'.$this->value;
    }
}
