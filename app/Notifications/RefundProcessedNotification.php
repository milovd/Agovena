<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Agovena\Notifications\RendersNotificationMail;
use App\Models\Refund;
use App\Support\MoneyFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class RefundProcessedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const KEY = 'refund_processed';

    public function __construct(private readonly Refund $refund)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return app(RendersNotificationMail::class)->isEnabled(self::KEY, $notifiable) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->refund->order()->firstOrFail();

        return app(RendersNotificationMail::class)->mail(self::KEY, [
            'name' => $order->customer_name,
            'number' => $order->number,
            'total' => MoneyFormatter::format($this->refund->amount, $this->refund->currency),
            'action_url' => $order->customer_id
                ? route('customer.orders.show', $order)
                : url('/'),
            'action_label' => __('notifications.refund_processed.action'),
        ]);
    }
}
