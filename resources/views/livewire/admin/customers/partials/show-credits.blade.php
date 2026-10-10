{{-- Customer workspace sidebar: store credit card. Part of livewire.admin.customers.show. --}}
<section class="customer-workspace__card" aria-labelledby="credits-heading">
    <header class="customer-workspace__section-header">
        <x-ag.icon name="wallet" :size="20" class="customer-workspace__section-icon" />
        <div>
            <p class="customer-workspace__eyebrow">{{ __('admin.customers.credits_eyebrow') }}</p>
            <h2 id="credits-heading" class="customer-workspace__section-title">{{ __('admin.customers.credit_heading') }}</h2>
        </div>
        <strong class="customer-workspace__balance">{{ $formatMoney($balanceAmount, $currency) }}</strong>
    </header>
    <div class="customer-workspace__credit-breakdown">
        <span>{{ __('admin.customers.available_credit') }} <strong>{{ $formatMoney($availableAmount, $currency) }}</strong></span>
        <span>{{ __('admin.customers.reserved_credit') }} <strong>{{ $formatMoney($reservedAmount, $currency) }}</strong></span>
    </div>
    @can('customers.manage')
        <form class="customer-workspace__credit-form" wire:submit="adjustCredit">
            <div class="customer-workspace__field-grid">
                <div class="ag-field">
                    <label class="ag-field__label" for="credit-type">{{ __('admin.customers.entry_type') }}</label>
                    <select id="credit-type" class="ag-select" wire:model="entry_type">
                        <option value="credit">{{ __('admin.customers.credit') }}</option>
                        <option value="debit">{{ __('admin.customers.debit') }}</option>
                    </select>
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="credit-amount">{{ __('admin.customers.amount_minor') }}</label>
                    <input id="credit-amount" class="ag-input" type="number" min="1" wire:model.number="amount" required>
                    @error('amount') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="credit-reason">{{ __('admin.customers.reason') }}</label>
                <input id="credit-reason" class="ag-input" wire:model="reason" maxlength="255" required>
                @error('reason') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <button class="ag-btn ag-btn--secondary" type="submit" wire:loading.attr="disabled">{{ __('admin.customers.adjust_credit') }}</button>
        </form>
    @endcan
    <div class="customer-workspace__ledger">
        @forelse ($entries as $entry)
            <div class="customer-workspace__ledger-entry">
                <span class="{{ $entry->entry_type === 'credit' ? 'customer-workspace__ledger-mark customer-workspace__ledger-mark--credit' : 'customer-workspace__ledger-mark customer-workspace__ledger-mark--debit' }}" aria-hidden="true"></span>
                <div><strong>{{ __('admin.customers.'.$entry->entry_type) }}</strong><span>{{ $entry->reason }}</span></div>
                <strong>{{ $formatMoney($entry->amount, $currency) }}</strong>
            </div>
        @empty
            <p class="customer-workspace__empty">{{ __('admin.customers.no_credit_entries') }}</p>
        @endforelse
    </div>
</section>
