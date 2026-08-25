<?php

declare(strict_types=1);

namespace App\Agovena\Payments\Contracts;

use App\Agovena\Payments\WebhookPayload;
use App\Models\PaymentAttempt;

interface ValidatesWebhookPayload
{
    public function validateWebhookPayload(PaymentAttempt $attempt, WebhookPayload $payload): bool;
}
