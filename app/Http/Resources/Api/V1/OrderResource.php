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
        $order->loadMissing(['items', 'payment.refunds', 'invoice', 'creditNotes']);
        $payment = $order->payment;
        $refunded = $payment?->refundedAmount() ?? 0;

        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status->value,
            'currency' => $order->currency,
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
            'invoice' => $order->invoice === null ? null : [
                'id' => $order->invoice->id,
                'number' => $order->invoice->number,
                'status' => $order->invoice->status->value,
            ],
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
}
