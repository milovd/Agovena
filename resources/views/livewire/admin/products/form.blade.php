<div class="admin-page admin-page--form" x-data="agProductTabs">
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

    @if (($isOrderable ?? true) === false)
        <p class="ag-alert ag-alert--warning" role="status">{{ __('admin.products.form.not_orderable') }}</p>
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
                @click="selectTab('{{ $tab }}')"
            >{{ __('admin.products.tabs.'.$tab) }}</button>
        @endforeach
        @if ($mode === 'edit')
            <button type="button" class="ag-product-tabs__tab" :class="{ 'is-active': activeTab === 'media' }" role="tab" :aria-selected="(activeTab === 'media').toString()" aria-controls="product-tab-media" @click="selectTab('media')">{{ __('admin.products.tabs.media') }}</button>
        @endif
        @if ($availableCapabilityKeys !== [])
            <button type="button" class="ag-product-tabs__tab" :class="{ 'is-active': activeTab === 'automation' }" role="tab" :aria-selected="(activeTab === 'automation').toString()" aria-controls="product-tab-automation" @click="selectTab('automation')">{{ __('admin.products.tabs.automation') }}</button>
        @endif
        @if ($mode === 'edit')
            <button type="button" class="ag-product-tabs__tab" :class="{ 'is-active': activeTab === 'options' }" role="tab" :aria-selected="(activeTab === 'options').toString()" aria-controls="product-tab-options" @click="selectTab('options')">{{ __('admin.products.tabs.options') }}</button>
            @foreach ($productTabs ?? [] as $productTab)
                <button
                    type="button"
                    class="ag-product-tabs__tab"
                    :class="{ 'is-active': activeTab === '{{ $productTab->id }}' }"
                    role="tab"
                    :aria-selected="(activeTab === '{{ $productTab->id }}').toString()"
                    aria-controls="product-tab-{{ $productTab->id }}"
                    @click="selectTab('{{ $productTab->id }}')"
                >{{ __($productTab->label) }}</button>
            @endforeach
        @endif
    </nav>

    <form id="product-form" wire:submit="save" class="ag-form ag-form--product" novalidate>
        @include('livewire.admin.products.partials.form-details')

        @include('livewire.admin.products.partials.form-pricing')

        @if ($mode === 'create' && ($canConfigureProvisioning ?? false))
            @include('livewire.admin.products.partials.form-create-automation')
        @endif

    </form>

    @if ($mode === 'edit')
        @include('livewire.admin.products.partials.form-media')

        @if ($availableCapabilityKeys !== [])
            @include('livewire.admin.products.partials.form-capabilities')
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
