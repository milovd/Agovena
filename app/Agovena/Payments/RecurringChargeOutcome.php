<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

enum RecurringChargeOutcome: string
{
    case Charged = 'charged';
    case Pending = 'pending';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
