<?php

declare(strict_types=1);

namespace App\Agovena\Fulfillment;

use App\Models\Order;

/**
 * Generic order fulfillment presentation for Themes / customer portal.
 * Modules (Shipping) may provide shipment rows; Core stays provider-agnostic.
 */
interface OrderFulfillmentPresenter
{
    /**
     * @return list<FulfillmentShipmentView>
     */
    public function forOrder(Order $order): array;
}
