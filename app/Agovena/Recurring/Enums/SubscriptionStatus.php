<?php

declare(strict_types=1);

namespace App\Agovena\Recurring\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Ended = 'ended';
}
