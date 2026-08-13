<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Models\Order;
use App\Models\Payment;

final readonly class PaymentInitiation
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public Order $order,
        public Payment $payment,
        public string $returnUrl,
        public string $cancelUrl,
        public array $metadata = [],
        public ?string $idempotencyKey = null,
    ) {}
}
