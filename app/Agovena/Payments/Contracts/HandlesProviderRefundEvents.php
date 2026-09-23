<?php

declare(strict_types=1);

namespace App\Agovena\Payments\Contracts;

use App\Agovena\Payments\ProviderRefundEvent;
use App\Agovena\Payments\WebhookPayload;

interface HandlesProviderRefundEvents
{
    public function providerRefundEvent(WebhookPayload $payload): ?ProviderRefundEvent;
}
