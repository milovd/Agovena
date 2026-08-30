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
 * Development-only instant payment completion. Never a production gateway.
 */
final class CompleteDevelopmentPayment
{
    public function __construct(
        private readonly AssertInvoiceCanBePaid $assertInvoiceCanBePaid,
    ) {}

    public function handle(Order $order): Payment
    {
        if (! (bool) config('agovena.payments.allow_development_instant_pay')) {
            throw ValidationException::withMessages([
                'payment' => 'Development instant payment is not enabled.',
            ]);
        }

        if (app()->environment('production')) {
            throw ValidationException::withMessages([
                'payment' => 'Development instant payment is unavailable in production.',
            ]);
        }

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

            if ($payment->method !== 'development') {
                throw ValidationException::withMessages([
                    'payment' => 'This order is not using the development payment method.',
                ]);
            }

            $payment->status = PaymentStatus::Paid;
            $payment->paid_at = now();
            $payment->reference = $payment->reference ?: 'dev-instant';
            $payment->save();

            $locked->status = OrderStatus::Paid;
            $locked->save();

            event(new PaymentRecorded($payment->fresh()));
            event(new OrderPaid($locked->fresh(['items', 'payment'])));

            return $payment->fresh();
        });
    }
}
