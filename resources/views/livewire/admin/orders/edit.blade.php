<div class="admin-page">
    <x-ag.page-header :heading="__('admin.orders.edit_title', ['number' => $order->number])" :lede="__('admin.orders.edit_lede')">
        <x-slot:breadcrumbs>
            <x-ag.breadcrumbs :items="[
                ['label' => __('admin.nav_groups.overview'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.orders.title'), 'url' => route('admin.orders.index')],
                ['label' => $order->number, 'url' => route('admin.orders.show', $order)],
                ['label' => __('admin.orders.edit_title', ['number' => $order->number])],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:back>
            <x-ag.back :href="route('admin.orders.show', $order)" :label="$order->number" />
        </x-slot:back>
        <x-slot:actions>
            <a class="ag-btn ag-btn--secondary" href="{{ route('admin.orders.show', $order) }}">{{ __('common.cancel') }}</a>
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    <form wire:submit="save" class="ag-form" novalidate>
        <section class="ag-section" aria-labelledby="order-contact-heading">
            <header class="ag-section__header">
                <h2 id="order-contact-heading" class="ag-section__title">{{ __('admin.orders.edit.contact_heading') }}</h2>
                <p class="ag-section__lede">{{ __('admin.orders.edit.contact_lede') }}</p>
            </header>
            <div class="ag-section__body ag-grid ag-grid--2">
                <div class="ag-field">
                    <label class="ag-field__label" for="order-customer-name">{{ __('common.name') }}</label>
                    <input id="order-customer-name" class="ag-input" type="text" wire:model="customerName" autocomplete="name">
                    @error('customerName') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="order-customer-email">{{ __('common.email') }}</label>
                    <input id="order-customer-email" class="ag-input" type="email" wire:model="customerEmail" autocomplete="email">
                    @error('customerEmail') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="order-due-at">{{ __('admin.orders.edit.due_at') }}</label>
                    <input id="order-due-at" class="ag-input" type="datetime-local" wire:model="dueAt">
                    @error('dueAt') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="ag-section" aria-labelledby="order-billing-heading">
            <header class="ag-section__header">
                <h2 id="order-billing-heading" class="ag-section__title">{{ __('admin.orders.edit.billing_heading') }}</h2>
                <p class="ag-section__lede">{{ __('admin.orders.edit.address_lede') }}</p>
            </header>
            <div class="ag-section__body ag-grid ag-grid--2">
                <div class="ag-field">
                    <label class="ag-field__label" for="order-billing-name">{{ __('common.name') }}</label>
                    <input id="order-billing-name" class="ag-input" type="text" wire:model="billingName" autocomplete="billing name">
                    @error('billingName') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="order-billing-company">{{ __('admin.orders.edit.company') }}</label>
                    <input id="order-billing-company" class="ag-input" type="text" wire:model="billingCompany" autocomplete="organization">
                    @error('billingCompany') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="order-billing-line1">{{ __('admin.orders.edit.address') }}</label>
                    <input id="order-billing-line1" class="ag-input" type="text" wire:model="billingLine1" autocomplete="address-line1">
                    @error('billingLine1') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="order-billing-line2">{{ __('admin.orders.edit.address_line2') }}</label>
                    <input id="order-billing-line2" class="ag-input" type="text" wire:model="billingLine2" autocomplete="address-line2">
                    @error('billingLine2') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="order-billing-postal-code">{{ __('admin.orders.edit.postal_code') }}</label>
                    <input id="order-billing-postal-code" class="ag-input" type="text" wire:model="billingPostalCode" autocomplete="postal-code">
                    @error('billingPostalCode') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="order-billing-city">{{ __('admin.orders.edit.city') }}</label>
                    <input id="order-billing-city" class="ag-input" type="text" wire:model="billingCity" autocomplete="address-level2">
                    @error('billingCity') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="order-billing-region">{{ __('admin.orders.edit.region') }}</label>
                    <input id="order-billing-region" class="ag-input" type="text" wire:model="billingRegion" autocomplete="address-level1">
                    @error('billingRegion') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="order-billing-country">{{ __('admin.orders.edit.country_code') }}</label>
                    <input id="order-billing-country" class="ag-input" type="text" wire:model="billingCountry" autocomplete="country" maxlength="2">
                    @error('billingCountry') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="order-billing-phone">{{ __('admin.orders.edit.phone') }}</label>
                    <input id="order-billing-phone" class="ag-input" type="tel" wire:model="billingPhone" autocomplete="tel">
                    @error('billingPhone') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="ag-section" aria-labelledby="order-shipping-heading">
            <header class="ag-section__header">
                <h2 id="order-shipping-heading" class="ag-section__title">{{ __('admin.orders.edit.shipping_heading') }}</h2>
                <p class="ag-section__lede">{{ __('admin.orders.edit.address_lede') }}</p>
            </header>
            <div class="ag-section__body">
                <label class="ag-check">
                    <input type="checkbox" wire:model="shippingSameAsBilling">
                    <span>{{ __('admin.orders.edit.shipping_same_as_billing') }}</span>
                </label>
                <div class="ag-grid ag-grid--2">
                    <div class="ag-field">
                        <label class="ag-field__label" for="order-shipping-name">{{ __('common.name') }}</label>
                        <input id="order-shipping-name" class="ag-input" type="text" wire:model="shippingName" autocomplete="shipping name">
                        @error('shippingName') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="order-shipping-company">{{ __('admin.orders.edit.company') }}</label>
                        <input id="order-shipping-company" class="ag-input" type="text" wire:model="shippingCompany" autocomplete="organization">
                        @error('shippingCompany') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="order-shipping-line1">{{ __('admin.orders.edit.address') }}</label>
                        <input id="order-shipping-line1" class="ag-input" type="text" wire:model="shippingLine1" autocomplete="shipping address-line1">
                        @error('shippingLine1') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="order-shipping-line2">{{ __('admin.orders.edit.address_line2') }}</label>
                        <input id="order-shipping-line2" class="ag-input" type="text" wire:model="shippingLine2" autocomplete="shipping address-line2">
                        @error('shippingLine2') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="order-shipping-postal-code">{{ __('admin.orders.edit.postal_code') }}</label>
                        <input id="order-shipping-postal-code" class="ag-input" type="text" wire:model="shippingPostalCode" autocomplete="shipping postal-code">
                        @error('shippingPostalCode') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="order-shipping-city">{{ __('admin.orders.edit.city') }}</label>
                        <input id="order-shipping-city" class="ag-input" type="text" wire:model="shippingCity" autocomplete="shipping address-level2">
                        @error('shippingCity') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="order-shipping-region">{{ __('admin.orders.edit.region') }}</label>
                        <input id="order-shipping-region" class="ag-input" type="text" wire:model="shippingRegion" autocomplete="shipping address-level1">
                        @error('shippingRegion') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="order-shipping-country">{{ __('admin.orders.edit.country_code') }}</label>
                        <input id="order-shipping-country" class="ag-input" type="text" wire:model="shippingCountry" autocomplete="shipping country" maxlength="2">
                        @error('shippingCountry') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="order-shipping-phone">{{ __('admin.orders.edit.phone') }}</label>
                        <input id="order-shipping-phone" class="ag-input" type="tel" wire:model="shippingPhone" autocomplete="shipping tel">
                        @error('shippingPhone') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </section>

        <div class="ag-form__actions">
            <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled">{{ __('common.save') }}</button>
            <a class="ag-btn ag-btn--secondary" href="{{ route('admin.orders.show', $order) }}">{{ __('common.cancel') }}</a>
            @can('orders.delete')
                <button type="button" class="ag-btn ag-btn--danger" wire:click="confirmDelete">{{ __('admin.orders.actions.delete') }}</button>
            @endcan
        </div>
    </form>

    @if ($confirmingDelete)
        <div class="ag-modal" role="dialog" aria-modal="true" aria-labelledby="delete-order-title">
            <div class="ag-modal__backdrop" wire:click="cancelDelete"></div>
            <div class="ag-modal__panel">
                <h2 id="delete-order-title" class="ag-modal__title">{{ __('admin.orders.delete.title', ['number' => $order->number]) }}</h2>
                <p class="ag-modal__text">{{ __('admin.orders.delete.text') }}</p>
                <div class="ag-modal__actions">
                    <button type="button" class="ag-btn ag-btn--danger" wire:click="deleteOrder">{{ __('admin.orders.actions.delete') }}</button>
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelDelete">{{ __('common.cancel') }}</button>
                </div>
            </div>
        </div>
    @endif

    @include('livewire.admin.partials.confirm-password-modal')
</div>
