<?php

declare(strict_types=1);

namespace App\Agovena\Orders;

use App\Agovena\Audit\AuditLogger;
use App\Models\Customer;
use App\Models\DiscountRedemption;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Assigns an unpaid order to another customer. Paid orders are refused: moving them would also
 * move subscriptions, services and download rights, which is an ownership transfer.
 */
final class ChangeOrderCustomer
{
    public function __construct(
        private readonly UnpaidOrderEditGuard $guard,
        private readonly ReissueOrderInvoice $reissue,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Order $order, Customer $customer, User $staff): Order
    {
        return DB::transaction(function () use ($order, $customer, $staff): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($this->applyLocked($locked, $customer, $staff)) {
                $this->reissue->reissueLocked($locked, $staff, 'customer_changed');
            }

            return $this->fresh($locked);
        });
    }

    /**
     * For callers that hold the order lock and reissue the invoice themselves.
     *
     * @return bool Whether the customer changed
     */
    public function applyLocked(Order $locked, Customer $customer, User $staff): bool
    {
        if ((int) $locked->customer_id === (int) $customer->id) {
            return false;
        }

        $this->guard->assertEditable($locked, forCustomerChange: true);

        $before = $locked->only(['customer_id', 'customer_name', 'customer_email']);

        $locked->forceFill([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            // A pay or confirmation link sent to the previous customer must stop working.
            'storefront_token' => Order::generateStorefrontToken(),
        ])->save();

        DiscountRedemption::query()
            ->where('order_id', $locked->id)
            ->update(['customer_id' => $customer->id]);

        $this->audit->log(
            'order.customer_changed',
            $locked,
            [
                'order_number' => $locked->number,
                'from_customer_id' => $before['customer_id'] === null ? null : (int) $before['customer_id'],
                'to_customer_id' => (int) $customer->id,
                'staff_id' => $staff->id,
            ],
            $before,
            $locked->only(['customer_id', 'customer_name', 'customer_email']),
        );

        return true;
    }

    private function fresh(Order $order): Order
    {
        return $order->fresh(['items', 'payment.attempts', 'invoices', 'creditNotes', 'refunds', 'customer'])
            ?? throw new RuntimeException('Order disappeared after a customer change.');
    }
}
