<?php

declare(strict_types=1);

namespace App\Agovena\Fulfillment;

use App\Models\Order;

final class NullOrderFulfillmentPresenter implements OrderFulfillmentPresenter
{
    public function forOrder(Order $order): array
    {
        return [];
    }
}
