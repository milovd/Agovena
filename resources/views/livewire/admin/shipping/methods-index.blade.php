<div class="admin-page ag-list-page c-shipping">
    <x-ag.page-header :heading="__('shipping::admin.methods_title')" :lede="__('shipping::admin.methods_lede')">
        <x-slot:actions>
            <a class="ag-btn ag-btn--secondary" href="{{ route('admin.shipping.zones') }}">{{ __('shipping::admin.zones_link') }}</a>
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    @can('shipping.manage')
        <form wire:submit="save" class="ag-form ag-section c-shipping__form">
            <header class="ag-section__header c-shipping__heading">
                <x-ag.icon-tile name="truck" tone="blue" />
                <h2 class="ag-section__title">{{ __('shipping::admin.add_method') }}</h2>
            </header>
            <div class="ag-section__body">
                <div class="ag-grid ag-grid--2">
                    <div class="ag-field">
                        <label class="ag-field__label" for="m-name">{{ __('shipping::admin.name') }}</label>
                        <input id="m-name" class="ag-input" type="text" wire:model="name" required>
                        @error('name') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="m-code">{{ __('shipping::admin.code') }}</label>
                        <input id="m-code" class="ag-input" type="text" wire:model="code" required>
                        @error('code') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="m-type">{{ __('shipping::admin.type') }}</label>
                        <select id="m-type" class="ag-select" wire:model.live="type">
                            @foreach ($types as $case)
                                <option value="{{ $case->value }}">{{ __('shipping::admin.types.'.$case->value) }}</option>
                            @endforeach
                        </select>
                        @error('type') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="m-zone">{{ __('shipping::admin.zone') }}</label>
                        <select id="m-zone" class="ag-select" wire:model="zone_id">
                            <option value="">{{ __('shipping::admin.no_zone') }}</option>
                            @foreach ($zones as $zone)
                                <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                            @endforeach
                        </select>
                        @error('zone_id') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="m-currency">{{ __('shipping::admin.currency') }}</label>
                        <input id="m-currency" class="ag-input" type="text" maxlength="3" wire:model="currency" required>
                        @error('currency') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    @if (in_array($type, ['flat', 'zone', 'free'], true))
                        <div class="ag-field">
                            <label class="ag-field__label" for="m-amount">{{ __('shipping::admin.amount') }}</label>
                            <input id="m-amount" class="ag-input" type="number" min="0" wire:model="amount">
                            @error('amount') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <div class="ag-field">
                        <label class="ag-field__label" for="m-min">{{ __('shipping::admin.min_subtotal') }}</label>
                        <input id="m-min" class="ag-input" type="number" min="0" wire:model="min_subtotal">
                        @error('min_subtotal') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    @if ($type === 'price')
                        <div class="ag-field ag-grid__span-2">
                            <label class="ag-field__label" for="m-tiers">{{ __('shipping::admin.price_tiers_hint') }}</label>
                            <textarea id="m-tiers" class="ag-input" rows="3" wire:model="tiers_json"></textarea>
                            @error('tiers_json') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    @if ($type === 'weight')
                        <div class="ag-field ag-grid__span-2">
                            <label class="ag-field__label" for="m-wtiers">{{ __('shipping::admin.weight_tiers_hint') }}</label>
                            <textarea id="m-wtiers" class="ag-input" rows="3" wire:model="tiers_json"></textarea>
                            @error('tiers_json') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <div class="ag-field c-shipping__active">
                        <label class="ag-check">
                            <input type="checkbox" wire:model="is_active">
                            <span>{{ __('shipping::admin.active') }}</span>
                        </label>
                    </div>
                </div>
                <div class="ag-form__actions c-shipping__actions">
                    <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled" wire:target="save">{{ __('shipping::admin.save') }}</button>
                </div>
            </div>
        </form>
    @endcan

    @if ($methods->isEmpty())
        <div class="ag-empty" role="status"><p class="ag-empty__title">{{ __('shipping::admin.empty_methods') }}</p></div>
    @else
        <div class="ag-table-wrap c-shipping__table-wrap" role="region" aria-label="{{ __('shipping::admin.methods_title') }}" tabindex="0">
            <table class="ag-table c-shipping__table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('shipping::admin.name') }}</th>
                        <th scope="col">{{ __('shipping::admin.type') }}</th>
                        <th scope="col">{{ __('shipping::admin.zone') }}</th>
                        <th scope="col">{{ __('common.status') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($methods as $method)
                        <tr wire:key="method-{{ $method->id }}">
                            <td class="c-shipping__identity">
                                <span class="c-shipping__name">{{ $method->name }}</span>
                                <span class="c-shipping__code">{{ $method->code }}</span>
                            </td>
                            <td class="c-shipping__detail" data-label="{{ __('shipping::admin.type') }}">{{ __('shipping::admin.types.'.$method->type->value) }}</td>
                            <td class="c-shipping__detail" data-label="{{ __('shipping::admin.zone') }}">{{ $method->zone?->name ?? '-' }}</td>
                            <td class="c-shipping__detail" data-label="{{ __('common.status') }}"><x-ag.badge :variant="$method->is_active ? 'success' : 'muted'">{{ $method->is_active ? __('common.active') : __('common.inactive') }}</x-ag.badge></td>
                            <td class="ag-table__actions c-shipping__row-actions">
                                @can('shipping.manage')
                                    <button type="button" class="ag-icon-btn ag-icon-btn--danger" wire:click="delete({{ $method->id }})" wire:confirm="{{ __('shipping::admin.delete_method_confirm', ['name' => $method->name]) }}" title="{{ __('common.delete') }}" aria-label="{{ __('common.delete') }}: {{ $method->name }}"><x-ag.icon name="trash" :size="16" /></button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
