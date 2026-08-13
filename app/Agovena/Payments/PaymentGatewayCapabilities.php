<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

final readonly class PaymentGatewayCapabilities
{
    public function __construct(
        public bool $refunds = false,
        public bool $partialRefunds = false,
        public bool $recurring = false,
        public bool $webhooks = false,
        public bool $redirect = false,
        public bool $statusSync = false,
        public bool $cancelPending = false,
    ) {}
}
