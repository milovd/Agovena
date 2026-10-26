{{-- Order detail: customer, contact snapshot and due date, edited in place. Part of livewire.admin.orders.show. --}}
<section class="ag-card" aria-labelledby="order-details-heading">
    <header class="ag-card__header">
        <x-ag.icon-tile name="user" tone="indigo" />
        <h2 id="order-details-heading" class="ag-card__title">{{ __('admin.orders.inline.details') }}</h2>
    </header>
    <div class="ag-card__content admin-order__fields">
        <div class="ag-field admin-order__field--wide">
            <div class="admin-order__field-head">
                <label class="ag-field__label" for="order-customer">{{ __('admin.orders.inline.customer') }}<span class="admin-order__required" aria-hidden="true">*</span></label>
                @if ($order->customer_id !== null)
                    @can('customers.view')
                        <a class="admin-order__field-link" href="{{ route('admin.customers.show', $order->customer_id) }}">{{ __('admin.orders.inline.go_to_customer') }}</a>
                    @endcan
                @endif
            </div>
            <x-ag.search-select
                id="order-customer"
                field="customer"
                :selected="$customerLabel"
                :open="$picker === 'customer'"
                :results="$picker === 'customer' ? $pickerResults : []"
                :disabled="! $canUpdate || $customerLockReason !== null"
            />
            @if ($canUpdate && $customerLockReason !== null)
                <p class="ag-field__help">{{ $customerLockReason }}</p>
            @endif
            @error('customer') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
        </div>

        <div class="ag-field">
            <label class="ag-field__label" for="order-customer-name">{{ __('common.name') }}</label>
            <input id="order-customer-name" class="ag-input" type="text" wire:model="details.customerName" autocomplete="off" @disabled(! $canUpdate)>
            @error('details.customerName') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div class="ag-field">
            <label class="ag-field__label" for="order-customer-email">{{ __('common.email') }}</label>
            <input id="order-customer-email" class="ag-input" type="email" wire:model="details.customerEmail" autocomplete="off" @disabled(! $canUpdate)>
            @error('details.customerEmail') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
        </div>
        <div class="ag-field">
            <label class="ag-field__label" for="order-due-at">{{ __('admin.orders.edit.due_at') }}</label>
            <x-ag.date-input id="order-due-at" class="ag-input" type="datetime-local" wire:model="details.dueAt" :disabled="! $canUpdate" />
            @error('details.dueAt') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
        </div>
    </div>
</section>
