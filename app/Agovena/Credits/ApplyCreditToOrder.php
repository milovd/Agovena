<?php

declare(strict_types=1);

namespace App\Agovena\Credits;

use App\Models\Customer;
use App\Models\Order;

final class ApplyCreditToOrder
{
    public function __construct(private readonly CustomerCreditLedger $ledger) {}

    public function handle(Order $order, Customer $customer, int $maximumAmount): int
    {
        if ($maximumAmount < 1 || $order->total_amount < 1) {
            return 0;
        }

        $amount = min(
            $maximumAmount,
            $order->total_amount,
            $this->ledger->available($customer, $order->currency),
        );

        if ($amount < 1) {
            return 0;
        }

        $this->ledger->reserve($customer, $amount, $order);

        return $amount;
    }
}
