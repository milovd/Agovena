{{-- Product form: details tab (basics, description, storefront cards). Part of livewire.admin.products.form. --}}
<div id="product-tab-details" role="tabpanel" x-cloak x-show="activeTab === 'details'">
<section class="ag-section" aria-labelledby="section-basic">
    <header class="ag-section__header ag-section__header--icon">
        <div class="ag-section__heading">
            <x-ag.icon-tile name="package" tone="blue" />
            <div>
                <h3 id="section-basic" class="ag-section__title">{{ __('admin.products.form.basic') }}</h3>
                <p class="ag-section__lede">{{ __('admin.products.form.basic_lede') }}</p>
            </div>
        </div>
    </header>
    <div class="ag-section__body">
        <div class="ag-grid ag-grid--2">
            <div class="ag-field ag-grid__span-2">
                <label class="ag-field__label" for="name">{{ __('common.name') }}</label>
                <input id="name" class="ag-input" type="text" wire:model="name" required>
                @error('name') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="slug">{{ __('common.slug') }}</label>
                <input id="slug" class="ag-input" type="text" wire:model="slug" aria-describedby="slug-hint">
                <p id="slug-hint" class="ag-field__hint">{{ __('admin.products.form.slug_hint') }}</p>
                @error('slug') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="sku">{{ __('admin.products.form.sku') }}</label>
                <input id="sku" class="ag-input" type="text" wire:model="sku" aria-describedby="sku-hint">
                <p id="sku-hint" class="ag-field__hint">{{ __('admin.products.form.sku_hint') }}</p>
                @error('sku') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="status">{{ __('common.status') }}</label>
                <select id="status" class="ag-select" wire:model="status">
                    <option value="draft">{{ __('common.draft') }}</option>
                    <option value="active">{{ __('common.active') }}</option>
                </select>
                <p class="ag-field__hint">{{ __('admin.products.form.status_hint') }}</p>
                @error('status') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="category_id">{{ __('common.category') }}</label>
                <select id="category_id" class="ag-select" wire:model="category_id">
                    <option value="">{{ __('common.none') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>
</section>

<section class="ag-section" aria-labelledby="section-description">
    <header class="ag-section__header ag-section__header--icon">
        <div class="ag-section__heading">
            <x-ag.icon-tile name="file-text" tone="violet" />
            <div>
                <h3 id="section-description" class="ag-section__title">{{ __('admin.products.form.description') }}</h3>
                <p class="ag-section__lede">{{ __('admin.products.form.description_lede') }}</p>
            </div>
        </div>
    </header>
    <div class="ag-section__body">
        <div class="ag-field">
            <label class="ag-field__label" for="subtitle">{{ __('admin.products.form.subtitle') }}</label>
            <input id="subtitle" class="ag-input" type="text" wire:model="subtitle" aria-describedby="subtitle-hint">
            <p id="subtitle-hint" class="ag-field__hint">{{ __('admin.products.form.subtitle_hint') }}</p>
            @error('subtitle') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div class="ag-field">
            <label class="ag-field__label" for="description">{{ __('admin.products.form.details') }}</label>
            <textarea id="description" class="ag-input ag-input--area" rows="6" wire:model="description"></textarea>
            @error('description') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div class="ag-switch-row">
            <x-ag.switch id="show_details" wire:model="show_details" :label="__('admin.products.form.show_details')" />
            <x-ag.switch id="show_specifications" wire:model="show_specifications" :label="__('admin.products.form.show_specifications')" />
        </div>
        <div class="ag-field">
            <div class="ag-field__label-row">
                <label class="ag-field__label">{{ __('admin.products.form.specifications') }}</label>
                <button type="button" class="ag-btn ag-btn--secondary ag-btn--sm" wire:click="addSpecRow">{{ __('admin.products.form.add_row') }}</button>
            </div>
            <p class="ag-field__hint">{{ __('admin.products.form.specifications_hint') }}</p>
            <div class="ag-spec-rows">
                @foreach ($specRows as $index => $row)
                    <div class="ag-spec-rows__row" wire:key="spec-{{ $index }}">
                        <input class="ag-input" type="text" placeholder="{{ __('admin.products.form.spec_label') }}" wire:model="specRows.{{ $index }}.label" aria-label="{{ __('admin.products.form.spec_label_aria', ['number' => $index + 1]) }}">
                        <input class="ag-input" type="text" placeholder="{{ __('admin.products.form.spec_value') }}" wire:model="specRows.{{ $index }}.value" aria-label="{{ __('admin.products.form.spec_value_aria', ['number' => $index + 1]) }}">
                        <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="removeSpecRow({{ $index }})" aria-label="{{ __('admin.products.form.remove_row') }}">{{ __('common.remove') }}</button>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
<section class="ag-section" aria-labelledby="section-storefront-cards">
    <header class="ag-section__header ag-section__header--icon">
        <div class="ag-section__heading">
            <x-ag.icon-tile name="store" tone="amber" />
            <div>
                <h3 id="section-storefront-cards" class="ag-section__title">{{ __('admin.products.form.storefront_cards') }}</h3>
                <p class="ag-section__lede">{{ __('admin.products.form.storefront_cards_lede') }}</p>
            </div>
        </div>
    </header>
    <div class="ag-section__body">
        <div class="ag-grid ag-grid--2">
            <div class="ag-field">
                <x-ag.switch id="show_delivery_card" wire:model="show_delivery_card" :label="__('admin.products.form.show_delivery_card')" />
                <label class="ag-field__label" for="delivery_title">{{ __('admin.products.form.delivery_card_title') }}</label>
                <input id="delivery_title" class="ag-input" type="text" wire:model="delivery_title" maxlength="120" placeholder="{{ __('storefront.product.delivery_title') }}">
                @error('delivery_title') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                <label class="ag-field__label" for="delivery_text">{{ __('admin.products.form.delivery_card_text') }}</label>
                <textarea id="delivery_text" class="ag-input ag-input--area" rows="3" wire:model="delivery_text" maxlength="1000" placeholder="{{ __('storefront.product.delivery_text') }}"></textarea>
                @error('delivery_text') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <x-ag.switch id="show_returns_card" wire:model="show_returns_card" :label="__('admin.products.form.show_returns_card')" />
                <label class="ag-field__label" for="returns_title">{{ __('admin.products.form.returns_card_title') }}</label>
                <input id="returns_title" class="ag-input" type="text" wire:model="returns_title" maxlength="120" placeholder="{{ __('storefront.product.returns_title') }}">
                @error('returns_title') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                <label class="ag-field__label" for="returns_text">{{ __('admin.products.form.returns_card_text') }}</label>
                <textarea id="returns_text" class="ag-input ag-input--area" rows="3" wire:model="returns_text" maxlength="1000" placeholder="{{ __('storefront.product.returns_text') }}"></textarea>
                @error('returns_text') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>
</section>
</div>
