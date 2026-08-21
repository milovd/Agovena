@if (($purchaseOptions ?? collect())->isNotEmpty())
    <div class="store-product__options">
        @foreach ($purchaseOptions as $option)
            <div class="store-field" wire:key="option-{{ $option->id }}">
                <span class="store-label">
                    {{ $option->label }}@if ($option->is_required) * @endif
                </span>
                @if ($option->type->value === 'select')
                    <select class="store-input" wire:model.live="optionSelections.{{ $option->key }}">
                        <option value="">{{ __('storefront.product.choose_option') }}</option>
                        @foreach ($option->choices->where('is_active', true) as $choice)
                            <option value="{{ $choice->value }}">
                                {{ $choice->label }}
                                @if ($choice->price_adjustment_amount > 0)
                                    (+{{ \App\Support\MoneyFormatter::formatDisplay($choice->price_adjustment_amount, $product->currency) }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                @elseif ($option->type->value === 'radio')
                    @foreach ($option->choices->where('is_active', true) as $choice)
                        <label class="store-check">
                            <input type="radio" value="{{ $choice->value }}" wire:model.live="optionSelections.{{ $option->key }}">
                            <span>
                                {{ $choice->label }}
                                @if ($choice->price_adjustment_amount > 0)
                                    (+{{ \App\Support\MoneyFormatter::formatDisplay($choice->price_adjustment_amount, $product->currency) }})
                                @endif
                            </span>
                        </label>
                    @endforeach
                @elseif ($option->type->value === 'checkbox')
                    @foreach ($option->choices->where('is_active', true) as $choice)
                        <label class="store-check">
                            <input type="checkbox" value="{{ $choice->value }}" wire:model.live="optionSelections.{{ $option->key }}">
                            <span>
                                {{ $choice->label }}
                                @if ($choice->price_adjustment_amount > 0)
                                    (+{{ \App\Support\MoneyFormatter::formatDisplay($choice->price_adjustment_amount, $product->currency) }})
                                @endif
                            </span>
                        </label>
                    @endforeach
                @elseif ($option->type->value === 'toggle')
                    <label class="store-check">
                        <input type="checkbox" wire:model.live="optionSelections.{{ $option->key }}">
                        <span>
                            {{ __('storefront.product.enable_option') }}
                            @if ($option->price_adjustment_amount > 0)
                                (+{{ \App\Support\MoneyFormatter::formatDisplay($option->price_adjustment_amount, $product->currency) }})
                            @endif
                        </span>
                    </label>
                @elseif ($option->type->value === 'number')
                    <input class="store-input" type="number" wire:model.live="optionSelections.{{ $option->key }}">
                @else
                    <input class="store-input" type="text" wire:model.live="optionSelections.{{ $option->key }}">
                @endif
                @error('optionSelections.'.$option->key) <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
        @endforeach
    </div>
@endif
