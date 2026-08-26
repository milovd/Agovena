<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Agovena\Notifications\RendersNotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

final class SubscriptionCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const KEY = 'subscription_cancelled';

    public function __construct(
        private readonly string $number,
        private readonly bool $atPeriodEnd,
        private readonly string $customerName = '',
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return app(RendersNotificationMail::class)->isEnabled(self::KEY, $notifiable) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $detail = (string) __(
            $this->atPeriodEnd
                ? 'notifications.subscription_cancelled.at_period_end'
                : 'notifications.subscription_cancelled.immediate',
            ['number' => $this->number],
        );

        return app(RendersNotificationMail::class)->mail(self::KEY, [
            'name' => $this->customerName,
            'number' => $this->number,
            'detail' => $detail,
            'action_url' => Route::has('customer.subscriptions')
                ? route('customer.subscriptions')
                : url('/account/subscriptions'),
            'action_label' => __('notifications.subscription_cancelled.action'),
        ]);
    }
}
