<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
final class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;
        $order->loadMissing(['items', 'payment.refunds', 'invoices', 'creditNotes']);
        $payment = $order->payment;
        $refunded = $payment?->refundedAmount() ?? 0;
        $primaryInvoice = $order->invoices->first();

        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status->value,
            'currency' => $order->currency,
            'customer' => [
                'id' => $order->customer_id,
                'name' => $order->customer_name,
                'email' => $order->customer_email,
            ],
            'billing' => $this->address($order, 'billing'),
            'shipping' => $this->address($order, 'shipping'),
            'custom_properties' => $order->custom_properties_snapshot ?? [],
            'custom_properties_snapshot' => $order->custom_properties_snapshot ?? [],
            'subtotal_amount' => $order->subtotal_amount,
            'total_amount' => $order->total_amount,
            'total_formatted' => MoneyFormatter::format($order->total_amount, $order->currency),
            'payment' => $payment === null ? null : [
                'status' => $payment->status->value,
                'amount' => $payment->amount,
                'amount_formatted' => MoneyFormatter::format($payment->amount, $payment->currency),
                'refunded_amount' => $refunded,
                'net_amount' => $payment->amount - $refunded,
            ],
            'invoice' => $primaryInvoice === null ? null : [
                'id' => $primaryInvoice->id,
                'number' => $primaryInvoice->number,
                'status' => $primaryInvoice->status->value,
            ],
            'invoices' => $order->invoices->map(static fn ($invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status->value,
            ])->values()->all(),
            'credit_notes' => $order->creditNotes->map(static fn ($note): array => [
                'id' => $note->id,
                'number' => $note->number,
                'total_amount' => $note->total_amount,
            ])->values()->all(),
            'items' => $order->items->map(static fn ($item): array => [
                'label' => $item->label,
                'quantity' => $item->quantity,
                'unit_amount' => $item->unit_amount,
                'line_total_amount' => $item->line_total_amount,
            ])->values()->all(),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, string|null> */
    private function address(Order $order, string $prefix): array
    {
        return [
            'name' => $order->{$prefix.'_name'},
            'company' => $order->{$prefix.'_company'},
            'line1' => $order->{$prefix.'_line1'},
            'line2' => $order->{$prefix.'_line2'},
            'city' => $order->{$prefix.'_city'},
            'region' => $order->{$prefix.'_region'},
            'postal_code' => $order->{$prefix.'_postal_code'},
            'country' => $order->{$prefix.'_country'},
            'phone' => $order->{$prefix.'_phone'},
        ];
    }
}
