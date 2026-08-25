<?php

declare(strict_types=1);

namespace App\Agovena\Notifications;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;
use RuntimeException;

final class MinishlinkPushTransport implements PushTransport
{
    public function __construct(private readonly VapidKeyStore $keys) {}

    public function send(PushSubscription $subscription, array $payload): void
    {
        $vapid = $this->keys->get();
        if ($vapid === null) {
            throw new RuntimeException('VAPID keys are not configured.');
        }

        $webPush = new WebPush(['VAPID' => $vapid]);
        $report = $webPush->sendOneNotification(
            $subscription->subscription(),
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        if ($report->isSuccess()) {
            return;
        }

        if ($report->isSubscriptionExpired()) {
            throw new PushSubscriptionExpired;
        }

        Log::warning('notification.push_delivery_failed', [
            'subscription_id' => $subscription->id,
            'reason' => 'provider_delivery_failed',
        ]);
        throw new RuntimeException('Push delivery failed.');
    }
}
