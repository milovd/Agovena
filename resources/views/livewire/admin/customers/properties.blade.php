<div class="admin-page">
    @if ($showForm)
        <nav class="admin-page__crumb" aria-label="{{ __('admin.settings.breadcrumb_aria') }}">
            <button type="button" class="admin-page__crumb-link" wire:click="cancel">
                {{ __('admin.customer_properties.title') }}
            </button>
            <span aria-hidden="true"> / </span>
            <span>{{ $editingId ? __('admin.customer_properties.edit') : __('admin.customer_properties.new') }}</span>
        </nav>

        <x-ag.page-header
            :heading="$editingId ? __('admin.customer_properties.edit') : __('admin.customer_properties.new')"
        >
            <x-slot:actions>
                @if ($editingId)
                    <button
                        type="button"
                        class="ag-btn ag-btn--danger"
                        wire:click="delete({{ $editingId }})"
                        wire:confirm="{{ __('admin.customer_properties.delete_confirm') }}"
                    >
                        {{ __('common.delete') }}
                    </button>
                @endif
            </x-slot:actions>
        </x-ag.page-header>

        <form wire:submit="save" class="ag-section ag-form ag-form--constrained" novalidate>
            <div class="ag-section__body">
                <div class="ag-grid ag-grid--2">
                    <div class="ag-field">
                        <label class="ag-field__label" for="property-label">{{ __('admin.customer_properties.field_label') }}</label>
                        <input id="property-label" type="text" class="ag-input" wire:model="label" required>
                        @error('label') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="property-key">{{ __('admin.customer_properties.key') }}</label>
                        <input id="property-key" type="text" class="ag-input" wire:model="key" required @disabled($editingId !== null)>
                        <p class="ag-field__help">{{ __('admin.customer_properties.key_help') }}</p>
                        @error('key') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="property-type">{{ __('admin.customer_properties.type') }}</label>
                        <select id="property-type" class="ag-select" wire:model.live="type">
                            @foreach ($types as $case)
                                <option value="{{ $case->value }}">{{ __('admin.customer_properties.types.'.$case->value) }}</option>
                            @endforeach
                        </select>
                        @error('type') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    @if (in_array($type, ['text', 'textarea', 'number'], true))
                        <div class="ag-field">
                            <label class="ag-field__label" for="property-validation">{{ __('admin.customer_properties.validation') }}</label>
                            <input id="property-validation" type="text" class="ag-input" wire:model="validation" placeholder="string|max:255">
                            @error('validation') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                <div class="ag-field">
                    <label class="ag-field__label" for="property-description">{{ __('admin.customer_properties.description') }}</label>
                    <textarea id="property-description" class="ag-input" rows="3" wire:model="description"></textarea>
                    @error('description') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>

                @if ($type === 'select')
                    <div class="ag-field">
                        <label class="ag-field__label">{{ __('admin.customer_properties.options') }}</label>
                        <div class="ag-stack">
                            @foreach ($options as $index => $option)
                                <div class="ag-grid ag-grid--2" wire:key="option-{{ $index }}" style="align-items: end;">
                                    <input type="text" class="ag-input" placeholder="{{ __('admin.customer_properties.option_value') }}" wire:model="options.{{ $index }}.value">
                                    <div style="display:flex; gap: 0.5rem; align-items: center;">
                                        <input type="text" class="ag-input" placeholder="{{ __('admin.customer_properties.option_label') }}" wire:model="options.{{ $index }}.label">
                                        <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="removeOption({{ $index }})">
                                            {{ __('common.remove') }}
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <p>
                            <button type="button" class="ag-btn ag-btn--secondary ag-btn--sm" wire:click="addOption">
                                {{ __('admin.customer_properties.add_option') }}
                            </button>
                        </p>
                        @error('options') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                @endif

                <fieldset class="ag-field">
                    <legend class="ag-field__label">{{ __('admin.customer_properties.surfaces') }}</legend>
                    <div class="ag-switch-row">
                        <x-ag.switch wire:model="show_on_registration" :label="__('admin.customer_properties.show_on_registration')" />
                        <x-ag.switch wire:model="show_on_checkout" :label="__('admin.customer_properties.show_on_checkout')" />
                        <x-ag.switch wire:model="show_on_account" :label="__('admin.customer_properties.show_on_account')" />
                        <x-ag.switch wire:model="show_on_invoice" :label="__('admin.customer_properties.show_on_invoice')" />
                    </div>
                </fieldset>

                <fieldset class="ag-field">
                    <legend class="ag-field__label">{{ __('admin.customer_properties.behavior') }}</legend>
                    <div class="ag-switch-row">
                        <x-ag.switch wire:model="is_required" :label="__('admin.customer_properties.required')" />
                        <x-ag.switch wire:model="customer_editable" :label="__('admin.customer_properties.customer_editable')" />
                        <x-ag.switch wire:model="staff_editable" :label="__('admin.customer_properties.staff_editable')" />
                        <x-ag.switch wire:model="internal_only" :label="__('admin.customer_properties.internal_only')" />
                        <x-ag.switch wire:model="is_active" :label="__('common.active')" />
                    </div>
                </fieldset>

                <div class="ag-field">
                    <label class="ag-field__label" for="property-sort">{{ __('admin.customer_properties.sort') }}</label>
                    <input id="property-sort" type="number" class="ag-input" wire:model="sort" min="0">
                </div>

                <p class="ag-muted">{{ __('admin.customer_properties.core_note') }}</p>

                <div class="ag-form__actions">
                    <button type="submit" class="ag-btn ag-btn--primary">{{ __('common.save') }}</button>
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancel">{{ __('common.cancel') }}</button>
                </div>
            </div>
        </form>
    @else
        <x-ag.page-header :heading="__('admin.customer_properties.title')" :lede="__('admin.customer_properties.lede')">
            <x-slot:actions>
                <button type="button" class="ag-btn ag-btn--primary" wire:click="create">
                    {{ __('admin.customer_properties.add') }}
                </button>
            </x-slot:actions>
        </x-ag.page-header>

        @if ($definitions->isEmpty())
            <p class="ag-muted">{{ __('admin.customer_properties.empty_text') }}</p>
        @else
            <div class="ag-table-wrap">
                <table class="ag-table ag-table--properties">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('admin.customer_properties.property') }}</th>
                            <th scope="col">{{ __('admin.customer_properties.key') }}</th>
                            <th scope="col">{{ __('admin.customer_properties.type') }}</th>
                            <th scope="col" class="ag-table__col-toggle">{{ __('admin.customer_properties.non_editable') }}</th>
                            <th scope="col" class="ag-table__col-toggle">{{ __('admin.customer_properties.required') }}</th>
                            <th scope="col" class="ag-table__col-toggle">{{ __('admin.customer_properties.show_on_invoice') }}</th>
                            <th scope="col" class="ag-table__col-actions"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($definitions as $definition)
                            <tr wire:key="property-{{ $definition->id }}">
                                <td>
                                    <span class="ag-table__primary">{{ $definition->label }}</span>
                                    @if ($definition->description)
                                        <span class="ag-table__muted">{{ Str::limit($definition->description, 60) }}</span>
                                    @endif
                                </td>
                                <td><code>{{ $definition->key }}</code></td>
                                <td>{{ __('admin.customer_properties.types.'.$definition->type->value) }}</td>
                                <td class="ag-table__col-toggle">
                                    <x-ag.switch
                                        class="ag-switch--table"
                                        :checked="! $definition->customer_editable"
                                        wire:click.prevent="toggleField({{ $definition->id }}, 'customer_editable')"
                                        :aria-label="__('admin.customer_properties.non_editable')"
                                    />
                                </td>
                                <td class="ag-table__col-toggle">
                                    <x-ag.switch
                                        class="ag-switch--table"
                                        :checked="$definition->is_required"
                                        wire:click.prevent="toggleField({{ $definition->id }}, 'is_required')"
                                        :aria-label="__('admin.customer_properties.required')"
                                    />
                                </td>
                                <td class="ag-table__col-toggle">
                                    <x-ag.switch
                                        class="ag-switch--table"
                                        :checked="$definition->show_on_invoice"
                                        wire:click.prevent="toggleField({{ $definition->id }}, 'show_on_invoice')"
                                        :aria-label="__('admin.customer_properties.show_on_invoice')"
                                    />
                                </td>
                                <td class="ag-table__col-actions">
                                    <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="edit({{ $definition->id }})">
                                        {{ __('common.edit') }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
