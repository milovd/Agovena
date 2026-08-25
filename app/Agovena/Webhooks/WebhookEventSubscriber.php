<?php

declare(strict_types=1);

namespace App\Agovena\Webhooks;

use App\Events\CreditNoteIssued;
use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Events\OrderPaid;
use App\Events\PaymentRecorded;
use App\Events\RefundRecorded;
use App\Models\Order;
use Illuminate\Events\Dispatcher;

final class WebhookEventSubscriber
{
    public function __construct(private readonly WebhookEventPublisher $publisher) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(OrderCreated::class, [self::class, 'orderCreated']);
        $events->listen(OrderPaid::class, [self::class, 'orderPaid']);
        $events->listen(OrderCancelled::class, [self::class, 'orderCancelled']);
        $events->listen(PaymentRecorded::class, [self::class, 'paymentRecorded']);
        $events->listen(RefundRecorded::class, [self::class, 'refundRecorded']);
        $events->listen(CreditNoteIssued::class, [self::class, 'creditNoteIssued']);
    }

    public function orderCreated(OrderCreated $event): void
    {
        $this->publisher->publish('order.created', $this->order($event->order));
    }

    public function orderPaid(OrderPaid $event): void
    {
        $this->publisher->publish('order.paid', $this->order($event->order));
    }

    public function orderCancelled(OrderCancelled $event): void
    {
        $this->publisher->publish('order.cancelled', $this->order($event->order));
    }

    public function paymentRecorded(PaymentRecorded $event): void
    {
        $payment = $event->payment;
        $this->publisher->publish('payment.recorded', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'status' => $payment->status->value,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
        ]);
    }

    public function refundRecorded(RefundRecorded $event): void
    {
        $refund = $event->refund;
        $this->publisher->publish('refund.recorded', [
            'refund_id' => $refund->id,
            'order_id' => $refund->order_id,
            'amount' => $refund->amount,
            'currency' => $refund->currency,
            'status' => $refund->status->value,
        ]);
    }

    public function creditNoteIssued(CreditNoteIssued $event): void
    {
        $creditNote = $event->creditNote;
        $this->publisher->publish('credit_note.issued', [
            'credit_note_id' => $creditNote->id,
            'invoice_id' => $creditNote->invoice_id,
            'order_id' => $creditNote->order_id,
            'amount' => $creditNote->total_amount,
            'currency' => $creditNote->currency,
        ]);
    }

    /** @return array<string, mixed> */
    private function order(Order $order): array
    {
        $order->loadMissing('payment');

        return [
            'order_id' => $order->id,
            'order_number' => $order->number,
            'status' => $order->status->value,
            'payment_status' => $order->payment?->status?->value,
            'total' => $order->total_amount,
            'currency' => $order->currency,
        ];
    }
}
