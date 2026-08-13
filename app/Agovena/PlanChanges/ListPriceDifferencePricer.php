<?php

declare(strict_types=1);

namespace App\Agovena\PlanChanges;

use App\Models\Product;

/**
 * Charge for a plan change. Remaining-period proration is a future seam.
 */
final class ListPriceDifferencePricer
{
    public function chargeAmount(Product $from, Product $to): int
    {
        return max(0, $to->price_amount - $from->price_amount);
    }
}
