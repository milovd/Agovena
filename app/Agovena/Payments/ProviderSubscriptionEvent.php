<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

/**
 * Provider-neutral subscription lifecycle event.
 * Provider identifiers stay in the extension payload and are never exposed to
 * generic checkout contracts.
 */
final readonly class ProviderSubscriptionEvent
{
    /**
     * @param  array<string, mixed>  $customData
     */
    public function __construct(
        public string $gatewayId,
        public string $externalSubscriptionId,
        public string $eventType,
        public string $status,
        public ?string $transactionId = null,
        public ?string $originOrderId = null,
        public ?string $periodStart = null,
        public ?string $periodEnd = null,
        public ?string $nextBillingAt = null,
        public ?bool $cancelAtPeriodEnd = null,
        public array $customData = [],
    ) {}
}
