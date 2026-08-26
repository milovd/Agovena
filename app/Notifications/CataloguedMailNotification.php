<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Agovena\Notifications\RendersNotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CataloguedMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, scalar|null>  $vars
     */
    public function __construct(
        public readonly string $key,
        private readonly array $vars,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return app(RendersNotificationMail::class)->isEnabled($this->key, $notifiable) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return app(RendersNotificationMail::class)->mail($this->key, $this->vars);
    }
}
