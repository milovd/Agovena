<?php

declare(strict_types=1);

namespace App\Events;

use App\Agovena\Orders\Lines\OrderLineChanges;
use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The lines of an unpaid order were changed by staff. Dispatched synchronously inside the order
 * transaction after the lines are written, so listeners can reserve or sync for added and changed
 * lines. A ValidationException (keyed "lines") rolls the whole edit back.
 */
final class OrderItemsChanged
{
    use Dispatchable;

    public function __construct(
        public Order $order,
        public OrderLineChanges $changes,
    ) {}
}
