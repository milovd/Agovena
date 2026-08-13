<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Audit\AuditLogger;
use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\Gateways\DevelopmentPaymentGateway;
use App\Agovena\Payments\Gateways\ManualPaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Events\RefundRecorded;
use App\Models\CreditNote;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class RecordRefund
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(
        Payment $payment,
        User $staff,
        int $amount,
        string $reason,
        ?int $creditNoteId = null,
    ): Refund {
        if (! $staff->can('payments.refund')) {
            abort(403);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => __('admin.refunds.reason_required'),
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('admin.refunds.amount_invalid'),
            ]);
        }

        return DB::transaction(function () use ($payment, $staff, $amount, $reason, $creditNoteId): Refund {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing('order.invoice');

            $remaining = $locked->remainingRefundable();
            if ($amount > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => __('admin.refunds.exceeds_remaining'),
                ]);
            }

            $creditNote = $this->resolveCreditNote($locked, $creditNoteId);
            $gateway = $this->resolveGateway($locked);

            if (! $gateway->capabilities()->refunds) {
                throw ValidationException::withMessages([
                    'payment' => __('admin.payments.refunds_not_supported'),
                ]);
            }

            if ($amount < $remaining && ! $gateway->capabilities()->partialRefunds) {
                throw ValidationException::withMessages([
                    'amount' => __('admin.refunds.partial_not_supported'),
                ]);
            }

            $refund = Refund::query()->create([
                'payment_id' => $locked->id,
                'order_id' => $locked->order_id,
                'invoice_id' => $locked->order?->invoice?->id,
                'credit_note_id' => $creditNote?->id,
                'created_by' => $staff->id,
                'amount' => $amount,
                'currency' => $locked->currency,
                'status' => RefundStatus::Pending,
                'reason' => $reason,
            ]);

            $this->audit->log('refund.created', $refund, [
                'payment_id' => $locked->id,
                'amount' => $amount,
                'currency' => $locked->currency,
            ]);

            $result = $gateway->refund(new RefundRequest(
                payment: $locked,
                amount: $amount,
                currency: $locked->currency,
                reason: $reason,
            ));

            if (! $result->success) {
                $refund->status = RefundStatus::Failed;
                $refund->save();

                $this->audit->log('refund.failed', $refund, [
                    'message' => $result->message,
                ]);

                throw ValidationException::withMessages([
                    'payment' => $result->message ?: __('admin.refunds.gateway_failed'),
                ]);
            }

            $refund->status = RefundStatus::Completed;
            $refund->provider_reference = $result->externalRefundId;
            $refund->completed_at = now();
            $refund->save();

            $this->syncPaymentStatus($locked);

            $completed = $refund->fresh() ?? throw new RuntimeException('Refund disappeared after completion.');

            $this->audit->log('refund.completed', $completed, [
                'payment_id' => $locked->id,
                'amount' => $completed->amount,
                'payment_status' => $locked->fresh()?->status->value,
            ]);

            event(new RefundRecorded($completed));

            return $completed;
        });
    }

    private function resolveCreditNote(Payment $payment, ?int $creditNoteId): ?CreditNote
    {
        if ($creditNoteId === null) {
            return null;
        }

        $creditNote = CreditNote::query()->whereKey($creditNoteId)->first();
        if ($creditNote === null || (int) $creditNote->order_id !== (int) $payment->order_id) {
            throw ValidationException::withMessages([
                'credit_note_id' => __('admin.refunds.credit_note_mismatch'),
            ]);
        }

        return $creditNote;
    }

    private function resolveGateway(Payment $payment): PaymentGateway
    {
        $gateway = $this->gateways->get($payment->method->value)
            ?? match ($payment->method) {
                PaymentMethod::Manual => app(ManualPaymentGateway::class),
                PaymentMethod::Development => app(DevelopmentPaymentGateway::class),
            };

        return $gateway;
    }

    private function syncPaymentStatus(Payment $payment): void
    {
        $refunded = $payment->refundedAmount();

        if ($refunded >= (int) $payment->amount) {
            $payment->status = PaymentStatus::Refunded;
        } elseif ($refunded > 0) {
            $payment->status = PaymentStatus::PartiallyRefunded;
        }

        $payment->save();
    }
}
