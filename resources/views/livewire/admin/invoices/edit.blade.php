<div class="admin-page admin-invoice">
    <x-ag.page-header
        :heading="__('admin.invoices.edit_title', ['number' => $invoice->number])"
        :lede="__('admin.invoices.edit_lede')"
    >
        <x-slot:breadcrumbs>
            <x-ag.breadcrumbs :items="[
                ['label' => __('admin.nav_groups.overview'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.invoices.title'), 'url' => route('admin.invoices.index')],
                ['label' => $invoice->number, 'url' => route('admin.invoices.show', $invoice)],
                ['label' => __('admin.invoices.edit_title', ['number' => $invoice->number])],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:back>
            <x-ag.back :href="route('admin.invoices.show', $invoice)" :label="$invoice->number" />
        </x-slot:back>
        <x-slot:actions>
            <a class="ag-btn ag-btn--secondary" href="{{ route('admin.invoices.show', $invoice) }}">{{ __('common.cancel') }}</a>
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    <p class="ag-alert ag-alert--info" role="note">{{ __('admin.invoices.immutable_hint') }}</p>

    <form wire:submit="save" class="ag-form" novalidate>
        <section class="ag-section" aria-labelledby="invoice-contact-heading">
            <header class="ag-section__header">
                <h2 id="invoice-contact-heading" class="ag-section__title">{{ __('admin.invoices.edit.contact_heading') }}</h2>
                <p class="ag-section__lede">{{ __('admin.invoices.edit.contact_lede') }}</p>
            </header>
            <div class="ag-section__body ag-grid ag-grid--2">
                <div class="ag-field">
                    <label class="ag-field__label" for="invoice-customer-name">{{ __('common.name') }}</label>
                    <input id="invoice-customer-name" class="ag-input" type="text" wire:model="customerName" autocomplete="name">
                    @error('customerName') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="invoice-customer-email">{{ __('common.email') }}</label>
                    <input id="invoice-customer-email" class="ag-input" type="email" wire:model="customerEmail" autocomplete="email">
                    @error('customerEmail') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="ag-section" aria-labelledby="invoice-billing-heading">
            <header class="ag-section__header">
                <h2 id="invoice-billing-heading" class="ag-section__title">{{ __('admin.invoices.edit.billing_heading') }}</h2>
                <p class="ag-section__lede">{{ __('admin.invoices.edit.billing_lede') }}</p>
            </header>
            <div class="ag-section__body ag-grid ag-grid--2">
                <div class="ag-field">
                    <label class="ag-field__label" for="invoice-billing-name">{{ __('common.name') }}</label>
                    <input id="invoice-billing-name" class="ag-input" type="text" wire:model="billingName" autocomplete="billing name">
                    @error('billingName') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="invoice-billing-company">{{ __('admin.orders.edit.company') }}</label>
                    <input id="invoice-billing-company" class="ag-input" type="text" wire:model="billingCompany" autocomplete="organization">
                    @error('billingCompany') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="invoice-billing-line1">{{ __('admin.orders.edit.address') }}</label>
                    <input id="invoice-billing-line1" class="ag-input" type="text" wire:model="billingLine1" autocomplete="address-line1">
                    @error('billingLine1') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="invoice-billing-line2">{{ __('admin.orders.edit.address_line2') }}</label>
                    <input id="invoice-billing-line2" class="ag-input" type="text" wire:model="billingLine2" autocomplete="address-line2">
                    @error('billingLine2') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="invoice-billing-postal-code">{{ __('admin.orders.edit.postal_code') }}</label>
                    <input id="invoice-billing-postal-code" class="ag-input" type="text" wire:model="billingPostalCode" autocomplete="postal-code">
                    @error('billingPostalCode') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="invoice-billing-city">{{ __('admin.orders.edit.city') }}</label>
                    <input id="invoice-billing-city" class="ag-input" type="text" wire:model="billingCity" autocomplete="address-level2">
                    @error('billingCity') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="invoice-billing-region">{{ __('admin.orders.edit.region') }}</label>
                    <input id="invoice-billing-region" class="ag-input" type="text" wire:model="billingRegion" autocomplete="address-level1">
                    @error('billingRegion') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="invoice-billing-country">{{ __('admin.orders.edit.country_code') }}</label>
                    <input id="invoice-billing-country" class="ag-input" type="text" wire:model="billingCountry" autocomplete="country" maxlength="2">
                    @error('billingCountry') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="invoice-billing-phone">{{ __('admin.orders.edit.phone') }}</label>
                    <input id="invoice-billing-phone" class="ag-input" type="tel" wire:model="billingPhone" autocomplete="tel">
                    @error('billingPhone') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="ag-section" aria-labelledby="invoice-merchant-heading">
            <header class="ag-section__header">
                <h2 id="invoice-merchant-heading" class="ag-section__title">{{ __('admin.invoices.edit.merchant_heading') }}</h2>
                <p class="ag-section__lede">{{ __('admin.invoices.edit.merchant_lede') }}</p>
            </header>
            <div class="ag-section__body ag-grid ag-grid--2">
                <div class="ag-field">
                    <label class="ag-field__label" for="invoice-merchant-name">{{ __('admin.invoices.edit.merchant_name') }}</label>
                    <input id="invoice-merchant-name" class="ag-input" type="text" wire:model="merchantName" autocomplete="organization">
                    @error('merchantName') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="invoice-due-at">{{ __('admin.invoices.edit.due_at') }}</label>
                    <input id="invoice-due-at" class="ag-input" type="date" wire:model="dueAt">
                    @error('dueAt') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field ag-field--full">
                    <label class="ag-field__label" for="invoice-merchant-address">{{ __('admin.invoices.edit.merchant_address') }}</label>
                    <textarea id="invoice-merchant-address" class="ag-input" rows="4" wire:model="merchantAddress" autocomplete="street-address"></textarea>
                    @error('merchantAddress') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <div class="ag-form__actions">
            <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled">{{ __('common.save') }}</button>
            <a class="ag-btn ag-btn--secondary" href="{{ route('admin.invoices.show', $invoice) }}">{{ __('common.cancel') }}</a>
            @can('invoices.delete')
                @if (! $confirmingDelete)
                    <button type="button" class="ag-btn ag-btn--danger" wire:click="confirmDelete">{{ __('admin.invoices.delete_action') }}</button>
                @else
                    <div class="ag-confirm" role="alertdialog" aria-labelledby="invoice-delete-confirm-title">
                        <h3 id="invoice-delete-confirm-title">{{ __('admin.invoices.delete_confirm_title') }}</h3>
                        <p>{{ __('admin.invoices.delete_confirm_text') }}</p>
                        <div class="ag-confirm__actions">
                            <button type="button" class="ag-btn ag-btn--danger" wire:click="deleteInvoice">{{ __('common.confirm') }}</button>
                            <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelDelete">{{ __('common.cancel') }}</button>
                        </div>
                    </div>
                @endif
            @endcan
        </div>
    </form>

    @include('livewire.admin.partials.confirm-password-modal')
</div>
