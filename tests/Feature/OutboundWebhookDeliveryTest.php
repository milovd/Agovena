<?php

declare(strict_types=1);

use App\Agovena\Webhooks\DeliverWebhook;
use App\Agovena\Webhooks\WebhookEventPublisher;
use App\Events\OrderCreated;
use App\Models\Order;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('publishes only to subscribed active webhook endpoints', function (): void {
    Queue::fake();
    WebhookEndpoint::query()->create([
        'name' => 'Orders',
        'url' => 'https://hooks.example.test/orders',
        'secret' => '[REDACTED]',
        'events' => ['order.created'],
        'active' => true,
    ]);
    WebhookEndpoint::query()->create([
        'name' => 'Payments',
        'url' => 'https://hooks.example.test/payments',
        'secret' => '[REDACTED]',
        'events' => ['payment.recorded'],
        'active' => true,
    ]);

    app(WebhookEventPublisher::class)->publish('order.created', ['order_id' => 10]);

    expect(WebhookDelivery::query()->count())->toBe(1)
        ->and(WebhookDelivery::query()->firstOrFail()->event_type)->toBe('order.created');
    Queue::assertPushed(DeliverWebhook::class, 1);
});

test('domain events create outbound webhook deliveries', function (): void {
    Queue::fake();
    WebhookEndpoint::query()->create([
        'name' => 'All events',
        'url' => 'https://hooks.example.test/all',
        'secret' => '[REDACTED]',
        'events' => ['order.created'],
        'active' => true,
    ]);

    $order = Order::factory()->create();
    OrderCreated::dispatch($order);

    $delivery = WebhookDelivery::query()->firstOrFail();
    expect($delivery->event_type)->toBe('order.created')
        ->and($delivery->payload['data']['order_id'])->toBe($order->id)
        ->and($delivery->payload['data']['total'])->toBe($order->total_amount);
});

it('delivers signed webhook payloads and records the response', function (): void {
    Queue::fake();
    Http::fake([
        'https://hooks.example.test/*' => Http::response(['accepted' => true], 202),
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

    (new DeliverWebhook($delivery->id))->handle();

    $delivery->refresh();
    expect($delivery->status)->toBe('delivered')
        ->and($delivery->attempt_count)->toBe(1)
        ->and($delivery->response_status)->toBe(202)
        ->and($delivery->delivered_at)->not->toBeNull();

    Http::assertSent(function (HttpRequest $request) use ($endpoint): bool {
        return $request->url() === $endpoint->url
            && $request->header('X-Agovena-Event') === ['order.created']
            && $request->header('X-Agovena-Delivery') !== []
            && $request->header('X-Agovena-Signature') !== []
            && $request->data()['data']['order_id'] === 10;
    });
});

it('records a retryable delivery failure without exposing the endpoint secret', function (): void {
    Queue::fake();
    Http::fake([
        'https://hooks.example.test/*' => Http::response(['error' => 'temporary'], 503),
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

    (new DeliverWebhook($delivery->id))->handle();

    $delivery->refresh();
    expect($delivery->status)->toBe('retrying')
        ->and($delivery->attempt_count)->toBe(1)
        ->and($delivery->last_error)->not->toContain('[REDACTED]');
});
