<?php

declare(strict_types=1);

namespace App\Agovena\Billing;

use App\Agovena\Audit\AuditLogger;
use App\Agovena\Invoices\IssueInvoiceFromOrder;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ConsolidatedRenewalOrderBuilder
{
    public function __construct(
        private readonly IssueInvoiceFromOrder $issueInvoice,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<ConsolidatedBillingLine>  $lines
     */
    public function create(array $lines): Order
    {
        if ($lines === []) {
            throw new InvalidArgumentException('At least one billing line is required.');
        }

        $first = $lines[0];
        foreach ($lines as $line) {
            $this->assertCompatible($first, $line);
        }

        $identity = array_map(static fn (ConsolidatedBillingLine $line): array => [
            $line->sourceType,
            $line->sourceId,
            $line->dueAt->toIso8601String(),
        ], $lines);
        usort($identity, static fn (array $left, array $right): int => $left <=> $right);
        $idempotencyKey = 'consolidated-renewal:'.hash('sha256', json_encode([
            'customer_id' => $first->customerId,
            'customer_email' => $first->customerEmail,
            'currency' => $first->currency,
            'gateway' => $first->gatewayId,
            'lines' => $identity,
        ], JSON_THROW_ON_ERROR));

        $existing = Order::query()
            ->where('idempotency_key', $idempotencyKey)
            ->with(['items', 'payment'])
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $subtotal = array_sum(array_map(static fn (ConsolidatedBillingLine $line): int => $line->lineTotal(), $lines));
        $dueAt = collect($lines)->min(static fn (ConsolidatedBillingLine $line): int => $line->dueAt->getTimestamp());
        if (! is_int($dueAt)) {
            throw new RuntimeException('Unable to determine the consolidated due date.');
        }

        try {
            return DB::transaction(function () use ($first, $lines, $subtotal, $idempotencyKey, $dueAt): Order {
                $existing = Order::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing !== null) {
                    return $existing->load(['items', 'payment']);
                }

                $order = Order::query()->create([
                    'number' => $this->generateOrderNumber(),
                    'status' => OrderStatus::Pending,
                    'customer_id' => $first->customerId,
                    'customer_name' => $first->customerName,
                    'customer_email' => $first->customerEmail,
                    'currency' => $first->currency,
                    'subtotal_amount' => $subtotal,
                    'shipping_amount' => 0,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'credit_amount' => 0,
                    'total_amount' => $subtotal,
                    'due_at' => date('Y-m-d H:i:s', $dueAt),
                    'idempotency_key' => $idempotencyKey,
                    'shipping_same_as_billing' => true,
                ]);

                foreach ($lines as $line) {
                    OrderItem::query()->create([
                        'order_id' => $order->id,
                        'product_id' => $line->productId,
                        'label' => $line->label,
                        'quantity' => $line->quantity,
                        'unit_amount' => $line->billableUnitAmount(),
                        'line_total_amount' => $line->lineTotal(),
                        'currency' => $line->currency,
                        'options_snapshot' => array_merge($line->optionsSnapshot, [
                            'consolidated_billing' => [
                                'source_type' => $line->sourceType,
                                'source_id' => $line->sourceId,
                                'due_at' => $line->dueAt->toIso8601String(),
                                'next_period_end' => $line->nextPeriodEnd->toIso8601String(),
                                'period_days' => $line->periodDays,
                                'days_already_paid' => $line->daysAlreadyPaid,
                            ],
                        ]),
                    ]);
                }

                Payment::query()->create([
                    'order_id' => $order->id,
                    'method' => $first->gatewayId ?: 'manual',
                    'status' => PaymentStatus::Pending,
                    'amount' => $subtotal,
                    'currency' => $first->currency,
                ]);

                $order = $order->fresh(['items', 'payment']) ?? $order;
                $this->issueInvoice->handle($order);
                $this->audit->log('billing.renewal_consolidated', $order, [
                    'order_number' => $order->number,
                    'source_count' => count($lines),
                    'due_at' => date('c', $dueAt),
                    'total_amount' => $subtotal,
                ]);

                return $order;
            });
        } catch (Throwable $exception) {
            $winner = Order::query()
                ->where('idempotency_key', $idempotencyKey)
                ->with(['items', 'payment'])
                ->first();
            if ($winner !== null) {
                return $winner;
            }

            throw $exception;
        }
    }

    private function assertCompatible(ConsolidatedBillingLine $first, ConsolidatedBillingLine $line): void
    {
        if ($line->customerId !== $first->customerId
            || $line->customerEmail !== $first->customerEmail
            || $line->currency !== $first->currency
            || $line->gatewayId !== $first->gatewayId) {
            throw new InvalidArgumentException('Consolidated billing lines must share customer, currency and gateway.');
        }
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'REN-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}
