<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Agovena\Notifications\RendersNotificationMail;
use App\Models\Payment;
use App\Support\MoneyFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PaymentRecordedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const KEY = 'payment_recorded';

    public function __construct(private readonly Payment $payment)
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
        $order = $this->payment->order()->firstOrFail();

        return app(RendersNotificationMail::class)->mail(self::KEY, [
            'name' => $order->customer_name,
            'number' => $order->number,
            'total' => MoneyFormatter::format($this->payment->amount, $this->payment->currency),
            'action_url' => route('customer.orders.show', $order),
            'action_label' => __('notifications.payment_recorded.action'),
        ]);
    }
}
