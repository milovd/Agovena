<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

final class RecordManualPayment
{
    public function __construct(
        private readonly CompleteDirectPayment $completeDirectPayment,
    ) {}

    /**
     * Mark a pending manual payment as received. Idempotent: already-paid is a no-op success.
     */
    public function handle(Order $order, User $staff, ?string $reference = null): Payment
    {
        if (! $staff->can('payments.record')) {
            abort(403);
        }

        return $this->completeDirectPayment->handle($order, 'manual', $reference);
    }
}
