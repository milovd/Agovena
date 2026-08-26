<?php

declare(strict_types=1);

use App\Agovena\Webhooks\DeliverWebhook;
use App\Agovena\Webhooks\WebhookEventPublisher;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('moves an exhausted webhook delivery to the dead-letter state', function (): void {
    Queue::fake();
    Http::fake([
        'https://hooks.example.test/*' => Http::response(['error' => 'permanent'], 503),
    ]);
    $endpoint = WebhookEndpoint::query()->create([
        'name' => 'Orders',
        'url' => 'https://hooks.example.test/orders',
        'secret' => '[REDACTED]',
        'events' => ['order.created'],
        'active' => true,
    ]);

    app(WebhookEventPublisher::class)->publish('order.created', ['order_id' => 10]);
    $delivery = WebhookDelivery::query()->firstOrFail();
    $job = new DeliverWebhook($delivery->id);

    for ($attempt = 0; $attempt < $job->tries; $attempt++) {
        $job->handle();
    }

    expect($delivery->fresh()->status)->toBe('dead_letter')
        ->and($delivery->fresh()->dead_lettered_at)->not->toBeNull();
});
