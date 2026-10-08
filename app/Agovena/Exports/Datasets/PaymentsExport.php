<?php

declare(strict_types=1);

namespace App\Agovena\Exports\Datasets;

use App\Agovena\Exports\ExportDataset;
use App\Agovena\Exports\ExportFilters;
use App\Agovena\Exports\ExportValues;
use App\Enums\RefundStatus;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recorded payments. Provider attempts and reconciliation payloads (raw
 * provider data) are never exported; only the reconciliation status is.
 */
final class PaymentsExport implements ExportDataset
{
    public function __construct(private readonly ExportValues $values) {}

    public function key(): string
    {
        return 'payments';
    }

    /** Payments are shown to staff on the order detail page. */
    public function permission(): string
    {
        return 'orders.view';
    }

    public function recordElement(): string
    {
        return 'payment';
    }

    public function columns(): array
    {
        return [
            'id',
            'order_id',
            'order_number',
            'customer_email',
            'method',
            'status',
            'amount',
            'refunded_amount',
            'currency',
            'reference',
            'reconciliation_status',
            'paid_at',
            'created_at',
            'updated_at',
        ];
    }

    public function count(ExportFilters $filters): int
    {
        return $filters->apply(Payment::query())->count();
    }

    public function rows(ExportFilters $filters): iterable
    {
        /** @var Builder<Payment> $query */
        $query = $filters->apply(Payment::query())
            ->select(['id', 'order_id', 'amount', 'currency', 'method', 'status', 'paid_at', 'reference', 'reconciliation_status', 'created_at', 'updated_at'])
            ->with('order:id,number,customer_email')
            ->withSum(['refunds as refunded_minor' => static function (Builder $refunds): void {
                $refunds->where('status', RefundStatus::Completed->value);
            }], 'amount');

        foreach ($query->lazyById(500, 'payments.id', 'id') as $payment) {
            /** @var Payment $payment */
            yield [
                'id' => $payment->id,
                'order_id' => $payment->order_id,
                'order_number' => $payment->order?->number,
                'customer_email' => $payment->order?->customer_email,
                'method' => $payment->method,
                'status' => $this->values->enum($payment->status),
                'amount' => $this->values->money($payment->amount, $payment->currency),
                'refunded_amount' => $this->values->money((int) $payment->getAttribute('refunded_minor'), $payment->currency),
                'currency' => $payment->currency,
                'reference' => $payment->reference,
                'reconciliation_status' => $payment->reconciliation_status,
                'paid_at' => $this->values->dateTime($payment->paid_at),
                'created_at' => $this->values->dateTime($payment->created_at),
                'updated_at' => $this->values->dateTime($payment->updated_at),
            ];
        }
    }
}
