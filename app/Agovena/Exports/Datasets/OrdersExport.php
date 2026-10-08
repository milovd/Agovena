<?php

declare(strict_types=1);

namespace App\Agovena\Exports\Datasets;

use App\Agovena\Exports\ExportDataset;
use App\Agovena\Exports\ExportFilters;
use App\Agovena\Exports\ExportValues;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * Orders with their totals. Storefront tokens, idempotency keys and option
 * snapshots (which can carry delivery secrets) are never exported.
 */
final class OrdersExport implements ExportDataset
{
    public function __construct(private readonly ExportValues $values) {}

    public function key(): string
    {
        return 'orders';
    }

    public function permission(): string
    {
        return 'orders.view';
    }

    public function recordElement(): string
    {
        return 'order';
    }

    public function columns(): array
    {
        return [
            'id',
            'number',
            'status',
            'customer_id',
            'customer_email',
            'customer_name',
            'billing_name',
            'billing_company',
            'billing_line1',
            'billing_line2',
            'billing_city',
            'billing_region',
            'billing_postal_code',
            'billing_country',
            'shipping_country',
            'shipping_method_label',
            'currency',
            'subtotal',
            'discount',
            'shipping',
            'tax',
            'payment_fee',
            'credit',
            'total',
            'discount_code',
            'referral_code',
            'tax_rate_name',
            'item_count',
            'payment_status',
            'payment_method',
            'due_at',
            'created_at',
            'updated_at',
        ];
    }

    public function count(ExportFilters $filters): int
    {
        return $filters->apply(Order::query())->count();
    }

    public function rows(ExportFilters $filters): iterable
    {
        /** @var Builder<Order> $query */
        $query = $filters->apply(Order::query())
            ->with('payment:id,order_id,status,method')
            ->withCount('items');

        foreach ($query->lazyById(500, 'orders.id', 'id') as $order) {
            /** @var Order $order */
            $currency = $order->currency;

            yield [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $this->values->enum($order->status),
                'customer_id' => $order->customer_id,
                'customer_email' => $order->customer_email,
                'customer_name' => $order->customer_name,
                'billing_name' => $this->string($order, 'billing_name'),
                'billing_company' => $this->string($order, 'billing_company'),
                'billing_line1' => $this->string($order, 'billing_line1'),
                'billing_line2' => $this->string($order, 'billing_line2'),
                'billing_city' => $this->string($order, 'billing_city'),
                'billing_region' => $this->string($order, 'billing_region'),
                'billing_postal_code' => $this->string($order, 'billing_postal_code'),
                'billing_country' => $this->string($order, 'billing_country'),
                'shipping_country' => $this->string($order, 'shipping_country'),
                'shipping_method_label' => $this->string($order, 'shipping_method_label'),
                'currency' => $currency,
                'subtotal' => $this->values->money($order->subtotal_amount, $currency),
                'discount' => $this->values->money((int) $order->getAttribute('discount_amount'), $currency),
                'shipping' => $this->values->money((int) $order->getAttribute('shipping_amount'), $currency),
                'tax' => $this->values->money((int) $order->tax_amount, $currency),
                'payment_fee' => $this->values->money((int) $order->payment_fee_amount, $currency),
                'credit' => $this->values->money((int) $order->credit_amount, $currency),
                'total' => $this->values->money($order->total_amount, $currency),
                'discount_code' => $this->string($order, 'discount_code'),
                'referral_code' => $this->string($order, 'referral_code'),
                'tax_rate_name' => $this->string($order, 'tax_rate_name'),
                'item_count' => (int) $order->getAttribute('items_count'),
                'payment_status' => $this->values->enum($order->payment?->status),
                'payment_method' => $order->payment?->method,
                'due_at' => $this->values->dateTime($order->due_at),
                'created_at' => $this->values->dateTime($order->created_at),
                'updated_at' => $this->values->dateTime($order->updated_at),
            ];
        }
    }

    private function string(Order $order, string $attribute): ?string
    {
        $value = $order->getAttribute($attribute);

        return $value === null ? null : (string) $value;
    }
}
