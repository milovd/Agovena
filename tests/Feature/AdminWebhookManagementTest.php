<?php

declare(strict_types=1);

use App\Agovena\Webhooks\DeliverWebhook;
use App\Livewire\Admin\Webhooks\Index;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('allows authorized staff to create and manage outbound webhook endpoints', function (): void {
    $staff = $this->createStaff();
    $this->actingAs($staff);

    Livewire::test(Index::class)
        ->set('name', 'Order listener')
        ->set('destination', 'discord')
        ->set('url', 'https://hooks.example.test/orders')
        ->set('secret', '[REDACTED]')
        ->set('events', ['order.created', 'order.paid'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Order listener');

    $endpoint = WebhookEndpoint::query()->sole();
    expect($endpoint->destination)->toBe('discord')
        ->and($endpoint->events)->toBe(['order.created', 'order.paid'])
        ->and($endpoint->secret)->toBe('[REDACTED]')
        ->and($endpoint->getRawOriginal('secret'))->not->toBe('[REDACTED]');
});

it('rejects insecure outbound webhook endpoints', function (): void {
    $staff = $this->createStaff();
    $this->actingAs($staff);

    Livewire::test(Index::class)
        ->set('name', 'Unsafe')
        ->set('url', 'http://127.0.0.1/hook')
        ->set('secret', '[REDACTED]')
        ->set('events', ['order.created'])
        ->call('save')
        ->assertHasErrors(['url']);

    expect(WebhookEndpoint::query()->count())->toBe(0);
});

it('queues failed deliveries for a controlled retry', function (): void {
    Queue::fake();
    $staff = $this->createStaff();
    $this->actingAs($staff);
    $endpoint = WebhookEndpoint::query()->create([
        'name' => 'Orders',
        'url' => 'https://hooks.example.test/orders',
        'secret' => '[REDACTED]',
        'events' => ['order.created'],
        'active' => true,
    ]);
    $delivery = WebhookDelivery::query()->create([
        'delivery_id' => 'delivery-retry',
        'webhook_endpoint_id' => $endpoint->id,
        'event_type' => 'order.created',
        'payload' => ['type' => 'order.created'],
        'status' => 'failed',
        'attempt_count' => 5,
        'last_error' => 'temporary failure',
    ]);

    Livewire::test(Index::class)
        ->call('retryDelivery', $delivery->id)
        ->assertHasNoErrors();

    expect($delivery->fresh()->status)->toBe('queued')
        ->and($delivery->fresh()->last_error)->toBeNull();
    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->deliveryId === $delivery->id);
});

it('does not expose webhook management to staff without the view permission', function (): void {
    $staff = $this->createStaff([], []);
    $this->actingAs($staff);

    Livewire::test(Index::class)->assertForbidden();
});
