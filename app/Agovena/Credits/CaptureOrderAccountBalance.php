<?php

declare(strict_types=1);

namespace App\Agovena\Credits;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Capture reserved account balance when an order becomes paid.
 */
final class CaptureOrderAccountBalance
{
    public function __construct(private readonly CustomerCreditLedger $ledger) {}

    public function handle(Order $order): void
    {
        $amount = (int) $order->credit_amount;
        if ($amount < 1 || $order->customer_id === null) {
            return;
        }

        DB::transaction(function () use ($order, $amount): void {
            $customer = Customer::query()->whereKey($order->customer_id)->lockForUpdate()->first();
            if ($customer === null) {
                return;
            }

            // Idempotent: already captured for this order.
            $already = $customer->creditEntries()
                ->where('reason', 'order_credit')
                ->where('reference_type', $order->getMorphClass())
                ->where('reference_id', $order->id)
                ->where('entry_type', 'debit')
                ->exists();

            if ($already) {
                return;
            }

            $this->ledger->capture($customer, $amount, $order);
        });
    }
}
