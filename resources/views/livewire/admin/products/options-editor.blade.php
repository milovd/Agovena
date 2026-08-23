<section class="ag-section" aria-labelledby="section-options">
    <header class="ag-section__header">
        <h3 id="section-options" class="ag-section__title">{{ __('admin.product_options.title') }}</h3>
        <p class="ag-section__lede">{{ __('admin.product_options.lede') }}</p>
    </header>
    <div class="ag-section__body">
        @if ($showForm)
            <form wire:submit="save" class="ag-form" novalidate>
                <div class="ag-grid ag-grid--2">
                    <div class="ag-field">
                        <label class="ag-field__label" for="opt-label">{{ __('admin.product_options.field_label') }}</label>
                        <input id="opt-label" class="ag-input" wire:model="label" required>
                        @error('label') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="opt-key">{{ __('admin.product_options.key') }}</label>
                        <input id="opt-key" class="ag-input" wire:model="key" required>
                        <p class="ag-field__hint">{{ __('admin.product_options.provisioning_key_hint') }}</p>
                        @error('key') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="opt-type">{{ __('admin.product_options.type') }}</label>
                        <select id="opt-type" class="ag-select" wire:model.live="type">
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}">{{ __('admin.product_options.types.'.$type->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="opt-sort">{{ __('admin.product_options.sort') }}</label>
                        <input id="opt-sort" class="ag-input" type="number" min="0" wire:model.number="sort">
                    </div>
                </div>
                @if (in_array($type, ['select', 'radio', 'checkbox'], true))
                    <div class="ag-field">
                        <label class="ag-field__label" for="opt-choices">{{ __('admin.product_options.choices') }}</label>
                        <textarea id="opt-choices" class="ag-input" rows="5" wire:model="choicesText"></textarea>
                        <p class="ag-field__help">{{ __('admin.product_options.choices_help') }}</p>
                        @error('choicesText') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div class="ag-field">
                        <label class="ag-field__label" for="opt-price">{{ __('admin.product_options.price_adjustment') }}</label>
                        <input id="opt-price" class="ag-input" type="number" min="0" wire:model.number="price_adjustment_amount">
                        <p class="ag-field__help">{{ __('admin.product_options.price_adjustment_help') }}</p>
                    </div>
                @endif
                <x-ag.switch id="opt-required" wire:model="is_required" :label="__('admin.product_options.required')" />
                <x-ag.switch id="opt-active" wire:model="is_active" :label="__('common.active')" />
                <div class="ag-form__actions">
                    <button class="ag-btn ag-btn--primary" type="submit">{{ __('common.save') }}</button>
                    <button class="ag-btn ag-btn--secondary" type="button" wire:click="cancel">{{ __('common.cancel') }}</button>
                </div>
            </form>
        @endif

        <p>
            <button type="button" class="ag-btn ag-btn--secondary" wire:click="create">{{ __('admin.product_options.add') }}</button>
        </p>

        @if ($options->isEmpty())
            <p class="ag-muted">{{ __('admin.product_options.empty') }}</p>
        @else
            <div class="ag-table-wrap">
                <table class="ag-table">
                    <thead><tr>
                        <th>{{ __('admin.product_options.field_label') }}</th>
                        <th>{{ __('admin.product_options.key') }}</th>
                        <th>{{ __('admin.product_options.type') }}</th>
                        <th>{{ __('common.status') }}</th>
                        <th><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                    </tr></thead>
                    <tbody>
                        @foreach ($options as $option)
                            <tr wire:key="option-{{ $option->id }}">
                                <td>{{ $option->label }}</td>
                                <td><code>{{ $option->key }}</code></td>
                                <td>{{ __('admin.product_options.types.'.$option->type->value) }}</td>
                                <td>{{ $option->is_active ? __('common.active') : __('common.inactive') }}</td>
                                <td>
                                    <button class="ag-btn ag-btn--ghost" type="button" wire:click="edit({{ $option->id }})">{{ __('common.edit') }}</button>
                                    <button class="ag-btn ag-btn--ghost" type="button" wire:click="delete({{ $option->id }})" wire:confirm="{{ __('admin.product_options.delete_confirm') }}">{{ __('common.delete') }}</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
