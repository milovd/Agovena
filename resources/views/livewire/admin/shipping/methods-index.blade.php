<div class="admin-page">
    <x-ag.page-header :heading="__('shipping::admin.methods_title')" :lede="__('shipping::admin.methods_lede')">
        <x-slot:actions>
            <a class="ag-btn ag-btn--secondary" href="{{ route('admin.shipping.zones') }}">{{ __('shipping::admin.zones_link') }}</a>
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    @can('shipping.manage')
        <form wire:submit="save" class="ag-form ag-section" style="margin-bottom: 1.5rem;">
            <header class="ag-section__header">
                <h3 class="ag-section__title">{{ __('shipping::admin.add_method') }}</h3>
            </header>
            <div class="ag-section__body ag-grid ag-grid--2">
                <div class="ag-field">
                    <label class="ag-field__label" for="m-name">{{ __('shipping::admin.name') }}</label>
                    <input id="m-name" class="ag-input" type="text" wire:model="name" required>
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="m-code">{{ __('shipping::admin.code') }}</label>
                    <input id="m-code" class="ag-input" type="text" wire:model="code" required>
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="m-type">{{ __('shipping::admin.type') }}</label>
                    <select id="m-type" class="ag-select" wire:model.live="type">
                        @foreach ($types as $case)
                            <option value="{{ $case->value }}">{{ __('shipping::admin.types.'.$case->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="m-zone">{{ __('shipping::admin.zone') }}</label>
                    <select id="m-zone" class="ag-select" wire:model="zone_id">
                        <option value="">{{ __('shipping::admin.no_zone') }}</option>
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="m-currency">{{ __('shipping::admin.currency') }}</label>
                    <input id="m-currency" class="ag-input" type="text" maxlength="3" wire:model="currency" required>
                </div>
                @if (in_array($type, ['flat', 'zone', 'free'], true))
                    <div class="ag-field">
                        <label class="ag-field__label" for="m-amount">{{ __('shipping::admin.amount') }}</label>
                        <input id="m-amount" class="ag-input" type="number" min="0" wire:model="amount">
                    </div>
                @endif
                <div class="ag-field">
                    <label class="ag-field__label" for="m-min">{{ __('shipping::admin.min_subtotal') }}</label>
                    <input id="m-min" class="ag-input" type="number" min="0" wire:model="min_subtotal">
                </div>
                @if ($type === 'price')
                    <div class="ag-field ag-grid__span-2">
                        <label class="ag-field__label" for="m-tiers">{{ __('shipping::admin.price_tiers_hint') }}</label>
                        <textarea id="m-tiers" class="ag-input" rows="3" wire:model="tiers_json"></textarea>
                    </div>
                @endif
                @if ($type === 'weight')
                    <div class="ag-field ag-grid__span-2">
                        <label class="ag-field__label" for="m-wtiers">{{ __('shipping::admin.weight_tiers_hint') }}</label>
                        <textarea id="m-wtiers" class="ag-input" rows="3" wire:model="tiers_json"></textarea>
                    </div>
                @endif
                <div class="ag-field">
                    <label class="ag-check">
                        <input type="checkbox" wire:model="is_active">
                        <span>{{ __('shipping::admin.active') }}</span>
                    </label>
                </div>
            </div>
            <button type="submit" class="ag-btn ag-btn--primary">{{ __('shipping::admin.save') }}</button>
        </form>
    @endcan

    @if ($methods->isEmpty())
        <p class="ag-muted">{{ __('shipping::admin.empty_methods') }}</p>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th>{{ __('shipping::admin.name') }}</th>
                        <th>{{ __('shipping::admin.type') }}</th>
                        <th>{{ __('shipping::admin.zone') }}</th>
                        <th>{{ __('common.status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($methods as $method)
                        <tr wire:key="method-{{ $method->id }}">
                            <td>
                                <span class="ag-table__name">{{ $method->name }}</span>
                                <span class="ag-muted">{{ $method->code }}</span>
                            </td>
                            <td>{{ __('shipping::admin.types.'.$method->type->value) }}</td>
                            <td>{{ $method->zone?->name ?? '—' }}</td>
                            <td>{{ $method->is_active ? __('common.active') : __('common.inactive') }}</td>
                            <td>
                                @can('shipping.manage')
                                    <button type="button" class="ag-btn ag-btn--ghost" wire:click="delete({{ $method->id }})">{{ __('common.delete') }}</button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
