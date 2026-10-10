<div class="admin-page ag-list-page c-shipping">
    <x-ag.page-header :heading="__('shipping::admin.zones_title')" :lede="__('shipping::admin.zones_lede')">
        <x-slot:actions>
            <a class="ag-btn ag-btn--secondary" href="{{ route('admin.shipping.methods') }}">{{ __('shipping::admin.methods_link') }}</a>
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    @can('shipping.manage')
        <form wire:submit="save" class="ag-form ag-section c-shipping__form">
            <header class="ag-section__header c-shipping__heading">
                <x-ag.icon-tile name="globe" tone="cyan" />
                <h2 class="ag-section__title">{{ __('shipping::admin.add_zone') }}</h2>
            </header>
            <div class="ag-section__body">
                <div class="ag-grid ag-grid--2">
                    <div class="ag-field">
                        <label class="ag-field__label" for="z-name">{{ __('shipping::admin.name') }}</label>
                        <input id="z-name" class="ag-input" type="text" wire:model="name" required>
                        @error('name') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="z-countries">{{ __('shipping::admin.countries') }}</label>
                        <input id="z-countries" class="ag-input" type="text" wire:model="countries" required>
                        <p class="ag-field__hint">{{ __('shipping::admin.countries_hint') }}</p>
                        @error('countries') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
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

    @if ($zones->isEmpty())
        <div class="ag-empty" role="status"><p class="ag-empty__title">{{ __('shipping::admin.empty_zones') }}</p></div>
    @else
        <div class="ag-table-wrap c-shipping__table-wrap" role="region" aria-label="{{ __('shipping::admin.zones_title') }}" tabindex="0">
            <table class="ag-table c-shipping__table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('shipping::admin.name') }}</th>
                        <th scope="col">{{ __('shipping::admin.countries_column') }}</th>
                        <th scope="col">{{ __('common.status') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($zones as $zone)
                        <tr wire:key="zone-{{ $zone->id }}">
                            <td class="c-shipping__identity"><span class="c-shipping__name">{{ $zone->name }}</span></td>
                            <td class="c-shipping__detail" data-label="{{ __('shipping::admin.countries_column') }}">{{ implode(', ', $zone->countries ?? []) }}</td>
                            <td class="c-shipping__detail" data-label="{{ __('common.status') }}"><x-ag.badge :variant="$zone->is_active ? 'success' : 'muted'">{{ $zone->is_active ? __('common.active') : __('common.inactive') }}</x-ag.badge></td>
                            <td class="ag-table__actions c-shipping__row-actions">
                                @can('shipping.manage')
                                    <button type="button" class="ag-icon-btn ag-icon-btn--danger" wire:click="delete({{ $zone->id }})" wire:confirm="{{ __('shipping::admin.delete_zone_confirm', ['name' => $zone->name]) }}" title="{{ __('common.delete') }}" aria-label="{{ __('common.delete') }}: {{ $zone->name }}"><x-ag.icon name="trash" :size="16" /></button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
