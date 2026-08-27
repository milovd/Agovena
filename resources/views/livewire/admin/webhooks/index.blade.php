<div class="admin-page">
    <x-ag.page-header :heading="__('admin.webhooks.title')" :lede="__('admin.webhooks.lede')" />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->has('delivery'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ $errors->first('delivery') }}</p>
    @endif

    <div class="ag-form-stack">
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
                    <div class="ag-table-wrap">
                        <table class="ag-table">
                            <caption class="visually-hidden">{{ __('admin.webhooks.endpoints') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('admin.webhooks.name') }}</th>
                                    <th scope="col">{{ __('admin.webhooks.destination') }}</th>
                                    <th scope="col">{{ __('admin.webhooks.url') }}</th>
                                    <th scope="col">{{ __('admin.webhooks.events') }}</th>
                                    <th scope="col">{{ __('admin.webhooks.status') }}</th>
                                    <th scope="col">{{ __('admin.webhooks.deliveries') }}</th>
                                    <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($endpoints as $endpoint)
                                    <tr wire:key="webhook-endpoint-{{ $endpoint->id }}">
                                        <td>
                                            <span class="ag-table__name">{{ $endpoint->name }}</span>
                                        </td>
                                        <td>{{ $endpoint->destination === 'discord' ? __('admin.webhooks.destination_discord') : __('admin.webhooks.destination_http') }}</td>
                                        <td><code>{{ $endpoint->url }}</code></td>
                                        <td>{{ implode(', ', $endpoint->events) }}</td>
                                        <td>
                                            <span @class(['ag-badge', 'ag-badge--success' => $endpoint->active, 'ag-badge--muted' => ! $endpoint->active])>
                                                {{ $endpoint->active ? __('admin.webhooks.active') : __('admin.webhooks.inactive') }}
                                            </span>
                                        </td>
                                        <td>{{ $endpoint->deliveries_count }}</td>
                                        <td class="ag-table__actions">
                                            <div class="ag-row-actions">
                                                <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="edit({{ $endpoint->id }})">{{ __('common.edit') }}</button>
                                                <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="toggleActive({{ $endpoint->id }})">{{ $endpoint->active ? __('admin.webhooks.disable') : __('admin.webhooks.enable') }}</button>
                                                <button type="button" class="ag-btn ag-btn--danger ag-btn--sm" wire:click="delete({{ $endpoint->id }})" wire:confirm="{{ __('admin.webhooks.delete_confirm') }}">{{ __('common.delete') }}</button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
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
                    <div class="ag-table-wrap">
                        <table class="ag-table">
                            <caption class="visually-hidden">{{ __('admin.webhooks.delivery_log') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('admin.webhooks.delivery') }}</th>
                                    <th scope="col">{{ __('admin.webhooks.endpoint') }}</th>
                                    <th scope="col">{{ __('admin.webhooks.event') }}</th>
                                    <th scope="col">{{ __('admin.webhooks.status') }}</th>
                                    <th scope="col">{{ __('admin.webhooks.attempts') }}</th>
                                    <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($deliveries as $delivery)
                                    <tr wire:key="webhook-delivery-{{ $delivery->id }}">
                                        <td><code>{{ $delivery->delivery_id }}</code></td>
                                        <td>{{ $delivery->endpoint?->name ?? __('common.em_dash') }}</td>
                                        <td>{{ $delivery->event_type }}</td>
                                        <td>
                                            <span @class([
                                                'ag-badge',
                                                'ag-badge--success' => $delivery->status === 'delivered',
                                                'ag-badge--warning' => in_array($delivery->status, ['queued', 'retrying'], true),
                                                'ag-badge--danger' => $delivery->status === 'failed',
                                                'ag-badge--muted' => ! in_array($delivery->status, ['delivered', 'queued', 'retrying', 'failed'], true),
                                            ])>{{ $delivery->status }}</span>
                                        </td>
                                        <td>{{ $delivery->attempt_count }}</td>
                                        <td class="ag-table__actions">
                                            @if (in_array($delivery->status, ['failed', 'retrying'], true))
                                                <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="retryDelivery({{ $delivery->id }})">{{ __('admin.webhooks.retry') }}</button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ag.card.content>
        </x-ag.card>
    </div>
</div>
