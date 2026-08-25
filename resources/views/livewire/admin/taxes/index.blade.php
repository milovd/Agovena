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
            <p class="ag-field__help">{{ __('admin.taxes.list_help') }} ({{ __('admin.settings.fields.automatic_tax_rates') }} · {{ $automaticSourceLabel }} · {{ $automaticVersion }})</p>
        @endif
    </div>

    @if ($showForm)
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
    <p class="ag-field__help">{{ __('admin.taxes.list_help') }}</p>

    @if ($rates->isNotEmpty())
        <div class="ag-field" style="max-width: 20rem;">
            <label class="ag-field__label" for="tax-filter">{{ __('admin.taxes.filter') }}</label>
            <input id="tax-filter" class="ag-input" wire:model.live.debounce.300ms="filter" placeholder="{{ __('admin.taxes.filter_placeholder') }}">
        </div>
    @endif

    <div class="ag-table-wrap">
        <table class="ag-table">
            <thead><tr>
                <th>{{ __('admin.taxes.country') }}</th>
                <th>{{ __('common.name') }}</th>
                <th>{{ __('admin.taxes.rate') }}</th>
                <th>{{ __('admin.taxes.source') }}</th>
                <th>{{ __('admin.taxes.shipping') }}</th>
                <th>{{ __('common.status') }}</th>
                <th><span class="visually-hidden">{{ __('common.actions') }}</span></th>
            </tr></thead>
            <tbody>
                @forelse ($rates as $rate)
                    <tr wire:key="tax-rate-{{ $rate->id }}">
                        <td>{{ $rate->country ?: __('admin.taxes.fallback_badge') }}</td>
                        <td>{{ $rate->name }}</td>
                        <td>
                            @if ($rate->is_disabled)
                                {{ __('admin.taxes.no_tax') }}
                            @else
                                {{ number_format($rate->rate_bps / 100, 2) }}%
                            @endif
                        </td>
                        <td>
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
                        <td>{{ $rate->applies_to_shipping ? __('common.yes') : __('common.no') }}</td>
                        <td><span class="ag-badge">{{ $rate->is_active ? __('common.active') : __('common.inactive') }}</span></td>
                        <td>
                            @can('taxes.manage')
                                <button class="ag-btn ag-btn--ghost" type="button" wire:click="edit({{ $rate->id }})">{{ __('common.edit') }}</button>
                                <button class="ag-btn ag-btn--ghost" type="button" wire:click="delete({{ $rate->id }})" wire:confirm="{{ __('admin.taxes.delete_confirm') }}">{{ __('common.delete') }}</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            {{ $filter !== '' ? __('admin.taxes.filter_empty') : __('admin.taxes.empty') }}
                            @if ($filter === '')
                                <span class="ag-field__help">{{ __('admin.taxes.empty_text') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
