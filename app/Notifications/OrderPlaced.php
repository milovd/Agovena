<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use App\Support\MoneyFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrderPlaced extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Order $order)
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
        return (new MailMessage)
            ->subject(__('notifications.order_placed.subject', ['number' => $this->order->number]))
            ->greeting(__('notifications.greeting', ['name' => $this->order->customer_name]))
            ->line(__('notifications.order_placed.line', ['number' => $this->order->number]))
            ->line(__('notifications.total', [
                'total' => MoneyFormatter::format($this->order->total_amount, $this->order->currency),
            ]))
            ->action(
                __('notifications.order_placed.action'),
                route('storefront.order.confirmation', $this->order),
            );
    }
}
