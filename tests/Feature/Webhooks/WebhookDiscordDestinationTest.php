<?php

declare(strict_types=1);

use App\Agovena\Webhooks\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('formats a discord destination without changing generic webhook delivery', function (): void {
    Http::fake([
        'https://discord.com/*' => Http::response(['ok' => true], 204),
    ]);

    $endpoint = WebhookEndpoint::query()->create([
        'name' => 'Discord alerts',
        'destination' => 'discord',
        'url' => 'https://discord.com/api/webhooks/123/abc',
        'secret' => 'test-secret',
        'events' => ['order.paid'],
        'active' => true,
    ]);
    $delivery = WebhookDelivery::query()->create([
        'delivery_id' => (string) Str::uuid(),
        'webhook_endpoint_id' => $endpoint->id,
        'event_type' => 'order.paid',
        'payload' => ['type' => 'order.paid', 'data' => ['order' => 'ORD-1']],
        'status' => 'queued',
    ]);

    app(DeliverWebhook::class, ['deliveryId' => $delivery->id])->handle();

    Http::assertSent(function ($request): bool {
        $json = $request->data();

        return isset($json['embeds'][0]['title'])
            && $json['embeds'][0]['title'] === 'Agovena event: order.paid';
    });
    expect($delivery->fresh()->status)->toBe('delivered');
});
