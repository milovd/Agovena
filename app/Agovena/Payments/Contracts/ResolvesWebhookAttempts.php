<?php

declare(strict_types=1);

namespace App\Agovena\Payments\Contracts;

use App\Agovena\Payments\WebhookPayload;
use App\Models\PaymentAttempt;

/**
 * Allows a provider to resolve a webhook before its final provider reference
 * is known at checkout time.
 */
interface ResolvesWebhookAttempts
{
    public function resolveWebhookAttempt(WebhookPayload $payload): ?PaymentAttempt;
}
