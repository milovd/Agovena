<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OrderPaid
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Order $order) {}
}
