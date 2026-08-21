@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\CustomerPropertyDefinition> $propertyDefinitions */
    $propertyDefinitions = $propertyDefinitions ?? collect();
    $countries = $countries ?? \App\Agovena\Support\CountryList::options();
    $editable = $propertyEditable ?? true;
    $modelPrefix = $propertyModelPrefix ?? 'propertyValues';
@endphp
@foreach ($propertyDefinitions as $definition)
    @php
        $fieldId = $modelPrefix.'-'.$definition->key;
        $canEdit = $editable && ($actor === 'staff' ? $definition->staff_editable : $definition->customer_editable);
        $type = $definition->type->value;
    @endphp
    <div class="{{ $fieldClass ?? 'store-field' }}" wire:key="{{ $fieldId }}">
        @if ($type === 'checkbox')
            <label class="{{ $checkClass ?? 'store-check' }}" for="{{ $fieldId }}">
                <input
                    id="{{ $fieldId }}"
                    type="checkbox"
                    @disabled(! $canEdit)
                    @if ($definition->is_required) required @endif
                    wire:model="{{ $modelPrefix }}.{{ $definition->key }}"
                >
                <span>{{ $definition->label }}</span>
            </label>
        @else
            <label class="{{ $labelClass ?? 'store-label' }}" for="{{ $fieldId }}">
                {{ $definition->label }}
            </label>
            @if ($type === 'textarea')
                <textarea
                    id="{{ $fieldId }}"
                    class="{{ $inputClass ?? 'store-input' }}"
                    rows="3"
                    @disabled(! $canEdit)
                    @if ($definition->is_required) required @endif
                    wire:model="{{ $modelPrefix }}.{{ $definition->key }}"
                ></textarea>
            @elseif ($type === 'select' || $type === 'country')
                <select
                    id="{{ $fieldId }}"
                    class="{{ $inputClass ?? 'store-input' }}"
                    @disabled(! $canEdit)
                    @if ($definition->is_required) required @endif
                    wire:model="{{ $modelPrefix }}.{{ $definition->key }}"
                >
                    <option value="">{{ __('common.none') }}</option>
                    @if ($type === 'country')
                        @foreach ($countries as $code => $name)
                            <option value="{{ $code }}">{{ $name }}</option>
                        @endforeach
                    @else
                        @foreach ($definition->options ?? [] as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    @endif
                </select>
            @else
                <input
                    id="{{ $fieldId }}"
                    class="{{ $inputClass ?? 'store-input' }}"
                    type="{{ $type === 'email' ? 'email' : ($type === 'number' ? 'number' : ($type === 'date' ? 'date' : ($type === 'phone' ? 'tel' : 'text'))) }}"
                    @disabled(! $canEdit)
                    @if ($definition->is_required) required @endif
                    wire:model="{{ $modelPrefix }}.{{ $definition->key }}"
                >
            @endif
        @endif
        @error($modelPrefix.'.'.$definition->key) <p class="{{ $errorClass ?? 'store-field__error' }}" role="alert">{{ $message }}</p> @enderror
    </div>
@endforeach
