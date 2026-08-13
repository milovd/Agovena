<?php

declare(strict_types=1);

namespace App\Agovena\Shipping\Contracts;

use App\Agovena\Shipping\ShippingRateQuote;
use App\Models\Order;

/**
 * Optional carrier capability for retrieving service levels and rates.
 */
interface QuotesShippingRates
{
    /**
     * @return list<ShippingRateQuote>
     */
    public function quote(Order $order): array;
}
