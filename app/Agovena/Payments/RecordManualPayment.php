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
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordManualPayment
{
    public function __construct(
        private readonly AssertInvoiceCanBePaid $assertInvoiceCanBePaid,
    ) {}

    /**
     * Mark a pending manual payment as received. Idempotent: already-paid is a no-op success.
     */
    public function handle(Order $order, User $staff, ?string $reference = null): Payment
    {
        if (! $staff->can('payments.record')) {
            abort(403);
        }

        return DB::transaction(function () use ($order, $reference): Payment {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing('invoice');
            $this->assertInvoiceCanBePaid->handle($locked);
            /** @var Payment $payment */
            $payment = Payment::query()->where('order_id', $locked->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PaymentStatus::Paid) {
                return $payment;
            }

            if ($payment->status === PaymentStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'payment' => 'This payment was cancelled and cannot be recorded.',
                ]);
            }

            $payment->status = PaymentStatus::Paid;
            $payment->paid_at = now();
            if (filled($reference)) {
                $payment->reference = $reference;
            }
            $payment->save();

            $locked->status = OrderStatus::Paid;
            $locked->save();

            event(new PaymentRecorded($payment->fresh()));
            event(new OrderPaid($locked->fresh(['items', 'payment'])));

            return $payment->fresh();
        });
    }
}
