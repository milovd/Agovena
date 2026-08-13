<?php

declare(strict_types=1);

namespace App\Agovena\Payments\Contracts;

use App\Agovena\Payments\PaymentInitiation;
use App\Agovena\Payments\PaymentInitiationResult;

/**
 * Optional PaymentGateway capability for off-session charges using a stored provider mandate.
 * Core does not store card data. Provider customer/mandate identifiers stay in the Extension.
 */
interface ChargesRecurringPayments
{
    public function charge(PaymentInitiation $request): PaymentInitiationResult;
}
