<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use App\Support\MoneyFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class InvoiceIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Invoice $invoice)
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
            ->subject(__('notifications.invoice_issued.subject', ['number' => $this->invoice->number]))
            ->line(__('notifications.invoice_issued.line', ['number' => $this->invoice->number]))
            ->line(__('notifications.total', [
                'total' => MoneyFormatter::format($this->invoice->total_amount, $this->invoice->currency),
            ]))
            ->action(__('notifications.invoice_issued.action'), route('customer.invoices.show', $this->invoice));
    }
}
