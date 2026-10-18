<div class="admin-page admin-page--webhooks ag-list-page">
    <x-ag.page-header :heading="__('admin.webhooks.title')" :lede="__('admin.webhooks.lede')" />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->has('delivery'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ $errors->first('delivery') }}</p>
    @endif

    <div class="ag-form-stack">
        @can('webhooks.manage')
        <x-ag.card>
            <x-ag.card.header>
                <x-ag.card.title>{{ $editingId ? __('admin.webhooks.edit') : __('admin.webhooks.create') }}</x-ag.card.title>
                <x-ag.card.description>{{ __('admin.webhooks.form_lede') }}</x-ag.card.description>
            </x-ag.card.header>
            <x-ag.card.content>
                <form class="ag-form" wire:submit="save">
                    <div class="ag-grid ag-grid--2">
                        <div class="ag-field">
                            <label class="ag-field__label" for="webhook-name">{{ __('admin.webhooks.name') }}</label>
                            <input id="webhook-name" class="ag-input" type="text" wire:model="name" autocomplete="off">
                            @error('name') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="ag-field">
                            <label class="ag-field__label" for="webhook-destination">{{ __('admin.webhooks.destination') }}</label>
                            <select id="webhook-destination" class="ag-select" wire:model="destination">
                                <option value="http">{{ __('admin.webhooks.destination_http') }}</option>
                                <option value="discord">{{ __('admin.webhooks.destination_discord') }}</option>
                            </select>
                            @error('destination') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="ag-field">
                            <label class="ag-field__label" for="webhook-url">{{ __('admin.webhooks.url') }}</label>
                            <input id="webhook-url" class="ag-input" type="url" wire:model="url" placeholder="https://example.test/webhooks/agovena" autocomplete="url">
                            <p class="ag-field__help">{{ __('admin.webhooks.url_help') }}</p>
                            @error('url') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="ag-field">
                            <label class="ag-field__label" for="webhook-secret">{{ __('admin.webhooks.secret') }}</label>
                            <input id="webhook-secret" class="ag-input" type="password" wire:model="secret" autocomplete="new-password">
                            <p class="ag-field__help">{{ $editingId ? __('admin.webhooks.secret_edit_help') : __('admin.webhooks.secret_help') }}</p>
                            @error('secret') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <fieldset class="ag-fieldset">
                        <legend class="ag-fieldset__legend">{{ __('admin.webhooks.events') }}</legend>
                        <div class="ag-check-grid">
                            @foreach ($eventCatalog as $event)
                                <label class="ag-check">
                                    <input type="checkbox" wire:model="events" value="{{ $event }}">
                                    <span>{{ $event }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('events') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </fieldset>

                    <div class="ag-form__actions">
                        <button type="submit" class="ag-btn ag-btn--primary">{{ $editingId ? __('admin.webhooks.save') : __('admin.webhooks.create') }}</button>
                        @if ($editingId)
                            <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelEdit">{{ __('common.cancel') }}</button>
                        @endif
                    </div>
                </form>
            </x-ag.card.content>
        </x-ag.card>
        @endcan

        <x-ag.card>
            <x-ag.card.header>
                <x-ag.card.title>{{ __('admin.webhooks.endpoints') }}</x-ag.card.title>
                <x-ag.card.description>{{ __('admin.webhooks.endpoints_lede') }}</x-ag.card.description>
            </x-ag.card.header>
            <x-ag.card.content>
                @if ($endpoints->isEmpty())
                    <div class="ag-empty ag-empty--soft" role="status">
                        <p class="ag-empty__text">{{ __('admin.webhooks.empty') }}</p>
                    </div>
                @else
                    <div class="ag-table-wrap ag-table-wrap--mobile-cards" role="region" tabindex="0" aria-label="{{ __('admin.webhooks.endpoints') }}">
                        <table class="ag-table ag-table--mobile-cards ag-table--webhooks c-system-table">
                            <caption class="visually-hidden">{{ __('admin.webhooks.endpoints') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('admin.webhooks.name') }}</th>
                                    <th scope="col">{{ __('admin.webhooks.destination') }}</th>
                                    <th scope="col">{{ __('admin.webhooks.url') }}</th>
                                    <th scope="col">{{ __('admin.webhooks.events') }}</th>
                                    <th scope="col">{{ __('admin.webhooks.status') }}</th>
                                    <th scope="col">{{ __('admin.webhooks.deliveries') }}</th>
                                    @can('webhooks.manage')
                                        <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                                    @endcan
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($endpoints as $endpoint)
                                    <tr wire:key="webhook-endpoint-{{ $endpoint->id }}">
                                        <td data-label="{{ __('admin.webhooks.name') }}">
                                            <span class="ag-table__name">{{ $endpoint->name }}</span>
                                        </td>
                                        <td data-label="{{ __('admin.webhooks.destination') }}">{{ $endpoint->destination === 'discord' ? __('admin.webhooks.destination_discord') : __('admin.webhooks.destination_http') }}</td>
                                        @php($endpointHost = parse_url($endpoint->url, PHP_URL_HOST))
                                        <td data-label="{{ __('admin.webhooks.url') }}"><code>{{ is_string($endpointHost) && $endpointHost !== '' ? 'https://'.$endpointHost : __('common.em_dash') }}</code></td>
                                        <td data-label="{{ __('admin.webhooks.events') }}">{{ implode(', ', $endpoint->events) }}</td>
                                        <td data-label="{{ __('admin.webhooks.status') }}">
                                            <span @class(['ag-badge', 'ag-badge--success' => $endpoint->active, 'ag-badge--muted' => ! $endpoint->active])>
                                                {{ $endpoint->active ? __('admin.webhooks.active') : __('admin.webhooks.inactive') }}
                                            </span>
                                        </td>
                                        <td data-label="{{ __('admin.webhooks.deliveries') }}">{{ $endpoint->deliveries_count }}</td>
                                        @can('webhooks.manage')
                                        <td class="ag-table__actions" data-label="{{ __('common.actions') }}">
                                            <div class="ag-row-actions">
                                                <button type="button" class="ag-icon-btn" wire:click="edit({{ $endpoint->id }})" title="{{ __('common.edit') }}" aria-label="{{ __('common.edit') }}: {{ $endpoint->name }}">
                                                    <x-ag.icon name="pencil" :size="16" />
                                                    <span class="ag-table__action-label">{{ __('common.edit') }}</span>
                                                </button>
                                                <button type="button" class="ag-icon-btn" wire:click="toggleActive({{ $endpoint->id }})" title="{{ $endpoint->active ? __('admin.webhooks.disable') : __('admin.webhooks.enable') }}" aria-label="{{ $endpoint->active ? __('admin.webhooks.disable') : __('admin.webhooks.enable') }}: {{ $endpoint->name }}">
                                                    <x-ag.icon :name="$endpoint->active ? 'x' : 'check'" :size="16" />
                                                    <span class="ag-table__action-label">{{ $endpoint->active ? __('admin.webhooks.disable') : __('admin.webhooks.enable') }}</span>
                                                </button>
                                                <button type="button" class="ag-icon-btn ag-icon-btn--danger" wire:click="delete({{ $endpoint->id }})" wire:confirm="{{ __('admin.webhooks.delete_confirm') }}" title="{{ __('common.delete') }}" aria-label="{{ __('common.delete') }}: {{ $endpoint->name }}">
                                                    <x-ag.icon name="trash" :size="16" />
                                                    <span class="ag-table__action-label">{{ __('common.delete') }}</span>
                                                </button>
                                            </div>
                                        </td>
                                        @endcan
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if ($endpoints->hasPages())
                    <div class="ag-pagination">{{ $endpoints->links() }}</div>
                @endif
            </x-ag.card.content>
        </x-ag.card>

        <x-ag.card>
            <x-ag.card.header>
                <x-ag.card.title>{{ __('admin.webhooks.delivery_log') }}</x-ag.card.title>
                <x-ag.card.description>{{ __('admin.webhooks.delivery_log_lede') }}</x-ag.card.description>
            </x-ag.card.header>
            <x-ag.card.content>
                @if ($deliveries->isEmpty())
                    <div class="ag-empty ag-empty--soft" role="status">
                        <p class="ag-empty__text">{{ __('admin.webhooks.no_deliveries') }}</p>
                    </div>
                @else
                    <div class="ag-table-wrap ag-table-wrap--mobile-cards" role="region" tabindex="0" aria-label="{{ __('admin.webhooks.delivery_log') }}">
                        <table class="ag-table ag-table--mobile-cards ag-table--webhooks c-system-table">
                            <caption class="visually-hidden">{{ __('admin.webhooks.delivery_log') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('admin.webhooks.delivery') }}</th>
                                    <th scope="col">{{ __('admin.webhooks.endpoint') }}</th>
                                    <th scope="col">{{ __('admin.webhooks.event') }}</th>
                                    <th scope="col">{{ __('admin.webhooks.status') }}</th>
                                    <th scope="col">{{ __('admin.webhooks.attempts') }}</th>
                                    @can('webhooks.manage')
                                        <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                                    @endcan
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($deliveries as $delivery)
                                    <tr wire:key="webhook-delivery-{{ $delivery->id }}">
                                        <td data-label="{{ __('admin.webhooks.delivery') }}"><code>{{ $delivery->delivery_id }}</code></td>
                                        <td data-label="{{ __('admin.webhooks.endpoint') }}">{{ $delivery->endpoint?->name ?? __('common.em_dash') }}</td>
                                        <td data-label="{{ __('admin.webhooks.event') }}">{{ $delivery->event_type }}</td>
                                        <td data-label="{{ __('admin.webhooks.status') }}">
                                            <span @class([
                                                'ag-badge',
                                                'ag-badge--success' => $delivery->status === 'delivered',
                                                'ag-badge--warning' => in_array($delivery->status, ['queued', 'retrying'], true),
                                                'ag-badge--danger' => $delivery->status === 'failed',
                                                'ag-badge--muted' => ! in_array($delivery->status, ['delivered', 'queued', 'retrying', 'failed'], true),
                                            ])>{{ $delivery->status }}</span>
                                        </td>
                                        <td data-label="{{ __('admin.webhooks.attempts') }}">{{ $delivery->attempt_count }}</td>
                                        @can('webhooks.manage')
                                        <td class="ag-table__actions" data-label="{{ __('common.actions') }}">
                                            @if (in_array($delivery->status, ['failed', 'retrying'], true))
                                                <button type="button" class="ag-icon-btn" wire:click="retryDelivery({{ $delivery->id }})" title="{{ __('admin.webhooks.retry') }}" aria-label="{{ __('admin.webhooks.retry') }}: {{ $delivery->delivery_id }}">
                                                    <x-ag.icon name="rotate-ccw" :size="16" />
                                                    <span class="ag-table__action-label">{{ __('admin.webhooks.retry') }}</span>
                                                </button>
                                            @endif
                                        </td>
                                        @endcan
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if ($deliveries->hasPages())
                    <div class="ag-pagination">{{ $deliveries->links() }}</div>
                @endif
            </x-ag.card.content>
        </x-ag.card>
    </div>
</div>
