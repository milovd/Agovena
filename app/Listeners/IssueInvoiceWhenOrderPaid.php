<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Agovena\Invoices\IssueInvoiceFromOrder;
use App\Agovena\Invoices\MarkInvoicePaid;
use App\Events\OrderPaid;
use App\Notifications\InvoiceIssuedNotification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Notification;

final class IssueInvoiceWhenOrderPaid implements ShouldQueueAfterCommit
{
    public int $tries = 5;

    public array $backoff = [10, 60, 300];

    public function __construct(
        private readonly IssueInvoiceFromOrder $issueInvoice,
        private readonly MarkInvoicePaid $markPaid,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $invoice = $this->issueInvoice->handle($event->order);
        $invoice = $this->markPaid->handle($invoice, $event->order);

        Notification::route('mail', $invoice->customer_email)
            ->notify(new InvoiceIssuedNotification($invoice));
    }
}
