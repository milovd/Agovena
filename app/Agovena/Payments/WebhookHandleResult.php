<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Models\PaymentWebhookEvent;

final readonly class WebhookHandleResult
{
    public function __construct(
        public PaymentWebhookEvent $event,
        public bool $duplicate,
    ) {}
}
