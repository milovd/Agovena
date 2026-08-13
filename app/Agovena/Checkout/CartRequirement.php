<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

enum CartRequirement: string
{
    case Billing = 'billing';
    case ShippingAddress = 'shipping_address';
    case ShippingMethod = 'shipping_method';
    case ProductConfiguration = 'product_configuration';
    case CustomProperties = 'custom_properties';
    case Payment = 'payment';
    case Review = 'review';
}
