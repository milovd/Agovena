<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Payment;
use App\Support\MoneyFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PaymentRecordedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Payment $payment)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->payment->order;

        return (new MailMessage)
            ->subject(__('notifications.payment_recorded.subject', ['number' => $order->number]))
            ->line(__('notifications.payment_recorded.line', ['number' => $order->number]))
            ->line(__('notifications.total', [
                'total' => MoneyFormatter::format($this->payment->amount, $this->payment->currency),
            ]))
            ->action(__('notifications.payment_recorded.action'), route('customer.orders.show', $order));
    }
}
