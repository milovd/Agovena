<div class="admin-page">
    <x-ag.page-header :heading="__('shipping::admin.zones_title')" :lede="__('shipping::admin.zones_lede')">
        <x-slot:actions>
            <a class="ag-btn ag-btn--secondary" href="{{ route('admin.shipping.methods') }}">{{ __('shipping::admin.methods_link') }}</a>
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    @can('shipping.manage')
        <form wire:submit="save" class="ag-form ag-section" style="margin-bottom: 1.5rem;">
            <header class="ag-section__header">
                <h3 class="ag-section__title">{{ __('shipping::admin.add_zone') }}</h3>
            </header>
            <div class="ag-section__body ag-grid ag-grid--2">
                <div class="ag-field">
                    <label class="ag-field__label" for="z-name">{{ __('shipping::admin.name') }}</label>
                    <input id="z-name" class="ag-input" type="text" wire:model="name" required>
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="z-countries">{{ __('shipping::admin.countries') }}</label>
                    <input id="z-countries" class="ag-input" type="text" wire:model="countries" required>
                    <p class="ag-field__hint">{{ __('shipping::admin.countries_hint') }}</p>
                </div>
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

    @if ($zones->isEmpty())
        <p class="ag-muted">{{ __('shipping::admin.empty_zones') }}</p>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th>{{ __('shipping::admin.name') }}</th>
                        <th>{{ __('shipping::admin.countries') }}</th>
                        <th>{{ __('common.status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($zones as $zone)
                        <tr wire:key="zone-{{ $zone->id }}">
                            <td>{{ $zone->name }}</td>
                            <td>{{ implode(', ', $zone->countries ?? []) }}</td>
                            <td>{{ $zone->is_active ? __('common.active') : __('common.inactive') }}</td>
                            <td>
                                @can('shipping.manage')
                                    <button type="button" class="ag-btn ag-btn--ghost" wire:click="delete({{ $zone->id }})">{{ __('common.delete') }}</button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
