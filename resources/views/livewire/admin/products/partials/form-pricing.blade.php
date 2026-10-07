{{-- Product form: pricing tab. Part of livewire.admin.products.form. --}}
<div id="product-tab-pricing" role="tabpanel" x-cloak x-show="activeTab === 'pricing'">
<section class="ag-section" aria-labelledby="section-pricing">
    <header class="ag-section__header">
        <h3 id="section-pricing" class="ag-section__title">{{ __('admin.products.form.pricing') }}</h3>
        <p class="ag-section__lede">{{ __('admin.products.form.pricing_lede') }}</p>
    </header>
    <div class="ag-section__body">
        <div class="ag-grid ag-grid--2">
            <div class="ag-field">
                <label class="ag-field__label" for="price">{{ __('common.price') }}</label>
                <input
                    id="price"
                    class="ag-input"
                    type="text"
                    inputmode="decimal"
                    wire:model="price"
                    required
                    aria-describedby="price-hint"
                    placeholder="45.00"
                >
                <p id="price-hint" class="ag-field__hint">{{ __('admin.products.form.price_hint') }}</p>
                @error('price') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="currency">{{ __('admin.products.form.currency') }}</label>
                @if ($currencies->isNotEmpty())
                    <select id="currency" class="ag-select" wire:model="currency">
                        @foreach ($currencies as $currencyOption)
                            <option value="{{ $currencyOption->code }}">{{ $currencyOption->code }} - {{ $currencyOption->name }}</option>
                        @endforeach
                    </select>
                @else
                    <input id="currency" class="ag-input" type="text" maxlength="3" wire:model="currency" required>
                @endif
                @error('currency') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
        </div>
        @if ($mode === 'edit' && $currencies->count() > 1)
            <div class="ag-field" style="margin-top: 1rem;">
                <p class="ag-field__label">{{ __('admin.products.form.currency_overrides') }}</p>
                <p class="ag-field__hint">{{ __('admin.products.form.currency_overrides_hint') }}</p>
                <div class="ag-grid ag-grid--2" style="margin-top: 0.75rem;">
                    @foreach ($currencies as $currencyOption)
                        @continue(strtoupper($currencyOption->code) === strtoupper($currency))
                        <div class="ag-field" wire:key="currency-price-{{ $currencyOption->code }}">
                            <label class="ag-field__label" for="currency-price-{{ $currencyOption->code }}">
                                {{ $currencyOption->code }} - {{ $currencyOption->name }}
                            </label>
                            <input
                                id="currency-price-{{ $currencyOption->code }}"
                                class="ag-input"
                                type="text"
                                inputmode="decimal"
                                wire:model="currencyPrices.{{ $currencyOption->code }}"
                                placeholder="{{ __('admin.products.form.currency_override_placeholder') }}"
                            >
                            @error('currencyPrices.'.$currencyOption->code)
                                <p class="ag-field__error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
</div>
