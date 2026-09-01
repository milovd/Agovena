<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Models\Order;
use App\Models\Payment;

/**
 * Settle an order whose remaining gateway amount is zero (fully covered by account balance).
 */
final class CompleteAccountBalancePayment
{
    public function __construct(
        private readonly CompleteDirectPayment $completeDirectPayment,
    ) {}

    public function handle(Order $order): Payment
    {
        return $this->completeDirectPayment->handle(
            $order,
            'account_balance',
            reference: 'account-balance',
            requiredAmount: 0,
        );
    }
}
