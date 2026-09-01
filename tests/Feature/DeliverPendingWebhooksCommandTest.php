<?php

declare(strict_types=1);

use App\Agovena\Webhooks\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

it('queues due retries and recovers stale deliveries without sending future retries early', function (): void {
    Queue::fake();
    $endpoint = WebhookEndpoint::query()->create([
        'name' => 'Orders',
        'url' => 'https://example.com/receiver',
        'secret' => '[REDACTED]',
        'events' => ['order.created'],
        'active' => true,
    ]);
    $due = WebhookDelivery::query()->create([
        'delivery_id' => 'delivery-due',
        'webhook_endpoint_id' => $endpoint->id,
        'event_type' => 'order.created',
        'payload' => ['type' => 'order.created'],
        'status' => 'retrying',
        'attempt_count' => 1,
        'next_attempt_at' => now()->subSecond(),
    ]);
    $stale = WebhookDelivery::query()->create([
        'delivery_id' => 'delivery-stale',
        'webhook_endpoint_id' => $endpoint->id,
        'event_type' => 'order.created',
        'payload' => ['type' => 'order.created'],
        'status' => 'in_progress',
        'attempt_count' => 1,
    ]);
    $stale->forceFill(['updated_at' => now()->subMinutes(11)])->saveQuietly();
    $future = WebhookDelivery::query()->create([
        'delivery_id' => 'delivery-future',
        'webhook_endpoint_id' => $endpoint->id,
        'event_type' => 'order.created',
        'payload' => ['type' => 'order.created'],
        'status' => 'retrying',
        'attempt_count' => 1,
        'next_attempt_at' => now()->addMinute(),
    ]);

    expect(Artisan::call('agovena:deliver-webhooks', ['--limit' => 10]))->toBe(0);

    expect(Queue::pushed(DeliverWebhook::class))->toHaveCount(2)
        ->and($due->fresh()->status)->toBe('retrying')
        ->and($stale->fresh()->status)->toBe('retrying')
        ->and($stale->fresh()->next_attempt_at)->not->toBeNull()
        ->and($future->fresh()->status)->toBe('retrying')
        ->and($future->fresh()->next_attempt_at->isFuture())->toBeTrue();
});
