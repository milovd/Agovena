<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case Expired = 'expired';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
}
