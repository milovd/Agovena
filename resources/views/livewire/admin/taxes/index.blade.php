<div class="admin-page">
    <x-ag.page-header :heading="__('admin.taxes.title')" :lede="__('admin.taxes.lede')">
        <x-slot:actions>
            @can('taxes.manage')
                <button type="button" class="ag-btn ag-btn--primary" wire:click="create">{{ __('admin.taxes.add_override') }}</button>
            @endcan
        </x-slot:actions>
    </x-ag.page-header>

    <div class="admin-panel">
        @can('taxes.manage')
            <x-ag.switch id="tax-enabled" wire:model.live="tax_enabled" :label="__('admin.settings.fields.tax_enabled')" />
            <p class="ag-field__help">{{ __('admin.settings.field_help.tax_enabled') }}</p>

            <x-ag.switch
                id="automatic-tax-rates"
                wire:model.live="automatic_tax_rates"
                :label="__('admin.settings.fields.automatic_tax_rates')"
                :disabled="! $tax_enabled"
            />
            <p class="ag-field__help">{{ __('admin.settings.field_help.automatic_tax_rates') }}</p>
        @else
            <p>
                <strong>{{ __('admin.settings.fields.tax_enabled') }}:</strong>
                {{ $tax_enabled ? __('common.yes') : __('common.no') }}
            </p>
            <p>
                <strong>{{ __('admin.settings.fields.automatic_tax_rates') }}:</strong>
                {{ $automatic_tax_rates ? __('common.yes') : __('common.no') }}
            </p>
        @endcan
        <p class="ag-field__help" style="margin-top: 1rem;">{{ __('admin.taxes.workflow_help') }}</p>
        @if ($automatic_tax_rates && $tax_enabled)
            <p class="ag-field__help">{{ __('admin.taxes.sources.automatic') }}: {{ $automaticSourceLabel }} ({{ $automaticVersion }})</p>
        @endif
    </div>

    @if ($showForm && auth()->user()?->can('taxes.manage'))
        <form wire:submit="save" class="admin-panel ag-form" novalidate>
            <h3 class="admin-panel__title">{{ $editingId ? __('admin.taxes.edit') : __('admin.taxes.new') }}</h3>
            <div class="ag-field">
                <label class="ag-field__label" for="tax-name">{{ __('common.name') }}</label>
                <input id="tax-name" class="ag-input" wire:model="name" required>
                @error('name') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="tax-rate">{{ __('admin.taxes.rate_bps') }}</label>
                <input id="tax-rate" class="ag-input" type="number" min="0" wire:model.number="rate_bps" required @disabled($is_disabled)>
                <p class="ag-field__help">{{ __('admin.taxes.rate_help') }}</p>
                @error('rate_bps') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="tax-country">{{ __('admin.taxes.country') }}</label>
                <input id="tax-country" class="ag-input" maxlength="2" wire:model.live="country">
                <p class="ag-field__help">{{ __('admin.taxes.country_help') }}</p>
                @error('country') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="tax-region">{{ __('admin.taxes.region') }}</label>
                <input id="tax-region" class="ag-input" wire:model="region">
                @error('region') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <x-ag.switch id="tax-shipping" wire:model="applies_to_shipping" :label="__('admin.taxes.applies_to_shipping')" />
            <x-ag.switch id="tax-disabled" wire:model.live="is_disabled" :label="__('admin.taxes.disable_for_country')" />
            <x-ag.switch id="tax-active" wire:model="is_active" :label="__('common.active')" />
            <div class="ag-form__actions">
                <button class="ag-btn ag-btn--primary" type="submit">{{ __('common.save') }}</button>
                <button class="ag-btn ag-btn--secondary" type="button" wire:click="cancel">{{ __('common.cancel') }}</button>
            </div>
        </form>
    @endif

    <h3 class="admin-panel__title">{{ __('admin.taxes.list_title') }}</h3>
    @if ($rates->isNotEmpty() || $filter !== '')
        <p class="ag-field__help">{{ __('admin.taxes.list_help') }}</p>
    @endif

    @if ($rates->isNotEmpty() || $filter !== '')
        <div class="ag-field" style="max-width: 20rem;">
            <label class="ag-field__label" for="tax-filter">{{ __('admin.taxes.filter') }}</label>
            <input id="tax-filter" class="ag-input" wire:model.live.debounce.300ms="filter" placeholder="{{ __('admin.taxes.filter_placeholder') }}">
        </div>
    @endif

    @if ($rates->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ $filter !== '' ? __('admin.taxes.filter_empty') : __('admin.taxes.empty') }}</p>
            @if ($filter === '')
                <p class="ag-empty__text">
                    @if (! $tax_enabled)
                        {{ __('admin.taxes.empty_tax_disabled') }}
                    @elseif (! $automatic_tax_rates)
                        {{ __('admin.taxes.empty_manual') }}
                    @else
                        {{ __('admin.taxes.empty_text') }}
                    @endif
                </p>
            @endif
        </div>
    @else
        <div class="ag-table-wrap ag-table-wrap--mobile-cards" role="region" aria-label="{{ __('admin.taxes.list_title') }}" tabindex="0">
            <table class="ag-table ag-table--mobile-cards ag-table--taxes">
                <thead><tr>
                    <th>{{ __('admin.taxes.country') }}</th>
                    <th>{{ __('common.name') }}</th>
                    <th>{{ __('admin.taxes.rate') }}</th>
                    <th>{{ __('admin.taxes.source') }}</th>
                    <th>{{ __('admin.taxes.shipping') }}</th>
                    <th>{{ __('common.status') }}</th>
                    @can('taxes.manage')
                        <th><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                    @endcan
                </tr></thead>
                <tbody>
                    @foreach ($rates as $rate)
                        <tr wire:key="tax-rate-{{ $rate->id }}">
                            <td data-label="{{ __('admin.taxes.country') }}">{{ $rate->country ?: __('admin.taxes.fallback_badge') }}</td>
                            <td data-label="{{ __('common.name') }}">{{ $rate->name }}</td>
                            <td data-label="{{ __('admin.taxes.rate') }}">
                                @if ($rate->is_disabled)
                                    {{ __('admin.taxes.no_tax') }}
                                @else
                                    {{ number_format($rate->rate_bps / 100, 2) }}%
                                @endif
                            </td>
                            <td data-label="{{ __('admin.taxes.source') }}">
                                <span class="ag-badge">
                                    @if ($rate->is_disabled)
                                        {{ __('admin.taxes.sources.disabled') }}
                                    @elseif ($rate->country === null)
                                        {{ __('admin.taxes.sources.fallback') }}
                                    @else
                                        {{ __('admin.taxes.sources.override') }}
                                    @endif
                                </span>
                            </td>
                            <td data-label="{{ __('admin.taxes.shipping') }}">{{ $rate->applies_to_shipping ? __('common.yes') : __('common.no') }}</td>
                            <td data-label="{{ __('common.status') }}"><span class="ag-badge {{ $rate->is_active ? 'ag-badge--success' : 'ag-badge--muted' }}">{{ $rate->is_active ? __('common.active') : __('common.inactive') }}</span></td>
                            @can('taxes.manage')
                                <td class="ag-table__actions" data-label="{{ __('common.actions') }}">
                                    <span class="ag-row-actions">
                                        <button class="ag-btn ag-btn--ghost" type="button" wire:click="edit({{ $rate->id }})">{{ __('common.edit') }}</button>
                                        <button class="ag-btn ag-btn--ghost" type="button" wire:click="delete({{ $rate->id }})" wire:confirm="{{ __('admin.taxes.delete_confirm') }}">{{ __('common.delete') }}</button>
                                    </span>
                                </td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
