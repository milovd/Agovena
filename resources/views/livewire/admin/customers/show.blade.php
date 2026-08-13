<div class="admin-page">
    <x-ag.back :href="route('admin.customers.index')" :label="__('admin.customers.back')" />
    <x-ag.page-header :heading="$customer->name" :lede="$customer->email">
        <x-slot:actions>
            @if ($customer->anonymized_at)
                <span class="ag-badge">{{ __('admin.customers.anonymized_badge') }}</span>
            @endif
        </x-slot:actions>
    </x-ag.page-header>

    @if (($propertyDefinitions ?? collect())->isNotEmpty())
        <section class="admin-panel">
            <h2 class="admin-panel__title">{{ __('admin.customer_properties.values_heading') }}</h2>
            <form class="ag-form" wire:submit="saveProperties">
                @include('partials.custom-property-fields')
                @can('customers.manage')
                    <button class="ag-btn ag-btn--primary" type="submit">{{ __('admin.customer_properties.save_values') }}</button>
                @endcan
            </form>
        </section>
    @endif

    <section class="admin-panel">
        <h2 class="admin-panel__title">{{ __('admin.customers.credit_heading') }}</h2>
        <p>{{ __('admin.customers.balance') }}: <strong>{{ \App\Support\MoneyFormatter::format(\App\Agovena\Money\Money::of($balanceAmount, $currency)) }}</strong></p>
        @can('customers.manage')
            <form class="ag-form" wire:submit="adjustCredit">
                <div class="ag-field">
                    <label class="ag-field__label" for="credit-type">{{ __('admin.customers.entry_type') }}</label>
                    <select id="credit-type" class="ag-select" wire:model="entry_type">
                        <option value="credit">{{ __('admin.customers.credit') }}</option>
                        <option value="debit">{{ __('admin.customers.debit') }}</option>
                    </select>
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="credit-amount">{{ __('admin.customers.amount_minor') }}</label>
                    <input id="credit-amount" class="ag-input" type="number" min="1" wire:model.number="amount">
                    @error('amount') <p class="ag-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="credit-reason">{{ __('admin.customers.reason') }}</label>
                    <input id="credit-reason" class="ag-input" wire:model="reason">
                    @error('reason') <p class="ag-field__error">{{ $message }}</p> @enderror
                </div>
                <button class="ag-btn ag-btn--primary" type="submit">{{ __('admin.customers.adjust_credit') }}</button>
            </form>
        @endcan
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead><tr><th>{{ __('admin.customers.entry_type') }}</th><th>{{ __('admin.customers.amount') }}</th><th>{{ __('admin.customers.reason') }}</th><th>{{ __('admin.customers.balance') }}</th></tr></thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr><td>{{ __('admin.customers.'.$entry->entry_type) }}</td><td>{{ $entry->amount }}</td><td>{{ $entry->reason }}</td><td>{{ $entry->balance_after }}</td></tr>
                    @empty
                        <tr><td colspan="4">{{ __('admin.customers.no_credit_entries') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-panel">
        <h2 class="admin-panel__title">{{ __('admin.customers.activity_heading') }}</h2>
        <div class="ag-grid ag-grid--2">
            @can('orders.view')
                <div>
                    <h3 class="ag-section__title">{{ __('admin.orders.title') }}</h3>
                    <div class="ag-table-wrap">
                        <table class="ag-table">
                            <thead>
                                <tr>
                                    <th>{{ __('admin.customers.activity_number') }}</th>
                                    <th>{{ __('common.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentOrders as $order)
                                    <tr>
                                        <td><a href="{{ route('admin.orders.show', $order) }}">{{ $order->number }}</a></td>
                                        <td>{{ __('admin.orders.status.'.$order->status->value) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2">{{ __('admin.customers.no_orders') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endcan

            @can('invoices.view')
                <div>
                    <h3 class="ag-section__title">{{ __('admin.invoices.title') }}</h3>
                    <div class="ag-table-wrap">
                        <table class="ag-table">
                            <thead>
                                <tr>
                                    <th>{{ __('admin.customers.activity_number') }}</th>
                                    <th>{{ __('common.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentInvoices as $invoice)
                                    <tr>
                                        <td><a href="{{ route('admin.invoices.show', $invoice) }}">{{ $invoice->number }}</a></td>
                                        <td>{{ __('admin.invoices.status.'.$invoice->status->value) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2">{{ __('admin.customers.no_invoices') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div>
                    <h3 class="ag-section__title">{{ __('admin.credit_notes.title') }}</h3>
                    <div class="ag-table-wrap">
                        <table class="ag-table">
                            <thead>
                                <tr>
                                    <th>{{ __('admin.customers.activity_number') }}</th>
                                    <th>{{ __('common.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentCreditNotes as $note)
                                    <tr>
                                        <td><a href="{{ route('admin.credit-notes.show', $note) }}">{{ $note->number }}</a></td>
                                        <td>{{ __('admin.credit_notes.status.'.$note->status->value) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2">{{ __('admin.customers.no_credit_notes') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endcan

            @can('tickets.view')
                <div>
                    <h3 class="ag-section__title">{{ __('admin.tickets.title') }}</h3>
                    <div class="ag-table-wrap">
                        <table class="ag-table">
                            <thead>
                                <tr>
                                    <th>{{ __('admin.customers.activity_number') }}</th>
                                    <th>{{ __('common.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentTickets as $ticket)
                                    <tr>
                                        <td><a href="{{ route('admin.tickets.show', $ticket) }}">{{ $ticket->number }}</a></td>
                                        <td>{{ __('admin.tickets.status.'.$ticket->status->value) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2">{{ __('admin.customers.no_tickets') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endcan

            @if ($recentRefunds->isNotEmpty())
                <div>
                    <h3 class="ag-section__title">{{ __('admin.customers.refunds_heading') }}</h3>
                    <div class="ag-table-wrap">
                        <table class="ag-table">
                            <thead>
                                <tr>
                                    <th>{{ __('admin.customers.activity_number') }}</th>
                                    <th>{{ __('common.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentRefunds as $refund)
                                    <tr>
                                        <td>{{ $refund->amount }} {{ $refund->currency }}</td>
                                        <td>{{ __('admin.refunds.status.'.$refund->status->value) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </section>

    @foreach ($customerDetailSections ?? [] as $section)
        @if ($section->permission === null || auth()->user()?->can($section->permission))
            @livewire($section->component, ['customer' => $customer], key($section->id.'-'.$customer->id))
        @endif
    @endforeach

    @can('customers.manage')
        @if (! $customer->anonymized_at)
            <x-ag.danger-zone :title="__('admin.customers.anonymize_heading')" :description="__('admin.customers.anonymize_lede')">
                <button class="ag-btn ag-btn--danger" type="button" wire:click="anonymize" wire:confirm="{{ __('admin.customers.anonymize_confirm') }}">
                    {{ __('admin.customers.anonymize') }}
                </button>
            </x-ag.danger-zone>
        @endif
    @endcan
</div>
