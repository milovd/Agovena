<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Agovena\Notifications\RendersNotificationMail;
use App\Models\Invoice;
use App\Support\MoneyFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class InvoiceIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const KEY = 'invoice_issued';

    public function __construct(private readonly Invoice $invoice)
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
            'name' => $this->invoice->customer_name,
            'number' => $this->invoice->number,
            'total' => MoneyFormatter::format($this->invoice->total_amount, $this->invoice->currency),
            'action_url' => route('customer.invoices.show', $this->invoice),
            'action_label' => __('notifications.invoice_issued.action'),
        ]);
    }
}
