<?php

declare(strict_types=1);

namespace App\Agovena\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * One merchant-facing status for the Admin order list, derived from the order status and
 * its payment status. Both stored statuses stay separate: the order lifecycle drives
 * cancelling, deleting and invoicing, the payment status drives refunds and providers.
 * This enum only presents and filters them together; it never writes either of them.
 */
enum OrderListStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case PaymentFailed = 'payment_failed';
    case PaymentExpired = 'payment_expired';
    case Paid = 'paid';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    public static function for(Order $order): self
    {
        $payment = $order->payment?->status;

        return match ($order->status) {
            OrderStatus::Cancelled => self::Cancelled,
            OrderStatus::Paid => match ($payment) {
                PaymentStatus::Refunded => self::Refunded,
                PaymentStatus::PartiallyRefunded => self::PartiallyRefunded,
                default => self::Paid,
            },
            OrderStatus::Pending => match ($payment) {
                PaymentStatus::Failed => self::PaymentFailed,
                PaymentStatus::Expired => self::PaymentExpired,
                default => self::AwaitingPayment,
            },
        };
    }

    /**
     * Restricts the query to orders that display this status; mirrors for() exactly.
     *
     * @template TModel of Order
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, self $status): Builder
    {
        $paymentIn = static fn (array $statuses) => static fn (Builder $payment) => $payment->whereIn('status', $statuses);

        return match ($status) {
            self::Cancelled => $query->where('status', OrderStatus::Cancelled),
            self::Refunded => $query->where('status', OrderStatus::Paid)
                ->whereHas('payment', $paymentIn([PaymentStatus::Refunded])),
            self::PartiallyRefunded => $query->where('status', OrderStatus::Paid)
                ->whereHas('payment', $paymentIn([PaymentStatus::PartiallyRefunded])),
            self::Paid => $query->where('status', OrderStatus::Paid)
                ->whereDoesntHave('payment', $paymentIn([PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded])),
            self::PaymentFailed => $query->where('status', OrderStatus::Pending)
                ->whereHas('payment', $paymentIn([PaymentStatus::Failed])),
            self::PaymentExpired => $query->where('status', OrderStatus::Pending)
                ->whereHas('payment', $paymentIn([PaymentStatus::Expired])),
            self::AwaitingPayment => $query->where('status', OrderStatus::Pending)
                ->whereDoesntHave('payment', $paymentIn([PaymentStatus::Failed, PaymentStatus::Expired])),
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::AwaitingPayment => 'warning',
            self::PaymentFailed, self::PaymentExpired => 'danger',
            self::PartiallyRefunded, self::Refunded => 'info',
            self::Cancelled => 'muted',
        };
    }
}
