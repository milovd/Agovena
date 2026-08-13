<?php

declare(strict_types=1);

namespace App\Agovena\Payments\Contracts;

use App\Agovena\Payments\CheckoutPaymentMethod;

/**
 * Optional PaymentGateway capability. Gateways implement this when they expose
 * more than one checkout method through a single Extension.
 */
interface OffersCheckoutMethods
{
    /**
     * @return list<CheckoutPaymentMethod>
     */
    public function checkoutMethods(): array;
}
