<?php

declare(strict_types=1);

namespace App\Events;

use App\Agovena\Orders\Lines\OrderLineChanges;
use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Staff are about to change the lines of an unpaid order. Dispatched synchronously inside the
 * order transaction before any line is written: listeners release what they hold for removed or
 * changed lines, or refuse the change by throwing a ValidationException (keyed "lines").
 */
final class OrderItemsChanging
{
    use Dispatchable;

    public function __construct(
        public Order $order,
        public OrderLineChanges $changes,
    ) {}
}
