<div class="admin-page">
    <div class="admin-page__header">
        <div>
            <h2 class="admin-page__heading">{{ $groupDefinition->label }}</h2>
            @if ($groupDefinition->description)
                <p class="admin-page__lede">{{ $groupDefinition->description }}</p>
            @endif
        </div>
    </div>

    <form wire:submit="save" class="admin-panel ag-form" novalidate>
        @foreach ($fields as $field)
            <div class="ag-field" wire:key="field-{{ $field->key }}">
                <label class="ag-field__label" for="setting-{{ $field->key }}">{{ $field->label }}</label>

                @if ($field->type === 'text')
                    <textarea
                        id="setting-{{ $field->key }}"
                        class="ag-input"
                        rows="4"
                        wire:model="values.{{ $field->key }}"
                        @disabled(! $canUpdate)
                    ></textarea>
                @elseif ($field->type === 'boolean')
                    <label class="ag-check">
                        <input type="checkbox" wire:model="values.{{ $field->key }}" @disabled(! $canUpdate)>
                        <span>Enabled</span>
                    </label>
                @elseif ($field->type === 'select')
                    <select
                        id="setting-{{ $field->key }}"
                        class="ag-input"
                        wire:model="values.{{ $field->key }}"
                        @disabled(! $canUpdate)
                    >
                        @foreach ($field->options ?? [] as $option)
                            <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                @elseif ($field->type === 'image')
                    @if (! empty($values[$field->key]))
                        <p class="ag-field__help">Current file: <code>{{ $values[$field->key] }}</code></p>
                    @endif
                    <input
                        id="setting-{{ $field->key }}"
                        class="ag-input"
                        type="file"
                        accept="image/*"
                        wire:model="uploads.{{ $field->key }}"
                        @disabled(! $canUpdate)
                    >
                    @error('uploads.'.$field->key) <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                @else
                    <input
                        id="setting-{{ $field->key }}"
                        class="ag-input"
                        type="text"
                        wire:model="values.{{ $field->key }}"
                        @disabled(! $canUpdate)
                    >
                @endif

                @if ($field->help)
                    <p class="ag-field__help">{{ $field->help }}</p>
                @endif
                @error('values.'.$field->key) <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
        @endforeach

        @if ($canUpdate)
            <div class="ag-form__actions">
                <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled">Save settings</button>
            </div>
        @else
            <p class="ag-field__help" role="status">You can view these settings but cannot change them.</p>
        @endif
    </form>
</div>
