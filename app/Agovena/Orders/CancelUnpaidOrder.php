<?php

declare(strict_types=1);

namespace App\Agovena\Orders;

use App\Agovena\Audit\AuditLogger;
use App\Agovena\Payments\Contracts\CancelsPayments;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Payments\PaymentLifecycleLock;
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
use Illuminate\Contracts\Cache\LockTimeoutException;
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
        private readonly PaymentLifecycleLock $lifecycleLock,
    ) {}

    public function handle(Order $order, UnpaidOrderCancelSource $source, ?User $staff = null): Order
    {
        try {
            return $this->lifecycleLock->run(
                $order->id,
                fn (): Order => $this->handleLocked($order, $source, $staff),
            );
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'order' => __('admin.orders.cannot_cancel'),
            ]);
        }
    }

    private function handleLocked(Order $order, UnpaidOrderCancelSource $source, ?User $staff = null): Order
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

        $payment = DB::transaction(function () use ($order, $source): ?Payment {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $payment = Payment::query()->where('order_id', $locked->id)->lockForUpdate()->first();
            $locked->setRelation('payment', $payment);

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

            return $payment;
        });

        if ($payment !== null && ! $this->attemptProviderCancel($payment)) {
            if ($source !== UnpaidOrderCancelSource::Scheduler) {
                throw ValidationException::withMessages([
                    'order' => $source === UnpaidOrderCancelSource::Customer
                        ? __('customer.account.cannot_cancel_order')
                        : __('admin.orders.cannot_cancel'),
                ]);
            }

            return $order->fresh(['items', 'invoice', 'payment'])
                ?? throw new RuntimeException('Order disappeared after provider cancellation failure.');
        }

        $paidDuringCancellation = false;
        $result = DB::transaction(function () use ($order, $source, $staff, &$paidDuringCancellation): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $payment = Payment::query()->where('order_id', $locked->id)->lockForUpdate()->first();
            $locked->setRelation('payment', $payment);
            $locked->load(['invoice']);

            if ($payment !== null && $payment->status === PaymentStatus::Paid) {
                $paidDuringCancellation = true;
                $payment->reconciliation_status = 'manual_review';
                $payment->reconciliation_meta = [
                    'reason' => 'payment_paid_during_cancellation',
                    'gateway_id' => (string) $payment->method,
                    'recorded_at' => now()->toIso8601String(),
                ];
                $payment->save();

                return $locked->fresh(['items', 'invoice', 'payment'])
                    ?? throw new RuntimeException('Order disappeared during cancellation recheck.');
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

        if ($paidDuringCancellation && $source !== UnpaidOrderCancelSource::Scheduler) {
            throw ValidationException::withMessages([
                'order' => $source === UnpaidOrderCancelSource::Customer
                    ? __('customer.account.cannot_cancel_order')
                    : __('admin.orders.cannot_cancel'),
            ]);
        }

        return $result;
    }

    private function attemptProviderCancel(Payment $payment): bool
    {
        $method = (string) $payment->method;
        $openAttempts = PaymentAttempt::query()
            ->where('payment_id', $payment->id)
            ->whereIn('status', [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing])
            ->orderBy('id')
            ->get();
        $unknownAttempt = $openAttempts->first(fn (PaymentAttempt $attempt): bool => ! filled($attempt->external_id));
        if ($unknownAttempt !== null) {
            $this->recordProviderCancellationFailure($payment, (string) $unknownAttempt->gateway_id);

            return false;
        }

        $targets = $openAttempts->isNotEmpty()
            ? $openAttempts
            : (in_array($method, ['manual', 'development'], true) ? [] : [null]);
        $cancelledAttemptIds = [];
        $allCancelled = true;

        foreach ($targets as $attempt) {
            $gatewayId = $attempt?->gateway_id ?? $method;
            if (! is_string($gatewayId) || $gatewayId === '') {
                $allCancelled = false;

                continue;
            }

            $gateway = $this->gateways->get($gatewayId);
            if (! $gateway instanceof CancelsPayments) {
                if (in_array($method, ['manual', 'development'], true) && $gatewayId !== $method) {
                    continue;
                }

                $this->recordProviderCancellationFailure($payment, $gatewayId);
                $allCancelled = false;

                continue;
            }

            try {
                $gateway->cancel($payment, $attempt);
                if ($attempt !== null) {
                    $cancelledAttemptIds[] = $attempt->id;
                }
            } catch (Throwable $exception) {
                Log::warning('payment.provider_cancel_failed', [
                    'payment_id' => $payment->id,
                    'gateway_id' => $gatewayId,
                    'attempt_id' => $attempt?->id,
                ]);
                $this->recordProviderCancellationFailure($payment, $gatewayId);
                $allCancelled = false;
            }
        }

        if ($cancelledAttemptIds !== []) {
            PaymentAttempt::query()
                ->where('payment_id', $payment->id)
                ->whereIn('id', $cancelledAttemptIds)
                ->whereIn('status', [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing])
                ->update([
                    'status' => PaymentAttemptStatus::Cancelled,
                    'completed_at' => now(),
                ]);
        }

        return $allCancelled;
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
