<div class="admin-page">
    <x-ag.page-header :heading="__('admin.customer_properties.title')" :lede="__('admin.customer_properties.lede')">
        <x-slot:actions>
            <x-ag.back :href="route('admin.customers.index')" :label="__('admin.customers.title')" />
            <button type="button" class="ag-btn ag-btn--primary" wire:click="create">{{ __('admin.customer_properties.add') }}</button>
        </x-slot:actions>
    </x-ag.page-header>

    @if ($showForm)
        <form wire:submit="save" class="admin-panel ag-form" novalidate>
            <h3 class="admin-panel__title">{{ $editingId ? __('admin.customer_properties.edit') : __('admin.customer_properties.new') }}</h3>
            <div class="ag-grid ag-grid--2">
                <div class="ag-field">
                    <label class="ag-field__label" for="prop-label">{{ __('admin.customer_properties.field_label') }}</label>
                    <input id="prop-label" class="ag-input" wire:model="label" required>
                    @error('label') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="prop-key">{{ __('admin.customer_properties.key') }}</label>
                    <input id="prop-key" class="ag-input" wire:model="key" required>
                    <p class="ag-field__help">{{ __('admin.customer_properties.key_help') }}</p>
                    @error('key') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="prop-type">{{ __('admin.customer_properties.type') }}</label>
                    <select id="prop-type" class="ag-select" wire:model.live="type">
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}">{{ __('admin.customer_properties.types.'.$type->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="prop-sort">{{ __('admin.customer_properties.sort') }}</label>
                    <input id="prop-sort" class="ag-input" type="number" min="0" wire:model.number="sort">
                </div>
            </div>
            @if ($type === 'select')
                <div class="ag-field">
                    <label class="ag-field__label" for="prop-options">{{ __('admin.customer_properties.options') }}</label>
                    <textarea id="prop-options" class="ag-input" rows="4" wire:model="optionsText"></textarea>
                    <p class="ag-field__help">{{ __('admin.customer_properties.options_help') }}</p>
                    @error('optionsText') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
            @endif
            @if (in_array($type, ['text', 'textarea', 'phone'], true))
                <div class="ag-field">
                    <label class="ag-field__label" for="prop-max">{{ __('admin.customer_properties.max_length') }}</label>
                    <input id="prop-max" class="ag-input" type="number" min="1" wire:model.number="max_length">
                </div>
            @endif
            @if ($type === 'number')
                <div class="ag-grid ag-grid--2">
                    <div class="ag-field">
                        <label class="ag-field__label" for="prop-min">{{ __('admin.customer_properties.min') }}</label>
                        <input id="prop-min" class="ag-input" type="number" wire:model.number="min">
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="prop-maxn">{{ __('admin.customer_properties.max') }}</label>
                        <input id="prop-maxn" class="ag-input" type="number" wire:model.number="max">
                    </div>
                </div>
            @endif
            <x-ag.switch id="prop-required" wire:model="is_required" :label="__('admin.customer_properties.required')" />
            <x-ag.switch id="prop-active" wire:model="is_active" :label="__('common.active')" />
            <x-ag.switch id="prop-reg" wire:model="show_on_registration" :label="__('admin.customer_properties.show_on_registration')" />
            <x-ag.switch id="prop-checkout" wire:model="show_on_checkout" :label="__('admin.customer_properties.show_on_checkout')" />
            <x-ag.switch id="prop-account" wire:model="show_on_account" :label="__('admin.customer_properties.show_on_account')" />
            <x-ag.switch id="prop-invoice" wire:model="show_on_invoice" :label="__('admin.customer_properties.show_on_invoice')" />
            <x-ag.switch id="prop-customer" wire:model="customer_editable" :label="__('admin.customer_properties.customer_editable')" />
            <x-ag.switch id="prop-staff" wire:model="staff_editable" :label="__('admin.customer_properties.staff_editable')" />
            <x-ag.switch id="prop-internal" wire:model="internal_only" :label="__('admin.customer_properties.internal_only')" />
            <p class="ag-field__help">{{ __('admin.customer_properties.core_note') }}</p>
            <div class="ag-form__actions">
                <button class="ag-btn ag-btn--primary" type="submit">{{ __('common.save') }}</button>
                <button class="ag-btn ag-btn--secondary" type="button" wire:click="cancel">{{ __('common.cancel') }}</button>
            </div>
        </form>
    @endif

    @if ($definitions->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.customer_properties.empty') }}</p>
            <p class="ag-empty__text">{{ __('admin.customer_properties.empty_text') }}</p>
        </div>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead><tr>
                    <th>{{ __('admin.customer_properties.field_label') }}</th>
                    <th>{{ __('admin.customer_properties.key') }}</th>
                    <th>{{ __('admin.customer_properties.type') }}</th>
                    <th>{{ __('common.status') }}</th>
                    <th><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                </tr></thead>
                <tbody>
                    @foreach ($definitions as $definition)
                        <tr wire:key="prop-{{ $definition->id }}">
                            <td>{{ $definition->label }}</td>
                            <td><code>{{ $definition->key }}</code></td>
                            <td>{{ __('admin.customer_properties.types.'.$definition->type->value) }}</td>
                            <td><span class="ag-badge">{{ $definition->is_active ? __('common.active') : __('common.inactive') }}</span></td>
                            <td>
                                <button class="ag-btn ag-btn--ghost" type="button" wire:click="edit({{ $definition->id }})">{{ __('common.edit') }}</button>
                                <button class="ag-btn ag-btn--ghost" type="button" wire:click="delete({{ $definition->id }})" wire:confirm="{{ __('admin.customer_properties.delete_confirm') }}">{{ __('common.delete') }}</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $definitions->links() }}
    @endif
</div>
