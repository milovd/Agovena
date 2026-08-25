<?php

declare(strict_types=1);

namespace App\Agovena\Webhooks;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

final class WebhookEventPublisher
{
    /** @param array<string, mixed> $data */
    public function publish(string $eventType, array $data): void
    {
        WebhookEndpoint::query()
            ->where('active', true)
            ->get()
            ->each(function (WebhookEndpoint $endpoint) use ($eventType, $data): void {
                $events = $endpoint->events;
                if (! in_array('*', $events, true) && ! in_array($eventType, $events, true)) {
                    return;
                }

                $delivery = WebhookDelivery::query()->create([
                    'delivery_id' => (string) Str::uuid(),
                    'webhook_endpoint_id' => $endpoint->id,
                    'event_type' => $eventType,
                    'payload' => [
                        'id' => (string) Str::uuid(),
                        'type' => $eventType,
                        'created_at' => now()->toIso8601String(),
                        'data' => $data,
                    ],
                    'status' => 'queued',
                ]);

                DeliverWebhook::dispatch($delivery->id)->afterCommit();
            });
    }
}
