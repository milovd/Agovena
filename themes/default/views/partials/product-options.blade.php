@if (($purchaseOptions ?? collect())->isNotEmpty())
    <div class="store-product__options">
        @foreach ($purchaseOptions as $option)
            @php
                $providerChoices = ($dynamicOptionChoices ?? [])[$option->key] ?? null;
                $optionChoices = $providerChoices ?? $option->choices->where('is_active', true)->map(static fn ($choice) => [
                    'value' => (string) $choice->value,
                    'label' => (string) $choice->label,
                    'price_adjustment_amount' => (int) $choice->price_adjustment_amount,
                ]);
            @endphp
            <div class="store-field" wire:key="option-{{ $option->id }}">
                @if ($option->type->value === 'slider')
                    @php
                        $sliderStops = collect($optionChoices)->values()->map(static fn (array $choice): array => [
                            'value' => (string) $choice['value'],
                            'label' => (string) $choice['label'],
                            'price' => (int) $choice['price_adjustment_amount'] > 0
                                ? '+'.\App\Support\MoneyFormatter::formatDisplay((int) $choice['price_adjustment_amount'], $product->currency)
                                : '',
                        ]);
                        if (! $option->is_required) {
                            $sliderStops->prepend(['value' => '', 'label' => __('common.none'), 'price' => '']);
                        }
                        $sliderSelected = (string) ($optionSelections[$option->key] ?? '');
                        $sliderFound = $sliderStops->search(static fn (array $stop): bool => $stop['value'] === $sliderSelected);
                        $sliderIndex = $sliderFound === false ? 0 : (int) $sliderFound;
                        $sliderMax = max(0, $sliderStops->count() - 1);
                        $sliderFill = $sliderMax > 0 ? round($sliderIndex / $sliderMax * 100, 4) : 0;
                        $sliderCurrent = $sliderStops->get($sliderIndex);
                        $sliderId = 'option-slider-'.$option->id;
                    @endphp
                    <div class="store-slider__header">
                        <label class="store-label" for="{{ $sliderId }}">
                            {{ $option->label }}@if ($option->is_required) * @endif
                        </label>
                        @if ($sliderCurrent)
                            <output class="store-slider__value" for="{{ $sliderId }}" data-slider-output>{{ $sliderCurrent['label'] }}</output>
                        @endif
                    </div>
                @else
                    <span class="store-label">
                        {{ $option->label }}@if ($option->is_required) * @endif
                    </span>
                @endif
                @if ($option->type->value === 'slider')
                    @if ($sliderStops->isNotEmpty())
                        <div
                            class="store-slider"
                            x-data="storefrontOptionSlider"
                            data-option-key="{{ $option->key }}"
                            data-stops='@json($sliderStops->all())'
                            data-count="{{ $sliderStops->count() }}"
                        >
                            <input
                                id="{{ $sliderId }}"
                                class="store-slider__input"
                                type="range"
                                min="0"
                                max="{{ $sliderMax }}"
                                step="1"
                                value="{{ $sliderIndex }}"
                                data-index="{{ $sliderIndex }}"
                                aria-valuetext="{{ $sliderCurrent['label'] }}"
                                style="--fill: {{ $sliderFill }}%"
                                x-ref="input"
                                x-on:input="preview"
                                x-on:change="commit"
                                @disabled($sliderMax === 0)
                            >
                            <div class="store-slider__ticks" aria-hidden="true">
                                @foreach ($sliderStops as $stopIndex => $stop)
                                    <span
                                        @class(['store-slider__tick', 'is-active' => $stopIndex === $sliderIndex])
                                        style="--pos: {{ $sliderMax > 0 ? round($stopIndex / $sliderMax * 100, 4) : 0 }}%"
                                        data-index="{{ $stopIndex }}"
                                        x-on:click="pick"
                                    >
                                        <span class="store-slider__tick-label">{{ $stop['label'] }}</span>
                                        @if ($stop['price'] !== '')
                                            <span class="store-slider__tick-price">{{ $stop['price'] }}</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @elseif ($option->type->value === 'select')
                    <select class="store-input" wire:model.live="optionSelections.{{ $option->key }}">
                        <option value="">{{ __('storefront.product.choose_option') }}</option>
                        @foreach ($optionChoices as $choice)
                            <option value="{{ $choice['value'] }}">
                                {{ $choice['label'] }}
                                @if ($choice['price_adjustment_amount'] > 0)
                                    (+{{ \App\Support\MoneyFormatter::formatDisplay($choice['price_adjustment_amount'], $product->currency) }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                @elseif ($option->type->value === 'radio')
                    @foreach ($optionChoices as $choice)
                        <label class="store-check">
                            <input type="radio" value="{{ $choice['value'] }}" wire:model.live="optionSelections.{{ $option->key }}">
                            <span>
                                {{ $choice['label'] }}
                                @if ($choice['price_adjustment_amount'] > 0)
                                    (+{{ \App\Support\MoneyFormatter::formatDisplay($choice['price_adjustment_amount'], $product->currency) }})
                                @endif
                            </span>
                        </label>
                    @endforeach
                @elseif ($option->type->value === 'checkbox')
                    @foreach ($optionChoices as $choice)
                        <label class="store-check">
                            <input type="checkbox" value="{{ $choice['value'] }}" wire:model.live="optionSelections.{{ $option->key }}">
                            <span>
                                {{ $choice['label'] }}
                                @if ($choice['price_adjustment_amount'] > 0)
                                    (+{{ \App\Support\MoneyFormatter::formatDisplay($choice['price_adjustment_amount'], $product->currency) }})
                                @endif
                            </span>
                        </label>
                    @endforeach
                @elseif ($option->type->value === 'toggle')
                    <label class="store-check">
                        <input type="checkbox" role="switch" wire:model.live="optionSelections.{{ $option->key }}">
                        <span>
                            {{ __('storefront.product.enable_option') }}
                            @if ($option->price_adjustment_amount > 0)
                                (+{{ \App\Support\MoneyFormatter::formatDisplay($option->price_adjustment_amount, $product->currency) }})
                            @endif
                        </span>
                    </label>
                @elseif ($option->type->value === 'number')
                    <input class="store-input" type="number" wire:model.live="optionSelections.{{ $option->key }}">
                @elseif ($option->type->value === 'textarea')
                    <textarea class="store-input" rows="5" maxlength="{{ (int) ($option->constraints['max_length'] ?? 5000) }}" wire:model.live="optionSelections.{{ $option->key }}"></textarea>
                @else
                    <input class="store-input" type="text" wire:model.live="optionSelections.{{ $option->key }}">
                @endif
                @if (isset(($optionChoiceErrors ?? [])[$option->key]))
                    <p class="store-field__error" role="alert">{{ $optionChoiceErrors[$option->key] }}</p>
                @elseif (isset(($optionHints ?? [])[$option->key]))
                    <p class="store-field__hint">{{ $optionHints[$option->key] }}</p>
                @endif
                @error('optionSelections.'.$option->key) <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
        @endforeach
    </div>
@endif
