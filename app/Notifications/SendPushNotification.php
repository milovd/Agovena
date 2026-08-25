<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Agovena\Notifications\PushSubscriptionExpired;
use App\Agovena\Notifications\PushTransport;
use App\Models\PushSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

final class SendPushNotification implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $subscriptionId,
        /** @var array<string, mixed> */
        public array $payload,
    ) {}

    public function handle(PushTransport $transport): void
    {
        $subscription = PushSubscription::query()->find($this->subscriptionId);
        if ($subscription === null) {
            return;
        }

        try {
            $transport->send($subscription, $this->payload);
            $subscription->update(['last_used_at' => now()]);
        } catch (PushSubscriptionExpired) {
            $subscription->delete();
        } catch (Throwable) {
            throw new \RuntimeException('Push notification delivery failed.');
        }
    }
}
