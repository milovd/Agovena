<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

final class SubscriptionCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $number,
        private readonly bool $atPeriodEnd,
    ) {
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
            ->subject(__('notifications.subscription_cancelled.subject', ['number' => $this->number]))
            ->line(__(
                $this->atPeriodEnd
                    ? 'notifications.subscription_cancelled.at_period_end'
                    : 'notifications.subscription_cancelled.immediate',
                ['number' => $this->number],
            ))
            ->action(
                __('notifications.subscription_cancelled.action'),
                Route::has('customer.subscriptions')
                    ? route('customer.subscriptions')
                    : url('/account/subscriptions'),
            );
    }
}
