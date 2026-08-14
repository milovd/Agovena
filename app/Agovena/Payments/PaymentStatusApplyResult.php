<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Models\PaymentAttempt;

/**
 * Outcome of applying a provider-normalized payment status.
 */
final class PaymentStatusApplyResult
{
    public function __construct(
        public readonly PaymentAttempt $attempt,
        public readonly bool $applied,
        public readonly bool $blockedByTerminalState = false,
    ) {}
}
