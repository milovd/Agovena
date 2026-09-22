<?php

declare(strict_types=1);

namespace App\Agovena\Availability;

enum AvailabilityMode: string
{
    case Unlimited = 'unlimited';
    case Finite = 'finite';
    case ProviderCapacity = 'provider_capacity';
}
