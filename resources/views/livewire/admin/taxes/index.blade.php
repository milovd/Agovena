<div class="admin-page">
    <x-ag.page-header :heading="__('admin.taxes.title')" :lede="__('admin.taxes.lede')">
        <x-slot:actions>
            @can('taxes.manage')
                <button type="button" class="ag-btn ag-btn--primary" wire:click="create">{{ __('admin.taxes.add') }}</button>
            @endcan
        </x-slot:actions>
    </x-ag.page-header>

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
                <input id="tax-rate" class="ag-input" type="number" min="0" wire:model.number="rate_bps" required>
                <p class="ag-field__help">{{ __('admin.taxes.rate_help') }}</p>
                @error('rate_bps') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="tax-country">{{ __('admin.taxes.country') }}</label>
                <input id="tax-country" class="ag-input" maxlength="2" wire:model="country">
                <p class="ag-field__help">{{ __('admin.taxes.country_help') }}</p>
                @error('country') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="tax-region">{{ __('admin.taxes.region') }}</label>
                <input id="tax-region" class="ag-input" wire:model="region">
                @error('region') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <x-ag.switch id="tax-shipping" wire:model="applies_to_shipping" :label="__('admin.taxes.applies_to_shipping')" />
            <x-ag.switch id="tax-active" wire:model="is_active" :label="__('common.active')" />
            <div class="ag-form__actions">
                <button class="ag-btn ag-btn--primary" type="submit">{{ __('common.save') }}</button>
                <button class="ag-btn ag-btn--secondary" type="button" wire:click="cancel">{{ __('common.cancel') }}</button>
            </div>
        </form>
    @endif

    @if ($rates->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.taxes.empty') }}</p>
            <p class="ag-empty__text">{{ __('admin.taxes.empty_text') }}</p>
        </div>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead><tr>
                    <th>{{ __('common.name') }}</th>
                    <th>{{ __('admin.taxes.rate') }}</th>
                    <th>{{ __('admin.taxes.location') }}</th>
                    <th>{{ __('admin.taxes.shipping') }}</th>
                    <th>{{ __('common.status') }}</th>
                    <th><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                </tr></thead>
                <tbody>
                    @foreach ($rates as $rate)
                        <tr wire:key="tax-{{ $rate->id }}">
                            <td>{{ $rate->name }}</td>
                            <td>{{ number_format($rate->rate_bps / 100, 2) }}%</td>
                            <td>{{ $rate->country ?: __('admin.taxes.all_countries') }}{{ $rate->region ? ' · '.$rate->region : '' }}</td>
                            <td>{{ $rate->applies_to_shipping ? __('common.yes') : __('common.no') }}</td>
                            <td><span class="ag-badge">{{ $rate->is_active ? __('common.active') : __('common.inactive') }}</span></td>
                            <td>
                                @can('taxes.manage')
                                    <button class="ag-btn ag-btn--ghost" type="button" wire:click="edit({{ $rate->id }})">{{ __('common.edit') }}</button>
                                    <button class="ag-btn ag-btn--ghost" type="button" wire:click="delete({{ $rate->id }})" wire:confirm="{{ __('admin.taxes.delete_confirm') }}">{{ __('common.delete') }}</button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $rates->links() }}
    @endif
</div>
