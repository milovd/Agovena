<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Invoices\AssertInvoiceCanBePaid;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Events\PaymentRecorded;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Settle an order whose remaining gateway amount is zero (fully covered by account balance).
 */
final class CompleteAccountBalancePayment
{
    public function __construct(
        private readonly AssertInvoiceCanBePaid $assertInvoiceCanBePaid,
    ) {}

    public function handle(Order $order): Payment
    {
        return DB::transaction(function () use ($order): Payment {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            /** @var Payment $payment */
            $payment = Payment::query()->where('order_id', $locked->id)->lockForUpdate()->firstOrFail();

            if (in_array($payment->status, [PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded], true)) {
                throw ValidationException::withMessages([
                    'payment' => __('storefront.errors.payment_unavailable'),
                ]);
            }

            $locked->loadMissing('invoice');
            $this->assertInvoiceCanBePaid->handle($locked);

            if ($payment->status === PaymentStatus::Paid) {
                return $payment;
            }

            if ((int) $payment->amount > 0) {
                throw ValidationException::withMessages([
                    'payment' => __('storefront.errors.payment_unavailable'),
                ]);
            }

            $payment->method = 'account_balance';
            $payment->status = PaymentStatus::Paid;
            $payment->paid_at = now();
            $payment->reference = $payment->reference ?: 'account-balance';
            $payment->save();

            $locked->status = OrderStatus::Paid;
            $locked->save();

            event(new PaymentRecorded($payment->fresh() ?? $payment));
            event(new OrderPaid($locked->fresh(['items', 'payment']) ?? $locked));

            return $payment->fresh() ?? $payment;
        });
    }
}
