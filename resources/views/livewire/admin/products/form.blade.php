<div class="admin-page admin-page--form" x-data="{ activeTab: 'details' }">
    <x-ag.page-header
        :heading="$mode === 'create' ? __('admin.products.form.create_title') : __('admin.products.form.edit_title')"
        :lede="$mode === 'create' ? __('admin.products.form.create_lede') : __('admin.products.form.edit_lede')"
    >
        <x-slot:breadcrumbs>
            <x-ag.breadcrumbs :items="[
                ['label' => __('admin.nav_groups.overview'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.products.title'), 'url' => route('admin.products.index')],
                ['label' => $mode === 'create' ? __('admin.products.form.create_title') : $product->name],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:back>
            <x-ag.back :href="route('admin.products.index')" :label="__('admin.products.title')" />
        </x-slot:back>
        <x-slot:actions>
            @if ($mode === 'edit')
                @if ($product->status->value === 'active')
                    <a
                        class="ag-btn ag-btn--secondary"
                        href="{{ route('storefront.product', $product->slug) }}"
                        target="_blank"
                        rel="noopener"
                    >
                        <x-ag.icon name="eye" :size="16" />
                        {{ __('admin.products.actions.preview') }}
                    </a>
                @else
                    <span
                        class="ag-btn ag-btn--secondary is-disabled"
                        title="{{ __('admin.products.actions.preview_disabled') }}"
                        aria-disabled="true"
                    >
                        <x-ag.icon name="eye" :size="16" />
                        {{ __('admin.products.actions.preview') }}
                    </span>
                @endif
            @endif
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    @php
        $availableCapabilityKeys = collect($availableCapabilities ?? [])->pluck('key')->all();
    @endphp

    <nav class="ag-product-tabs" role="tablist" aria-label="{{ __('admin.products.tabs.aria') }}">
        @foreach (['details', 'pricing'] as $tab)
            <button
                type="button"
                class="ag-product-tabs__tab"
                :class="{ 'is-active': activeTab === '{{ $tab }}' }"
                role="tab"
                :aria-selected="(activeTab === '{{ $tab }}').toString()"
                aria-controls="product-tab-{{ $tab }}"
                @click="activeTab = '{{ $tab }}'"
            >{{ __('admin.products.tabs.'.$tab) }}</button>
        @endforeach
        @if ($mode === 'edit')
            <button type="button" class="ag-product-tabs__tab" :class="{ 'is-active': activeTab === 'media' }" role="tab" :aria-selected="(activeTab === 'media').toString()" aria-controls="product-tab-media" @click="activeTab = 'media'">{{ __('admin.products.tabs.media') }}</button>
        @endif
        @if ($availableCapabilityKeys !== [])
            <button type="button" class="ag-product-tabs__tab" :class="{ 'is-active': activeTab === 'automation' }" role="tab" :aria-selected="(activeTab === 'automation').toString()" aria-controls="product-tab-automation" @click="activeTab = 'automation'">{{ __('admin.products.tabs.automation') }}</button>
        @endif
        @if ($mode === 'edit')
            <button type="button" class="ag-product-tabs__tab" :class="{ 'is-active': activeTab === 'options' }" role="tab" :aria-selected="(activeTab === 'options').toString()" aria-controls="product-tab-options" @click="activeTab = 'options'">{{ __('admin.products.tabs.options') }}</button>
            @foreach ($productTabs ?? [] as $productTab)
                <button
                    type="button"
                    class="ag-product-tabs__tab"
                    :class="{ 'is-active': activeTab === '{{ $productTab->id }}' }"
                    role="tab"
                    :aria-selected="(activeTab === '{{ $productTab->id }}').toString()"
                    aria-controls="product-tab-{{ $productTab->id }}"
                    @click="activeTab = '{{ $productTab->id }}'"
                >{{ __($productTab->label) }}</button>
            @endforeach
        @endif
    </nav>

    <form id="product-form" wire:submit="save" class="ag-form ag-form--product" novalidate>
        <div id="product-tab-details" role="tabpanel" x-cloak x-show="activeTab === 'details'">
        <section class="ag-section" aria-labelledby="section-basic">
            <header class="ag-section__header">
                <h3 id="section-basic" class="ag-section__title">{{ __('admin.products.form.basic') }}</h3>
                <p class="ag-section__lede">{{ __('admin.products.form.basic_lede') }}</p>
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
            <header class="ag-section__header">
                <h3 id="section-description" class="ag-section__title">{{ __('admin.products.form.description') }}</h3>
                <p class="ag-section__lede">{{ __('admin.products.form.description_lede') }}</p>
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
        </div>

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
                                    <option value="{{ $currencyOption->code }}">{{ $currencyOption->code }} — {{ $currencyOption->name }}</option>
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
                                        {{ $currencyOption->code }} — {{ $currencyOption->name }}
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

        @if ($mode === 'create' && ($canConfigureProvisioning ?? false))
            <div id="product-tab-automation" role="tabpanel" x-cloak x-show="activeTab === 'automation'">
                <section class="ag-section" aria-labelledby="section-create-automation">
                    <header class="ag-section__header">
                        <h3 id="section-create-automation" class="ag-section__title">{{ __('admin.products.automation.title') }}</h3>
                        <p class="ag-section__lede">{{ __('admin.products.automation.lede') }}</p>
                    </header>
                    <div class="ag-section__body">
                        <x-ag.checkbox
                            id="configure-provisioning"
                            wire:model.live="configureProvisioning"
                            :label="__('admin.products.automation.enable_provisioning')"
                        />
                        @if ($configureProvisioning)
                            <div class="ag-provider-settings">
                                <div class="ag-field">
                                    <label class="ag-field__label" for="create-provisioning-server">{{ __('admin.products.automation.server') }}</label>
                                    <select id="create-provisioning-server" class="ag-select" wire:model.live="provisioningServerId" required>
                                        <option value="">{{ __('admin.products.automation.select_server') }}</option>
                                        @foreach ($provisioningServers ?? [] as $server)
                                            <option value="{{ $server->id }}">{{ $server->name }}</option>
                                        @endforeach
                                    </select>
                                    @if (($provisioningServers ?? collect())->isEmpty())
                                        <p class="ag-field__hint"><a href="{{ route('admin.provisioning.servers') }}">{{ __('admin.products.automation.configure_server_first') }}</a></p>
                                    @endif
                                    @error('provisioningServerId') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                                </div>
                                <div class="ag-alert ag-alert--info" role="status">
                                    <div class="ag-alert__body">
                                        <p class="ag-alert__title">{{ $providerKey !== '' ? ucfirst($providerKey) : __('admin.products.automation.provider') }}</p>
                                        <p class="ag-alert__text">{{ __('admin.products.automation.provider_hint') }}</p>
                                    </div>
                                </div>
                                <div class="ag-grid ag-grid--2">
                                    @foreach ($providerSettingDefinitions ?? [] as $definition)
                                        <div class="ag-field {{ $definition->type === 'text' ? 'ag-grid__span-2' : '' }}">
                                            <label class="ag-field__label" for="create-provider-setting-{{ $definition->key }}">{{ __($definition->label) }}</label>
                                            @if ($definition->type === 'text')
                                                <textarea id="create-provider-setting-{{ $definition->key }}" class="ag-input" rows="5" wire:model="providerSettings.{{ $definition->key }}"></textarea>
                                            @else
                                                <input id="create-provider-setting-{{ $definition->key }}" class="ag-input" type="text" wire:model="providerSettings.{{ $definition->key }}" @required($definition->required)>
                                            @endif
                                            @if ($definition->help !== '')
                                                <p class="ag-field__hint">{{ __($definition->help) }}</p>
                                            @endif
                                            @error('providerSettings.'.$definition->key) <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </section>
            </div>
        @endif

    </form>

    @if ($mode === 'edit')
        <section id="product-tab-media" class="ag-section ag-form--product" role="tabpanel" x-cloak x-show="activeTab === 'media'" aria-labelledby="section-media">
            <header class="ag-section__header">
                <h3 id="section-media" class="ag-section__title">{{ __('admin.products.form.media') }}</h3>
                <p class="ag-section__lede">{{ __('admin.products.form.media_lede') }}</p>
            </header>
            <div class="ag-section__body">
                <x-ag.file-upload
                    id="product-uploads"
                    :label="__('admin.products.form.add_photos')"
                    :hint="__('admin.products.form.photos_hint')"
                    multiple
                    :button-label="__('admin.products.form.upload_photos')"
                    :replace-label="__('admin.products.form.upload_more')"
                    loading-target="uploads"
                    wire:model="uploads"
                >
                    @error('uploads') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    @error('uploads.*') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </x-ag.file-upload>

                @if ($galleryImages->isNotEmpty())
                    <ul class="ag-gallery-admin" role="list">
                        @foreach ($galleryImages as $image)
                            @php $isPrimary = $product->image_path === $image->path; @endphp
                            <li class="ag-media-tile {{ $isPrimary ? 'is-primary' : '' }}" wire:key="img-{{ $image->id }}">
                                <div class="ag-media-tile__preview">
                                    @php $previewUrl = \App\Agovena\Media\PublicMedia::url($image->path); @endphp
                                    @if ($previewUrl)
                                        <img src="{{ $previewUrl }}" alt="" width="112" height="112">
                                    @endif
                                    @if ($isPrimary)
                                        <span class="ag-media-tile__badge">{{ __('admin.products.form.primary_badge') }}</span>
                                    @endif
                                </div>
                                <div class="ag-media-tile__toolbar">
                                    <div class="ag-media-tile__tools">
                                        <button type="button" class="ag-icon-btn" wire:click="moveImage({{ $image->id }}, 'up')" title="{{ __('admin.products.form.move_earlier') }}" aria-label="{{ __('admin.products.form.move_earlier_aria') }}">
                                            <x-ag.icon name="chevron-up" :size="16" />
                                        </button>
                                        <button type="button" class="ag-icon-btn" wire:click="moveImage({{ $image->id }}, 'down')" title="{{ __('admin.products.form.move_later') }}" aria-label="{{ __('admin.products.form.move_later_aria') }}">
                                            <x-ag.icon name="chevron-down" :size="16" />
                                        </button>
                                    </div>
                                    <div
                                        class="ag-menu"
                                        x-data="{ open: false }"
                                        @keydown.escape.window="open = false"
                                        @click.outside="open = false"
                                    >
                                        <button
                                            type="button"
                                            class="ag-icon-btn"
                                            @click="open = !open"
                                            :aria-expanded="open.toString()"
                                            aria-haspopup="menu"
                                            title="{{ __('admin.products.form.photo_actions') }}"
                                            aria-label="{{ __('admin.products.form.photo_actions') }}"
                                        >
                                            <x-ag.icon name="more-horizontal" :size="16" />
                                        </button>
                                        <div class="ag-menu__panel" x-show="open" x-cloak role="menu">
                                            @unless ($isPrimary)
                                                <button type="button" class="ag-menu__item" role="menuitem" wire:click="setPrimaryImage({{ $image->id }})">
                                                    {{ __('admin.products.form.set_primary') }}
                                                </button>
                                            @endunless
                                            <button
                                                type="button"
                                                class="ag-menu__item ag-menu__item--danger"
                                                role="menuitem"
                                                wire:click="removeImage({{ $image->id }})"
                                                wire:confirm="{{ __('admin.products.form.remove_photo_confirm') }}"
                                            >
                                                {{ __('common.remove') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="ag-empty ag-empty--compact" role="status">{{ __('admin.products.form.no_photos') }}</p>
                @endif
            </div>
        </section>

        @if ($availableCapabilityKeys !== [])
            <section id="product-tab-automation" class="ag-section" role="tabpanel" x-cloak x-show="activeTab === 'automation'" aria-labelledby="section-capabilities">
                <header class="ag-section__header">
                <h3 id="section-capabilities" class="ag-section__title">{{ __('admin.products.presets.title') }}</h3>
                <p class="ag-section__lede">{{ __('admin.products.presets.lede') }}</p>
            </header>
            <div class="ag-section__body">

                <div class="ag-preset-grid" role="group" aria-label="{{ __('admin.products.presets.aria') }}">
                    @foreach (['simple', 'physical', 'digital', 'downloadable', 'subscription', 'hosted_service', 'event_ticket'] as $preset)
                        @php
                            $requiredCapability = match ($preset) {
                                'physical' => 'physical',
                                'digital' => 'digital_secret',
                                'downloadable' => 'digital',
                                'subscription' => 'subscribable',
                                'hosted_service' => 'provisionable',
                                'event_ticket' => 'event_ticket',
                                default => null,
                            };
                            $isAvailable = $requiredCapability === null || in_array($requiredCapability, $availableCapabilityKeys, true);
                        @endphp
                        <button
                            type="button"
                            class="ag-preset {{ $sellingPreset === $preset ? 'is-active' : '' }}"
                            wire:click="applyPreset('{{ $preset }}')"
                            @disabled(! $isAvailable)
                        >
                            <strong>{{ __('admin.products.presets.'.$preset.'.label') }}</strong>
                            <span>{{ __('admin.products.presets.'.$preset.'.help') }}</span>
                        </button>
                    @endforeach
                </div>

                @if (! empty($capabilityEnabled['digital_secret']))
                    <div class="ag-preset-option ag-grid ag-grid--2" style="margin-top: 1rem;">
                        <div class="ag-field">
                            <label class="ag-field__label" for="digital-secret-source">{{ __('admin.products.capabilities.digital_secret_source') }}</label>
                            <select id="digital-secret-source" class="ag-select" wire:model="digitalSecretSource">
                                <option value="pool">{{ __('admin.products.capabilities.digital_secret_source_pool') }}</option>
                                <option value="manual">{{ __('admin.products.capabilities.digital_secret_source_manual') }}</option>
                                <option value="provider">{{ __('admin.products.capabilities.digital_secret_source_provider') }}</option>
                            </select>
                            <p class="ag-field__hint">{{ __('admin.products.capabilities.digital_secret_source_hint') }}</p>
                        </div>
                    </div>
                @endif

                @if (in_array('provisionable', $availableCapabilityKeys, true))
                    <div class="ag-preset-option">
                        <x-ag.checkbox
                            id="hosted-service-subscription"
                            wire:model.live="hostedServiceSubscription"
                            :label="__('admin.products.presets.hosted_service.also_subscription')"
                        />
                    </div>
                @endif

                @if (($availableCapabilities ?? []) === [])
                    <p class="ag-muted">{{ __('admin.products.capabilities.none') }}</p>
                @else
                    <details class="ag-advanced">
                        <summary class="ag-advanced__summary">{{ __('admin.products.presets.advanced') }}</summary>
                        <p class="ag-field__hint">{{ __('admin.products.presets.advanced_help') }}</p>
                        <div class="ag-stack ag-advanced__body">
                            @foreach ($availableCapabilities as $definition)
                                <div class="ag-field">
                                    <x-ag.checkbox
                                        :id="'capability-'.$definition->key"
                                        wire:model="capabilityEnabled.{{ $definition->key }}"
                                        :label="__($definition->label)"
                                    />
                                    @if ($definition->description !== '')
                                        <p class="ag-field__hint">{{ __($definition->description) }}</p>
                                    @endif
                                </div>
                            @endforeach

                            @if (! empty($capabilityEnabled['inventory']))
                                <div class="ag-field">
                                    <label class="ag-field__label" for="stockQuantity">{{ __('admin.products.capabilities.stock_quantity') }}</label>
                                    <input id="stockQuantity" class="ag-input" type="number" min="0" wire:model="stockQuantity">
                                    <p class="ag-field__hint">{{ __('admin.products.capabilities.stock_hint') }}</p>
                                </div>
                            @endif

                            @if (! empty($capabilityEnabled['shippable']))
                                <div class="ag-field">
                                    <label class="ag-field__label" for="weightGrams">{{ __('admin.products.capabilities.weight_grams') }}</label>
                                    <input id="weightGrams" class="ag-input" type="number" min="0" wire:model="weightGrams">
                                    <p class="ag-field__hint">{{ __('admin.products.capabilities.weight_hint') }}</p>
                                </div>
                            @endif

                            @if (! empty($capabilityEnabled['subscribable']))
                                <div class="ag-grid ag-grid--2">
                                    <div class="ag-field">
                                        <label class="ag-field__label" for="subscriptionInterval">{{ __('admin.products.capabilities.subscription_interval') }}</label>
                                        <select id="subscriptionInterval" class="ag-select" wire:model="subscriptionInterval">
                                            <option value="day">{{ __('admin.products.capabilities.interval_day') }}</option>
                                            <option value="week">{{ __('admin.products.capabilities.interval_week') }}</option>
                                            <option value="month">{{ __('admin.products.capabilities.interval_month') }}</option>
                                            <option value="year">{{ __('admin.products.capabilities.interval_year') }}</option>
                                        </select>
                                    </div>
                                    <div class="ag-field">
                                        <label class="ag-field__label" for="subscriptionIntervalCount">{{ __('admin.products.capabilities.subscription_interval_count') }}</label>
                                        <input id="subscriptionIntervalCount" class="ag-input" type="number" min="1" wire:model="subscriptionIntervalCount">
                                        <p class="ag-field__hint">{{ __('admin.products.capabilities.subscription_interval_hint') }}</p>
                                    </div>
                                    <div class="ag-field">
                                        <label class="ag-field__label" for="subscriptionTrialDays">{{ __('admin.products.capabilities.subscription_trial_days') }}</label>
                                        <input id="subscriptionTrialDays" class="ag-input" type="number" min="0" wire:model="subscriptionTrialDays">
                                        <p class="ag-field__hint">{{ __('admin.products.capabilities.subscription_trial_hint') }}</p>
                                    </div>
                                </div>
                            @endif

                            @if (! empty($capabilityEnabled['provisionable']))
                                <div class="ag-field">
                                    <label class="ag-field__label" for="provisioningServerId">{{ __('admin.products.automation.server') }}</label>
                                    <select id="provisioningServerId" class="ag-select" wire:model.live="provisioningServerId">
                                        <option value="">{{ __('admin.products.automation.select_server') }}</option>
                                        @foreach ($provisioningServers ?? [] as $server)
                                            <option value="{{ $server->id }}">{{ $server->name }} — {{ $server->provider_key }}</option>
                                        @endforeach
                                    </select>
                                    <p class="ag-field__hint"><a href="{{ route('admin.provisioning.servers') }}">{{ __('admin.products.automation.manage_servers') }}</a></p>
                                    @error('provisioningServerId') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                                </div>
                                @foreach ($providerSettingDefinitions ?? [] as $definition)
                                    <div class="ag-field">
                                        <label class="ag-field__label" for="provider-setting-{{ $definition->key }}">{{ __($definition->label) }}</label>
                                        @if ($definition->type === 'text')
                                            <textarea id="provider-setting-{{ $definition->key }}" class="ag-input" rows="4" wire:model="providerSettings.{{ $definition->key }}"></textarea>
                                        @else
                                            <input id="provider-setting-{{ $definition->key }}" class="ag-input" type="text" wire:model="providerSettings.{{ $definition->key }}">
                                        @endif
                                        @if ($definition->help !== '')
                                            <p class="ag-field__hint">{{ __($definition->help) }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </details>
                    <div class="ag-preset-save">
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="saveCapabilities">
                            {{ __('admin.products.capabilities.save') }}
                        </button>
                    </div>
                @endif
                @error('capability') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
        </section>
        @endif

        @if ($mode === 'edit')
            <div id="product-tab-options" role="tabpanel" x-cloak x-show="activeTab === 'options'">
                <livewire:admin.products.options-editor :product-id="$product->id" :key="'product-options-'.$product->id" />
            </div>
            @foreach ($productTabs ?? [] as $productTab)
                <div id="product-tab-{{ $productTab->id }}" role="tabpanel" x-cloak x-show="activeTab === '{{ $productTab->id }}'">
                    @livewire($productTab->component, ['product' => $product], key('product-tab-'.$productTab->id.'-'.$product->id))
                </div>
            @endforeach
        @endif

        @can('products.delete')
            <div class="ag-form--product" x-show="activeTab === 'automation'">
                <x-ag.danger-zone
                    :title="__('admin.products.delete.zone_title')"
                    :description="$isReferenced
                        ? __('admin.products.delete.zone_referenced_text')
                        : __('admin.products.delete.zone_text')"
                >
                    @if ($isReferenced)
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="setDraft">
                            {{ __('admin.products.actions.set_draft') }}
                        </button>
                    @else
                        <button type="button" class="ag-btn ag-btn--danger" wire:click="confirmDelete">
                            {{ __('admin.products.actions.delete') }}
                        </button>
                    @endif
                </x-ag.danger-zone>
            </div>
        @endcan

        @if ($confirmingDelete)
            <div class="ag-modal" role="dialog" aria-modal="true" aria-labelledby="delete-edit-title">
                <div class="ag-modal__backdrop" wire:click="cancelDelete"></div>
                <div class="ag-modal__panel">
                    <h3 id="delete-edit-title" class="ag-modal__title">{{ __('admin.products.delete.title', ['name' => $product->name]) }}</h3>
                    <p class="ag-modal__text">{{ __('admin.products.delete.text') }}</p>
                    <div class="ag-modal__actions">
                        <button type="button" class="ag-btn ag-btn--danger" wire:click="deleteProduct">{{ __('admin.products.actions.delete') }}</button>
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelDelete">{{ __('common.cancel') }}</button>
                    </div>
                </div>
            </div>
        @endif
    @else
        <p class="ag-field__hint" x-show="activeTab === 'details'">{{ __('admin.products.form.create_media_hint') }}</p>
    @endif

    <div
        class="ag-form__sticky ag-form__sticky--page"
        role="group"
        aria-label="{{ __('admin.products.form.actions_aria') }}"
        @if ($mode === 'edit') x-show="activeTab === 'details' || activeTab === 'pricing'" @endif
    >
        <a class="ag-btn ag-btn--secondary" href="{{ route('admin.products.index') }}">{{ __('common.cancel') }}</a>
        <button type="submit" form="product-form" class="ag-btn ag-btn--primary" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">{{ $mode === 'create' ? __('admin.products.form.create_title') : __('admin.products.form.save_changes') }}</span>
            <span wire:loading wire:target="save">{{ __('common.saving') }}</span>
        </button>
    </div>
</div>
