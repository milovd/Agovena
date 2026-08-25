<?php

declare(strict_types=1);

namespace App\Agovena\Notifications;

use App\Models\PushSubscription;

interface PushTransport
{
    /** @param array<string, mixed> $payload */
    public function send(PushSubscription $subscription, array $payload): void;
}
