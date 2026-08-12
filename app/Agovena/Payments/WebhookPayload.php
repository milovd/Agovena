<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Enums\PaymentStatus;

final readonly class WebhookPayload
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $externalEventId,
        public ?string $externalPaymentId,
        public PaymentStatus $status,
        public array $raw = [],
    ) {}
}
