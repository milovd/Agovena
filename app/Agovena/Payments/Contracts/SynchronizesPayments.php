<?php

declare(strict_types=1);

namespace App\Agovena\Payments\Contracts;

use App\Models\Payment;

/**
 * Optional payment provider capability. Gateways implement this when they can poll provider status.
 */
interface SynchronizesPayments
{
    public function syncStatus(Payment $payment): Payment;
}
