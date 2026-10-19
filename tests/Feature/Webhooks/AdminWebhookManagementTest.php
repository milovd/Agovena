<?php

declare(strict_types=1);

use App\Agovena\Webhooks\DeliverWebhook;
use App\Livewire\Admin\Webhooks\Index;
use App\Models\AuditLog;
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
        ->set('url', 'https://example.com/orders')
        ->set('secret', '[REDACTED]')
        ->set('events', ['order.created', 'order.paid'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Order listener')
        ->assertSee('ag-form-stack', false)
        ->assertSee('ag-card__header', false)
        ->assertSee('ag-icon-btn', false)
        ->assertSee('ag-table--webhooks', false)
        ->assertDontSee('ag-btn--small', false);

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
        'url' => 'https://example.com/orders',
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

it('paginates endpoint and delivery histories independently instead of silently truncating them', function (): void {
    $viewer = $this->createStaff([], ['webhooks.view']);
    $firstEndpointId = $lastEndpointId = $firstDeliveryId = $lastDeliveryId = null;
    foreach (range(1, 22) as $number) {
        $endpoint = WebhookEndpoint::query()->create([
            'name' => sprintf('History endpoint %02d', $number),
            'url' => 'https://example.test/webhooks',
            'secret' => 'test-only-placeholder',
            'events' => ['order.created'],
            'active' => true,
        ]);
        $delivery = WebhookDelivery::query()->create([
            'delivery_id' => sprintf('history-delivery-%02d', $number),
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => 'order.created',
            'payload' => ['type' => 'order.created'],
            'status' => 'failed',
            'attempt_count' => 1,
        ]);
        if ($number === 1) {
            $firstEndpointId = $endpoint->id;
            $firstDeliveryId = $delivery->id;
        }
        if ($number === 22) {
            $lastEndpointId = $endpoint->id;
            $lastDeliveryId = $delivery->id;
        }
    }

    Livewire::actingAs($viewer)
        ->test(Index::class)
        ->assertSee('wire:key="webhook-endpoint-'.$lastEndpointId.'"', false)
        ->assertDontSee('wire:key="webhook-endpoint-'.$firstEndpointId.'"', false)
        ->assertSee('wire:key="webhook-delivery-'.$lastDeliveryId.'"', false)
        ->assertDontSee('wire:key="webhook-delivery-'.$firstDeliveryId.'"', false)
        ->call('gotoPage', 2, 'endpointsPage')
        ->assertSee('wire:key="webhook-endpoint-'.$firstEndpointId.'"', false)
        ->assertDontSee('wire:key="webhook-endpoint-'.$lastEndpointId.'"', false)
        ->assertSee('wire:key="webhook-delivery-'.$lastDeliveryId.'"', false)
        ->call('gotoPage', 2, 'deliveriesPage')
        ->assertSee('wire:key="webhook-delivery-'.$firstDeliveryId.'"', false)
        ->assertDontSee('wire:key="webhook-delivery-'.$lastDeliveryId.'"', false)
        ->assertSee('wire:key="webhook-endpoint-'.$firstEndpointId.'"', false);

    Livewire::actingAs($viewer);
    Livewire::withQueryParams(['endpointsPage' => 2, 'deliveriesPage' => 2])
        ->test(Index::class)
        ->assertSee('wire:key="webhook-endpoint-'.$firstEndpointId.'"', false)
        ->assertDontSee('wire:key="webhook-endpoint-'.$lastEndpointId.'"', false)
        ->assertSee('wire:key="webhook-delivery-'.$firstDeliveryId.'"', false)
        ->assertDontSee('wire:key="webhook-delivery-'.$lastDeliveryId.'"', false);
});

it('describes the paginated delivery history without a fixed last-fifty limit', function (): void {
    foreach (['en', 'nl'] as $locale) {
        $lede = __('admin.webhooks.delivery_log_lede', [], $locale);
        expect($lede)->not->toBe('admin.webhooks.delivery_log_lede')
            ->and($lede)->not->toContain('50');
    }
});

it('recovers out-of-range webhook pages without claiming the histories are empty', function (): void {
    $viewer = $this->createStaff([], ['webhooks.view']);
    $endpoint = WebhookEndpoint::query()->create([
        'name' => 'Persisted endpoint',
        'url' => 'https://example.test/webhooks',
        'secret' => 'test-only-placeholder',
        'events' => ['order.created'],
        'active' => true,
    ]);
    $delivery = WebhookDelivery::query()->create([
        'delivery_id' => 'persisted-delivery',
        'webhook_endpoint_id' => $endpoint->id,
        'event_type' => 'order.created',
        'payload' => ['type' => 'order.created'],
        'status' => 'failed',
        'attempt_count' => 1,
    ]);

    Livewire::actingAs($viewer)
        ->test(Index::class)
        ->call('gotoPage', 999, 'endpointsPage')
        ->call('gotoPage', 999, 'deliveriesPage')
        ->assertSee('wire:key="webhook-endpoint-'.$endpoint->id.'"', false)
        ->assertSee('wire:key="webhook-delivery-'.$delivery->id.'"', false)
        ->assertDontSee(__('admin.webhooks.empty'))
        ->assertDontSee(__('admin.webhooks.no_deliveries'));
});

it('returns to a populated endpoint page after deleting its last row', function (): void {
    $manager = $this->createStaff([], ['webhooks.view', 'webhooks.manage']);
    $oldestId = null;
    $newestId = null;
    foreach (range(1, 21) as $number) {
        $endpoint = WebhookEndpoint::query()->create([
            'name' => sprintf('Deletion endpoint %02d', $number),
            'url' => 'https://example.test/webhooks',
            'secret' => 'test-only-placeholder',
            'events' => ['order.created'],
            'active' => true,
        ]);
        $oldestId ??= $endpoint->id;
        $newestId = $endpoint->id;
    }

    Livewire::actingAs($manager)
        ->test(Index::class)
        ->call('gotoPage', 2, 'endpointsPage')
        ->assertSee('wire:key="webhook-endpoint-'.$oldestId.'"', false)
        ->call('deleteEndpoint', $oldestId)
        ->assertSee('wire:key="webhook-endpoint-'.$newestId.'"', false)
        ->assertDontSee(__('admin.webhooks.empty'));
    expect(WebhookEndpoint::query()->count())->toBe(20);
});

it('keeps endpoint and failed delivery facts visible without webhook management controls', function (): void {
    $staff = $this->createStaff([], ['webhooks.view']);
    $endpoint = WebhookEndpoint::query()->create([
        'name' => 'Read-only endpoint',
        'url' => 'https://example.com/readonly',
        'secret' => 'test-only-placeholder',
        'events' => ['order.created'],
        'active' => true,
    ]);
    $delivery = WebhookDelivery::query()->create([
        'delivery_id' => 'delivery-viewer',
        'webhook_endpoint_id' => $endpoint->id,
        'event_type' => 'order.created',
        'payload' => ['type' => 'order.created'],
        'status' => 'failed',
        'attempt_count' => 1,
    ]);

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->assertSee('Read-only endpoint')
        ->assertSee('delivery-viewer')
        ->assertDontSee('wire:submit="save"', false)
        ->assertDontSee('wire:click="edit(', false)
        ->assertDontSee('wire:click="toggleActive(', false)
        ->assertDontSee('wire:click="deleteEndpoint(', false)
        ->assertDontSee('wire:click="retryDelivery(', false);

    Livewire::actingAs($staff)->test(Index::class)->call('retryDelivery', $delivery->id)->assertForbidden();
    Livewire::actingAs($staff)->test(Index::class)->call('edit', $endpoint->id)->assertForbidden();
    Livewire::actingAs($staff)->test(Index::class)->call('toggleActive', $endpoint->id)->assertForbidden();
    Livewire::actingAs($staff)->test(Index::class)->call('deleteEndpoint', $endpoint->id)->assertForbidden();
    Livewire::actingAs($staff)->test(Index::class)->call('save')->assertForbidden();
    expect($delivery->fresh()->status)->toBe('failed')
        ->and($endpoint->fresh()->active)->toBeTrue();
});

it('keeps webhook destination paths out of viewer markup and new audit records', function (): void {
    $manager = $this->createStaff([], ['webhooks.view', 'webhooks.manage']);
    $url = 'https://example.com/private-path?reference=test-only-sentinel';

    Livewire::actingAs($manager)->test(Index::class)
        ->set('name', 'Private endpoint')
        ->set('url', $url)
        ->set('secret', 'test-only-placeholder')
        ->set('events', ['order.created'])
        ->call('save')
        ->assertHasNoErrors();

    $endpoint = WebhookEndpoint::query()->sole();
    expect($endpoint->url)->toBe($url);
    expect(json_encode(AuditLog::query()->where('action', 'webhook.endpoint_created')->sole()->properties))
        ->not->toContain('test-only-sentinel');

    Livewire::actingAs($manager)->test(Index::class)
        ->call('edit', $endpoint->id)
        ->assertSet('editingId', $endpoint->id)
        ->assertSet('url', $url);

    $viewer = $this->createStaff([], ['webhooks.view']);
    Livewire::actingAs($viewer)->test(Index::class)
        ->assertSee('example.com')
        ->assertDontSee('private-path')
        ->assertDontSee('test-only-sentinel')
        ->assertDontSee('wire:submit="save"', false);
});

it('keeps destination URLs out of update toggle and delete audit events', function (): void {
    $manager = $this->createStaff([], ['webhooks.view', 'webhooks.manage']);
    $endpoint = WebhookEndpoint::query()->create([
        'name' => 'Private endpoint',
        'url' => 'https://example.com/old-private-path',
        'secret' => 'test-only-placeholder',
        'events' => ['order.created'],
        'active' => true,
    ]);

    Livewire::actingAs($manager)->test(Index::class)
        ->call('edit', $endpoint->id)
        ->set('url', 'https://example.com/new-private-path?reference=test-only-sentinel')
        ->call('save')
        ->assertHasNoErrors()
        ->call('toggleActive', $endpoint->id)
        ->call('deleteEndpoint', $endpoint->id);

    expect(WebhookEndpoint::query()->count())->toBe(0);
    foreach (AuditLog::query()->where('action', 'like', 'webhook.endpoint_%')->get() as $entry) {
        $recorded = json_encode([$entry->properties, $entry->before, $entry->after]);
        expect($recorded)->not->toContain('private-path')->not->toContain('test-only-sentinel');
    }
});

it('does not expose webhook management to staff without the view permission', function (): void {
    $staff = $this->createStaff([], []);
    $this->actingAs($staff);

    Livewire::test(Index::class)->assertForbidden();
});
