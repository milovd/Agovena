<?php

declare(strict_types=1);

namespace App\Agovena\Orders;

use App\Agovena\Audit\AuditLogger;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class UpdateOrder
{
    /** @var list<string> */
    private const EDITABLE_ATTRIBUTES = [
        'customer_name',
        'customer_email',
        'billing_name',
        'billing_company',
        'billing_line1',
        'billing_line2',
        'billing_city',
        'billing_region',
        'billing_postal_code',
        'billing_country',
        'billing_phone',
        'shipping_name',
        'shipping_company',
        'shipping_line1',
        'shipping_line2',
        'shipping_city',
        'shipping_region',
        'shipping_postal_code',
        'shipping_country',
        'shipping_phone',
        'shipping_same_as_billing',
        'due_at',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Order $order, array $attributes): Order
    {
        return DB::transaction(function () use ($order, $attributes): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $data = array_intersect_key($attributes, array_flip(self::EDITABLE_ATTRIBUTES));
            if (($data['shipping_same_as_billing'] ?? false) === true) {
                foreach (['name', 'company', 'line1', 'line2', 'city', 'region', 'postal_code', 'country', 'phone'] as $field) {
                    $data['shipping_'.$field] = $data['billing_'.$field] ?? null;
                }
            }

            $before = $locked->only(self::EDITABLE_ATTRIBUTES);
            $locked->fill($data);
            if ($locked->isDirty()) {
                $locked->save();
                $this->audit->logChange(
                    'order.updated',
                    $locked,
                    $before,
                    $locked->only(self::EDITABLE_ATTRIBUTES),
                    ['order_number' => $locked->number],
                );
            }

            $fresh = $locked->fresh(['items', 'payment.attempts', 'invoice', 'creditNotes', 'refunds']);
            if ($fresh === null) {
                throw new RuntimeException('Order disappeared after update.');
            }

            return $fresh;
        });
    }
}
