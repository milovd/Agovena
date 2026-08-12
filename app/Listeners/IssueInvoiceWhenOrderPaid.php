<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Agovena\Invoices\IssueInvoiceFromOrder;
use App\Events\OrderPaid;

final class IssueInvoiceWhenOrderPaid
{
    public function __construct(
        private readonly IssueInvoiceFromOrder $issueInvoice,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $this->issueInvoice->handle($event->order);
    }
}
