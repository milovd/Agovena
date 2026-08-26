<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class BackupFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $errorCode,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Agovena database backup failed')
            ->line('The scheduled database backup failed.')
            ->line('Error code: '.mb_substr($this->errorCode, 0, 80));
    }
}
