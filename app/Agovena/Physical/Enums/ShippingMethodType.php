<?php

declare(strict_types=1);

namespace App\Agovena\Physical\Enums;

enum ShippingMethodType: string
{
    case Free = 'free';
    case Flat = 'flat';
    case Price = 'price';
    case Weight = 'weight';
    case Zone = 'zone';
}
