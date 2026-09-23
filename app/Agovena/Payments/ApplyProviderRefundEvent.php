<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Events\RefundRecorded;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

final class ApplyProviderRefundEvent
{
    public function handle(ProviderRefundEvent $event): bool
    {
        return DB::transaction(function () use ($event): bool {
            $refund = Refund::query()
                ->where('provider_reference', $event->externalRefundId)
                ->lockForUpdate()
                ->first();

            if ($refund === null) {
                $attempt = PaymentAttempt::query()
                    ->where('gateway_id', $event->gatewayId)
                    ->where('external_id', $event->transactionId)
                    ->latest('id')
                    ->first();

                if ($attempt !== null) {
                    $refund = Refund::query()
                        ->where('payment_id', $attempt->payment_id)
                        ->whereIn('status', [RefundStatus::Pending, RefundStatus::Processing])
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();
                }
            }

            if ($refund === null) {
                return false;
            }

            $refund->provider_reference = $event->externalRefundId;
            $status = strtolower($event->status);
            if (in_array($status, ['pending', 'pending_approval', 'processing'], true)) {
                $refund->status = RefundStatus::Processing;
                $refund->provider_claimed_at = now();
                $refund->save();

                return true;
            }

            if ($status === 'approved') {
                $wasCompleted = $refund->status === RefundStatus::Completed;
                $refund->status = RefundStatus::Completed;
                $refund->completed_at ??= now();
                $refund->provider_claimed_at = null;
                $refund->save();
                $this->syncPaymentStatus($refund->payment()->lockForUpdate()->firstOrFail());

                if (! $wasCompleted) {
                    event(new RefundRecorded($refund->fresh() ?? $refund));
                }

                return true;
            }

            if (in_array($status, ['rejected', 'reversed'], true)) {
                $refund->status = RefundStatus::Failed;
                $refund->provider_claimed_at = null;
                $refund->save();

                return true;
            }

            return false;
        });
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
