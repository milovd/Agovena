<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Webhooks;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Audit\AuditLogger;
use App\Agovena\Webhooks\DeliverWebhook;
use App\Agovena\Webhooks\WebhookEventCatalog;
use App\Agovena\Webhooks\WebhookUrlValidator;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class Index extends Component
{
    use AuthorizesRequests;

    public string $name = '';

    public string $destination = 'http';

    public string $url = '';

    public string $secret = '';

    /** @var list<string> */
    public array $events = [];

    public ?int $editingId = null;

    public function mount(WebhookEventCatalog $catalog): void
    {
        $this->authorize('webhooks.view');
        $this->events = $catalog->all();
    }

    public function save(WebhookEventCatalog $catalog, AuditLogger $audit): void
    {
        $this->authorize('webhooks.manage');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'destination' => ['required', 'string', Rule::in(['http', 'discord'])],
            'url' => ['required', 'url:https', 'max:2000'],
            'secret' => [$this->editingId === null ? 'required' : 'nullable', 'string', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in($catalog->all())],
        ]);

        if (! WebhookUrlValidator::isAllowed($data['url'])) {
            $this->addError('url', __('admin.webhooks.validation_url'));

            return;
        }

        if ($this->editingId !== null) {
            $endpoint = WebhookEndpoint::query()->findOrFail($this->editingId);
            $before = [
                'name' => $endpoint->name,
                'destination' => $endpoint->destination,
                'url' => $endpoint->url,
                'events' => $endpoint->events,
                'active' => $endpoint->active,
            ];
            $endpoint->name = $data['name'];
            $endpoint->destination = $data['destination'];
            $endpoint->url = $data['url'];
            $endpoint->events = array_values($data['events']);
            if ($data['secret'] !== null && $data['secret'] !== '') {
                $endpoint->secret = $data['secret'];
            }
            $endpoint->save();
            $audit->logChange('webhook.endpoint_updated', $endpoint, $before, [
                'name' => $endpoint->name,
                'destination' => $endpoint->destination,
                'url' => $endpoint->url,
                'events' => $endpoint->events,
                'active' => $endpoint->active,
            ]);
            session()->flash('status', __('admin.webhooks.updated'));
        } else {
            $endpoint = WebhookEndpoint::query()->create([
                'name' => $data['name'],
                'destination' => $data['destination'],
                'url' => $data['url'],
                'secret' => $data['secret'],
                'events' => array_values($data['events']),
                'active' => true,
            ]);
            $audit->log('webhook.endpoint_created', $endpoint, [
                'name' => $endpoint->name,
                'destination' => $endpoint->destination,
                'url' => $endpoint->url,
                'events' => $endpoint->events,
            ]);
            session()->flash('status', __('admin.webhooks.created'));
        }

        $this->resetForm($catalog);
    }

    public function edit(int $id): void
    {
        $this->authorize('webhooks.manage');
        $endpoint = WebhookEndpoint::query()->findOrFail($id);
        $this->editingId = $endpoint->id;
        $this->name = $endpoint->name;
        $this->destination = $endpoint->destination ?? 'http';
        $this->url = $endpoint->url;
        $this->secret = '';
        $this->events = $endpoint->events;
    }

    public function cancelEdit(WebhookEventCatalog $catalog): void
    {
        $this->authorize('webhooks.manage');
        $this->resetForm($catalog);
    }

    public function toggleActive(int $id, AuditLogger $audit): void
    {
        $this->authorize('webhooks.manage');
        $endpoint = WebhookEndpoint::query()->findOrFail($id);
        $endpoint->active = ! $endpoint->active;
        $endpoint->save();
        $audit->log('webhook.endpoint_'.($endpoint->active ? 'enabled' : 'disabled'), $endpoint, [
            'active' => $endpoint->active,
        ]);
    }

    public function delete(int $id, AuditLogger $audit): void
    {
        $this->authorize('webhooks.manage');
        $endpoint = WebhookEndpoint::query()->findOrFail($id);
        $audit->log('webhook.endpoint_deleted', $endpoint, [
            'name' => $endpoint->name,
            'url' => $endpoint->url,
        ]);
        $endpoint->delete();
    }

    public function retryDelivery(int $id, AuditLogger $audit): void
    {
        $this->authorize('webhooks.manage');
        $delivery = WebhookDelivery::query()->with('endpoint')->findOrFail($id);
        if ($delivery->endpoint === null || ! $delivery->endpoint->active) {
            $this->addError('delivery', __('admin.webhooks.retry_inactive'));

            return;
        }

        $delivery->update([
            'status' => 'queued',
            'last_error' => null,
            'failure_code' => null,
            'failed_at' => null,
            'dead_lettered_at' => null,
            'next_attempt_at' => null,
        ]);
        DeliverWebhook::dispatch($delivery->id);
        $audit->log('webhook.delivery_retried', $delivery, [
            'delivery_id' => $delivery->delivery_id,
            'event_type' => $delivery->event_type,
        ]);
    }

    public function render(AdminRegistrar $admin, WebhookEventCatalog $catalog)
    {
        return view('livewire.admin.webhooks.index', [
            'endpoints' => WebhookEndpoint::query()->withCount('deliveries')->latest('id')->get(),
            'deliveries' => WebhookDelivery::query()->with('endpoint')->latest('id')->limit(50)->get(),
            'eventCatalog' => $catalog->all(),
        ])->layout('layouts.admin', [
            'title' => __('admin.webhooks.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function resetForm(WebhookEventCatalog $catalog): void
    {
        $this->reset(['name', 'destination', 'url', 'secret', 'editingId']);
        $this->events = $catalog->all();
        $this->resetValidation();
    }
}
