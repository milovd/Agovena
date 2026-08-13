<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Agovena\Notifications\RendersNotificationMail;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TicketRepliedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const KEY = 'ticket_replied';

    public function __construct(private readonly Ticket $ticket)
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
        $this->ticket->loadMissing('customer');
        $customer = $this->ticket->customer;

        return app(RendersNotificationMail::class)->mail(self::KEY, [
            'name' => $customer !== null ? (string) $customer->name : '',
            'number' => $this->ticket->number,
            'subject' => $this->ticket->subject,
            'action_url' => route('customer.tickets.show', $this->ticket),
            'action_label' => __('notifications.ticket_replied.action'),
        ]);
    }
}
