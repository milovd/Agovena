<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

final readonly class ProviderRefundEvent
{
    public function __construct(
        public string $gatewayId,
        public string $externalRefundId,
        public string $transactionId,
        public string $status,
    ) {}
}
