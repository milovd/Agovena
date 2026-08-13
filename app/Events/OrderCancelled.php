<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pending unpaid order cancelled (customer, staff, or scheduler).
 * Modules restock or cancel unshipped fulfillment; they must not treat this as a refund.
 */
final class OrderCancelled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Order $order) {}
}
