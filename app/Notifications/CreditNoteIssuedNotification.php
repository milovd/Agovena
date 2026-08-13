<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Agovena\Notifications\RendersNotificationMail;
use App\Models\CreditNote;
use App\Support\MoneyFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CreditNoteIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const KEY = 'credit_note_issued';

    public function __construct(private readonly CreditNote $creditNote)
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
        $url = $this->creditNote->customer_id
            ? route('customer.credit-notes.show', $this->creditNote)
            : route('customer.invoices.show', $this->creditNote->invoice);

        return app(RendersNotificationMail::class)->mail(self::KEY, [
            'name' => $this->creditNote->customer_name,
            'number' => $this->creditNote->number,
            'total' => MoneyFormatter::format($this->creditNote->total_amount, $this->creditNote->currency),
            'action_url' => $url,
            'action_label' => __('notifications.credit_note_issued.action'),
        ]);
    }
}
