<?php

declare(strict_types=1);

namespace App\Agovena\Orders;

use App\Agovena\Orders\Lines\OrderLineDraft;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Saves everything staff changed on the order page in one transaction: the customer, the contact,
 * due date and address snapshot, and the lines. When the customer or the lines changed, the open
 * invoice is replaced exactly once at the end.
 */
final class SaveOrderChanges
{
    public function __construct(
        private readonly ChangeOrderCustomer $customers,
        private readonly UpdateOrder $details,
        private readonly UpdateOrderLines $lines,
        private readonly ReissueOrderInvoice $reissue,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  Contact, due date and address attributes (see UpdateOrder)
     * @param  list<OrderLineDraft>|null  $lines  Null keeps the lines untouched
     */
    public function handle(
        Order $order,
        User $staff,
        ?Customer $customer = null,
        array $attributes = [],
        ?array $lines = null,
    ): Order {
        return DB::transaction(function () use ($order, $staff, $customer, $attributes, $lines): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $customerChanged = $customer !== null && $this->customers->applyLocked($locked, $customer, $staff);
            $linesChanged = $lines !== null && $this->lines->applyLocked($locked, $lines, $staff);
            $this->details->applyLocked($locked, $attributes);

            if ($customerChanged || $linesChanged) {
                $this->reissue->reissueLocked($locked, $staff, $linesChanged ? 'lines_changed' : 'customer_changed');
            }

            return $locked->fresh(['items', 'payment.attempts', 'invoices', 'creditNotes', 'refunds', 'customer'])
                ?? throw new RuntimeException('Order disappeared after saving.');
        });
    }
}
