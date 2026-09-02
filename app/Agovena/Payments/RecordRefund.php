<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Audit\AuditLogger;
use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\Gateways\DevelopmentPaymentGateway;
use App\Agovena\Payments\Gateways\ManualPaymentGateway;
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
use Throwable;

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

        /** @var array{payment: Payment, refund: Refund, gateway: PaymentGateway, amount: int, currency: string, reason: string, shouldInvoke: bool} $preparation */
        $preparation = DB::transaction(function () use ($payment, $staff, $amount, $reason, $creditNoteId): array {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing('order.invoices');

            $creditNote = $this->resolveCreditNote($locked, $creditNoteId);
            $creditNoteKey = $creditNote instanceof CreditNote ? (int) $creditNote->id : 0;
            $gateway = $this->resolveGateway($locked);

            if (! $gateway->capabilities()->refunds) {
                throw ValidationException::withMessages([
                    'payment' => __('admin.payments.refunds_not_supported'),
                ]);
            }

            $pendingRefund = Refund::query()
                ->where('payment_id', $locked->id)
                ->whereIn('status', [RefundStatus::Pending, RefundStatus::Processing])
                ->latest('id')
                ->lockForUpdate()
                ->first();
            if ($pendingRefund !== null) {
                if ($pendingRefund->amount !== $amount
                    || $pendingRefund->reason !== $reason
                    || (int) ($pendingRefund->credit_note_id ?? 0) !== $creditNoteKey
                ) {
                    throw ValidationException::withMessages([
                        'payment' => __('admin.refunds.gateway_failed'),
                    ]);
                }

                $refund = $pendingRefund;
                if ($refund->status === RefundStatus::Processing
                    && $refund->provider_claimed_at?->gt(now()->subMinutes(10))) {
                    return [
                        'payment' => $locked,
                        'refund' => $refund,
                        'gateway' => $gateway,
                        'amount' => $amount,
                        'currency' => $locked->currency,
                        'reason' => $reason,
                        'shouldInvoke' => false,
                    ];
                }

            } else {
                $remaining = $locked->remainingRefundable();
                if ($amount > $remaining) {
                    throw ValidationException::withMessages([
                        'amount' => __('admin.refunds.exceeds_remaining'),
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
                    'invoice_id' => $locked->order?->invoices()->count() === 1
                        ? $locked->order->invoices()->value('id')
                        : null,
                    'credit_note_id' => $creditNote?->id,
                    'created_by' => $staff->id,
                    'amount' => $amount,
                    'currency' => $locked->currency,
                    'status' => RefundStatus::Processing,
                    'reason' => $reason,
                    'provider_claimed_at' => now(),
                ]);

                $this->audit->log('refund.created', $refund, [
                    'payment_id' => $locked->id,
                    'amount' => $amount,
                    'currency' => $locked->currency,
                ]);
            }

            $refund->status = RefundStatus::Processing;
            $refund->provider_claimed_at = now();
            $refund->save();

            return [
                'payment' => $locked,
                'refund' => $refund,
                'gateway' => $gateway,
                'amount' => $amount,
                'currency' => $locked->currency,
                'reason' => $reason,
                'shouldInvoke' => true,
            ];
        });

        if (! $preparation['shouldInvoke']) {
            return $preparation['refund']->fresh() ?? $preparation['refund'];
        }

        try {
            $result = $preparation['gateway']->refund(new RefundRequest(
                payment: $preparation['payment'],
                amount: $preparation['amount'],
                currency: $preparation['currency'],
                reason: $preparation['reason'],
                idempotencyKey: 'refund-'.$preparation['refund']->id,
            ));

            if ($result->success && (! is_string($result->externalRefundId) || trim($result->externalRefundId) === '')) {
                $result = RefundResult::unknown(
                    ['reason' => 'provider_refund_response_invalid'],
                    __('admin.refunds.gateway_failed'),
                );
            }

            return DB::transaction(function () use ($preparation, $result): Refund {
                /** @var Payment $locked */
                $locked = Payment::query()->whereKey($preparation['payment']->id)->lockForUpdate()->firstOrFail();
                /** @var Refund $refund */
                $refund = Refund::query()->whereKey($preparation['refund']->id)->lockForUpdate()->firstOrFail();

                if ($refund->status !== RefundStatus::Processing) {
                    return $refund->fresh() ?? throw new RuntimeException('Refund disappeared after provider processing.');
                }

                if ($result->unknownOutcome) {
                    $locked->reconciliation_status = 'manual_review';
                    $locked->reconciliation_meta = array_merge(
                        is_array($locked->reconciliation_meta) ? $locked->reconciliation_meta : [],
                        [
                            'reason' => 'provider_refund_outcome_unknown',
                            'refund_id' => $refund->id,
                            'recorded_at' => now()->toIso8601String(),
                        ],
                    );
                    $refund->status = RefundStatus::Pending;
                    $refund->provider_claimed_at = null;
                    $refund->save();
                    $locked->save();

                    $this->audit->log('refund.manual_review', $refund, [
                        'payment_id' => $locked->id,
                        'reason' => 'provider_refund_outcome_unknown',
                    ], outcome: 'manual_review', severity: 'high', category: 'refund');

                    return $refund->fresh() ?? throw new RuntimeException('Refund disappeared during reconciliation review.');
                }

                if (! $result->success) {
                    $refund->status = RefundStatus::Failed;
                    $refund->provider_claimed_at = null;
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
                $refund->provider_claimed_at = null;
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
        } catch (Throwable $exception) {
            DB::transaction(function () use ($preparation): void {
                $refund = Refund::query()->whereKey($preparation['refund']->id)->lockForUpdate()->first();
                if ($refund?->status === RefundStatus::Processing) {
                    $refund->status = RefundStatus::Pending;
                    $refund->provider_claimed_at = null;
                    $refund->save();
                }
            });

            throw $exception;
        }
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
        $gateway = $this->gateways->get($payment->method)
            ?? match ($payment->method) {
                'manual' => app(ManualPaymentGateway::class),
                'development' => app(DevelopmentPaymentGateway::class),
                default => null,
            };

        if ($gateway === null) {
            throw ValidationException::withMessages([
                'payment' => __('admin.payments.refunds_not_supported'),
            ]);
        }

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
