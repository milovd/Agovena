<?php

declare(strict_types=1);

namespace App\Agovena\Payments\Contracts;

use App\Models\Payment;

/**
 * Optional payment provider capability. Gateways implement this when pending payments can be cancelled.
 */
interface CancelsPayments
{
    public function cancel(Payment $payment): Payment;
}
