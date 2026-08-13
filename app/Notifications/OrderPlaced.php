<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Agovena\Notifications\RendersNotificationMail;
use App\Models\Order;
use App\Support\MoneyFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrderPlaced extends Notification implements ShouldQueue
{
    use Queueable;

    public const KEY = 'order_placed';

    public function __construct(private readonly Order $order)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return app(RendersNotificationMail::class)->isEnabled(self::KEY) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return app(RendersNotificationMail::class)->mail(self::KEY, [
            'name' => $this->order->customer_name,
            'number' => $this->order->number,
            'total' => MoneyFormatter::format($this->order->total_amount, $this->order->currency),
            'action_url' => route('storefront.order.confirmation', $this->order),
            'action_label' => __('notifications.order_placed.action'),
        ]);
    }
}
