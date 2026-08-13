<?php

declare(strict_types=1);

namespace App\Agovena\Orders;

enum UnpaidOrderCancelSource: string
{
    case Customer = 'customer';
    case Staff = 'staff';
    case Scheduler = 'scheduler';
}
