<?php

declare(strict_types=1);

namespace App\Agovena\Orders;

use App\Agovena\Audit\AuditLogger;
use App\Agovena\Payments\Contracts\CancelsPayments;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Events\InvoiceVoided;
use App\Events\OrderCancelled;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class CancelUnpaidOrder
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PaymentGatewayRegistry $gateways,
    ) {}

    public function handle(Order $order, UnpaidOrderCancelSource $source, ?User $staff = null): Order
    {
        if ($source === UnpaidOrderCancelSource::Staff) {
            if ($staff === null || (! $staff->can('orders.cancel') && ! $staff->can('invoices.void'))) {
                abort(403);
            }
        }

        $order->loadMissing('payment');
        if (! $order->isAwaitingPayment()) {
            throw ValidationException::withMessages([
                'order' => $source === UnpaidOrderCancelSource::Customer
                    ? __('customer.account.cannot_cancel_order')
                    : __('admin.orders.cannot_cancel'),
            ]);
        }

        if ($order->payment !== null && ! $this->attemptProviderCancel($order->payment)) {
            if ($source === UnpaidOrderCancelSource::Scheduler) {
                return $order->fresh(['items', 'payment', 'invoice']) ?? $order;
            }

            throw ValidationException::withMessages([
                'order' => $source === UnpaidOrderCancelSource::Customer
                    ? __('customer.account.cannot_cancel_order')
                    : __('admin.orders.cannot_cancel'),
            ]);
        }

        return DB::transaction(function () use ($order, $source, $staff): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $payment = Payment::query()->where('order_id', $locked->id)->lockForUpdate()->first();
            $locked->setRelation('payment', $payment);
            $locked->load(['invoice']);

            if ($payment !== null && $payment->status === PaymentStatus::Paid) {
                throw ValidationException::withMessages([
                    'order' => $source === UnpaidOrderCancelSource::Customer
                        ? __('customer.account.cannot_cancel_order')
                        : __('admin.orders.cannot_cancel'),
                ]);
            }

            if (! $locked->isAwaitingPayment()) {
                throw ValidationException::withMessages([
                    'order' => $source === UnpaidOrderCancelSource::Customer
                        ? __('customer.account.cannot_cancel_order')
                        : __('admin.orders.cannot_cancel'),
                ]);
            }

            $invoice = $locked->invoice;
            if ($invoice !== null) {
                /** @var Invoice $invoice */
                $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
                if (! $invoice->canVoid()) {
                    throw ValidationException::withMessages([
                        'order' => __('admin.invoices.cannot_void'),
                    ]);
                }

                $invoice->status = InvoiceStatus::Void;
                $invoice->save();
            }

            if ($locked->status === OrderStatus::Pending) {
                $locked->status = OrderStatus::Cancelled;
                $locked->save();
            }

            if ($payment !== null && in_array($payment->status, [
                PaymentStatus::Pending,
                PaymentStatus::Failed,
                PaymentStatus::Cancelled,
            ], true)) {
                $payment->status = PaymentStatus::Cancelled;
                $payment->save();

                PaymentAttempt::query()
                    ->where('payment_id', $payment->id)
                    ->whereIn('status', [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing])
                    ->update([
                        'status' => PaymentAttemptStatus::Cancelled,
                        'completed_at' => now(),
                    ]);
            }

            $fresh = $locked->fresh(['items', 'invoice', 'payment'])
                ?? throw new RuntimeException('Cancelled order disappeared.');

            if ($fresh->invoice !== null) {
                $this->audit->log('invoice.voided', $fresh->invoice, [
                    'invoice_number' => $fresh->invoice->number,
                    'order_id' => $fresh->id,
                    'source' => $source->value,
                    'staff_id' => $staff?->id,
                ]);
                event(new InvoiceVoided($fresh->invoice));
            }

            $this->audit->log('order.cancelled', $fresh, [
                'order_number' => $fresh->number,
                'source' => $source->value,
                'staff_id' => $staff?->id,
            ]);
            event(new OrderCancelled($fresh));

            return $fresh;
        });
    }

    private function attemptProviderCancel(Payment $payment): bool
    {
        $method = (string) $payment->method;
        if ($method === '' || in_array($method, ['manual', 'development'], true)) {
            return true;
        }

        $gateway = $this->gateways->get($method);
        if (! $gateway instanceof CancelsPayments) {
            return true;
        }

        $gatewayId = $gateway->id();
        try {
            $gateway->cancel($payment);

            return true;
        } catch (Throwable $exception) {
            Log::warning('payment.provider_cancel_failed', [
                'payment_id' => $payment->id,
                'gateway_id' => $gatewayId,
            ]);
            $this->recordProviderCancellationFailure($payment, $gatewayId);

            return false;
        }
    }

    private function recordProviderCancellationFailure(Payment $payment, string $gatewayId): void
    {
        DB::transaction(function () use ($payment, $gatewayId): void {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();
            if ($lockedPayment === null) {
                throw new RuntimeException('Payment disappeared while recording cancellation failure.');
            }

            $lockedPayment->reconciliation_status = 'manual_review';
            $lockedPayment->reconciliation_meta = [
                'reason' => 'provider_cancel_failed',
                'gateway_id' => $gatewayId,
                'recorded_at' => now()->toIso8601String(),
            ];
            $lockedPayment->save();

            $this->audit->log(
                'payment.reconciliation_required',
                $lockedPayment,
                [
                    'payment_id' => $lockedPayment->id,
                    'gateway_id' => $gatewayId,
                    'reason' => 'provider_cancel_failed',
                ],
                outcome: 'manual_review',
                severity: 'high',
                category: 'payments',
            );
        });
    }
}
