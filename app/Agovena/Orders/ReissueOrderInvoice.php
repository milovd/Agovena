<?php

declare(strict_types=1);

namespace App\Agovena\Orders;

use App\Agovena\Audit\AuditLogger;
use App\Agovena\Invoices\IssueInvoiceFromOrder;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the open invoice of an unpaid order after its customer or lines changed. A numbered
 * invoice is never rewritten: the old one becomes void (it stays in the number sequence) and a
 * new invoice is issued from the order as it is now. Without an open invoice nothing is issued.
 */
final class ReissueOrderInvoice
{
    public function __construct(
        private readonly UnpaidOrderEditGuard $guard,
        private readonly IssueInvoiceFromOrder $issue,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Order $order, User $staff, string $reason): ?Invoice
    {
        return DB::transaction(function () use ($order, $staff, $reason): ?Invoice {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            return $this->reissueLocked($locked, $staff, $reason);
        });
    }

    /**
     * For callers that already hold the order lock inside their own transaction.
     */
    public function reissueLocked(Order $locked, User $staff, string $reason): ?Invoice
    {
        $this->guard->assertEditable($locked);

        $open = Invoice::query()
            ->where('order_id', $locked->id)
            ->where('status', InvoiceStatus::Issued)
            ->lockForUpdate()
            ->get();

        if ($open->isEmpty()) {
            return null;
        }

        foreach ($open as $invoice) {
            $invoice->status = InvoiceStatus::Void;
            $invoice->save();
        }

        $replacement = $this->issue->issueFor($locked->fresh(['items', 'payment']) ?? $locked);

        $this->audit->log('invoice.reissued', $replacement, [
            'order_id' => $locked->id,
            'order_number' => $locked->number,
            'voided_invoice_numbers' => $open->pluck('number')->values()->all(),
            'invoice_number' => $replacement->number,
            'reason' => $reason,
            'staff_id' => $staff->id,
        ]);

        return $replacement;
    }
}
