<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Models\Payment;

final readonly class RefundRequest
{
    public function __construct(
        public Payment $payment,
        public int $amount,
        public string $currency,
        public ?string $reason = null,
        public ?string $idempotencyKey = null,
    ) {}
}
