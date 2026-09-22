<?php

declare(strict_types=1);

namespace App\Agovena\Recurring\Enums;

enum RenewalStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
