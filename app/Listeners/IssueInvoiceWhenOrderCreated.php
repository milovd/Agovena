<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Agovena\Invoices\IssueInvoiceFromOrder;
use App\Events\OrderCreated;

final class IssueInvoiceWhenOrderCreated
{
    public function __construct(
        private readonly IssueInvoiceFromOrder $issueInvoice,
    ) {}

    public function handle(OrderCreated $event): void
    {
        $this->issueInvoice->handle($event->order);
    }
}
