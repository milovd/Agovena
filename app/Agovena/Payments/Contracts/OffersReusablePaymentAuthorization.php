<?php

declare(strict_types=1);

namespace App\Agovena\Payments\Contracts;

use App\Agovena\Payments\ReusablePaymentAuthorization;

/**
 * Optional PaymentGateway capability: does this customer have a reusable off-session authorization?
 * Provider mandate/customer identifiers stay in the Extension.
 */
interface OffersReusablePaymentAuthorization
{
    public function reusableAuthorization(?int $customerId, string $customerEmail): ReusablePaymentAuthorization;
}
