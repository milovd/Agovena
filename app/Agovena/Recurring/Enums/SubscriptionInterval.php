<?php

declare(strict_types=1);

namespace App\Agovena\Recurring\Enums;

enum SubscriptionInterval: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
}
