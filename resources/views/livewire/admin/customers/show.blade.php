<div class="admin-page">
    <x-ag.page-header :heading="$customer->name" :lede="$customer->email">
        <x-slot:back>
            <x-ag.back :href="route('admin.customers.index')" :label="__('admin.customers.back')" />
        </x-slot:back>
        <x-slot:actions>
            @if ($customer->anonymized_at)
                <span class="ag-badge">{{ __('admin.customers.anonymized_badge') }}</span>
            @elseif ($customer->deletion_requested_at)
                <span class="ag-badge">{{ __('admin.customers.deletion_requested_badge') }}</span>
            @endif
            @if ($user?->hasVerifiedEmail())
                <span class="ag-badge ag-badge--success">{{ __('admin.customers.email_verified') }}</span>
            @else
                <span class="ag-badge ag-badge--warning">{{ __('admin.customers.email_unverified') }}</span>
            @endif
            @if ($user?->hasTwoFactorEnabled())
                <span class="ag-badge ag-badge--success">{{ __('admin.customers.two_factor_on') }}</span>
            @endif
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <div class="ag-tabs" role="tablist" aria-label="{{ __('admin.customers.panels_aria') }}">
        @foreach ([
            'overview' => __('admin.customers.panels.overview'),
            'profile' => __('admin.customers.panels.profile'),
            'addresses' => __('admin.customers.panels.addresses'),
            'commerce' => __('admin.customers.panels.commerce'),
            'credits' => __('admin.customers.panels.credits'),
            'capabilities' => __('admin.customers.panels.capabilities'),
        ] as $key => $label)
            <button
                type="button"
                class="ag-tabs__tab {{ $panel === $key ? 'ag-tabs__tab--active' : '' }}"
                role="tab"
                aria-selected="{{ $panel === $key ? 'true' : 'false' }}"
                wire:click="selectPanel('{{ $key }}')"
            >{{ $label }}</button>
        @endforeach
    </div>

    @if ($panel === 'overview')
        <div class="ag-grid ag-grid--2" style="margin-bottom: 1rem;">
            <section class="admin-panel">
                <p class="ag-muted">{{ __('admin.orders.title') }}</p>
                <p class="admin-panel__title" style="margin:0;">{{ $stats['orders'] }}</p>
            </section>
            <section class="admin-panel">
                <p class="ag-muted">{{ __('admin.invoices.title') }}</p>
                <p class="admin-panel__title" style="margin:0;">{{ $stats['invoices'] }}</p>
            </section>
            <section class="admin-panel">
                <p class="ag-muted">{{ __('admin.tickets.title') }}</p>
                <p class="admin-panel__title" style="margin:0;">{{ $stats['tickets'] }}</p>
            </section>
            <section class="admin-panel">
                <p class="ag-muted">{{ __('admin.customers.credit_heading') }}</p>
                <p class="admin-panel__title" style="margin:0;">{{ \App\Support\MoneyFormatter::format(\App\Agovena\Money\Money::of($balanceAmount, $currency)) }}</p>
            </section>
        </div>

        <section class="admin-panel">
            <h2 class="admin-panel__title">{{ __('admin.customers.identity_heading') }}</h2>
            <dl class="ag-dl">
                <div>
                    <dt>{{ __('common.name') }}</dt>
                    <dd>{{ $customer->name }}</dd>
                </div>
                <div>
                    <dt>{{ __('admin.customers.email') }}</dt>
                    <dd>{{ $customer->email }}</dd>
                </div>
                <div>
                    <dt>{{ __('admin.customers.user_id') }}</dt>
                    <dd>
                        @if ($user)
                            #{{ $user->id }}
                            @can('users.view')
                                <a class="ag-btn ag-btn--ghost ag-btn--sm" href="{{ route('admin.users.index') }}">{{ __('admin.customers.open_users') }}</a>
                            @endcan
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt>{{ __('admin.customers.roles') }}</dt>
                    <dd>
                        @forelse (($user?->roles ?? collect()) as $role)
                            <span class="ag-badge">{{ $role->name }}</span>
                        @empty
                            <span class="ag-muted">{{ __('admin.customers.no_roles') }}</span>
                        @endforelse
                    </dd>
                </div>
                <div>
                    <dt>{{ __('common.created') }}</dt>
                    <dd>{{ $customer->created_at?->toDayDateTimeString() }}</dd>
                </div>
            </dl>
        </section>

        <section class="admin-panel">
            <header class="ag-section__header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;">
                <h2 class="admin-panel__title" style="margin:0;">{{ __('admin.customers.recent_orders') }}</h2>
                <button type="button" class="ag-btn ag-btn--ghost" wire:click="selectPanel('commerce')">{{ __('admin.customers.view_all_commerce') }}</button>
            </header>
            <div class="ag-table-wrap">
                <table class="ag-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.customers.activity_number') }}</th>
                            <th>{{ __('common.status') }}</th>
                            <th>{{ __('common.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentOrders as $order)
                            <tr>
                                <td>
                                    @can('orders.view')
                                        <a href="{{ route('admin.orders.show', $order) }}">{{ $order->number }}</a>
                                    @else
                                        {{ $order->number }}
                                    @endcan
                                </td>
                                <td>{{ __('admin.orders.status.'.$order->status->value) }}</td>
                                <td>{{ \App\Support\MoneyFormatter::format($order->total_amount, $order->currency) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3">{{ __('admin.customers.no_orders') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($panel === 'profile')
        <section class="admin-panel">
            <h2 class="admin-panel__title">{{ __('admin.customers.profile_heading') }}</h2>
            <p class="ag-muted">{{ __('admin.customers.profile_lede') }}</p>
            @can('customers.manage')
                @if (! $customer->anonymized_at)
                    <form class="ag-form" wire:submit="saveProfile">
                        <div class="ag-field">
                            <label class="ag-field__label" for="customer-name">{{ __('common.name') }}</label>
                            <input id="customer-name" class="ag-input" type="text" wire:model="name" required>
                            @error('name') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="ag-field">
                            <label class="ag-field__label" for="customer-email">{{ __('admin.customers.email') }}</label>
                            <input id="customer-email" class="ag-input" type="email" wire:model="email" required>
                            @error('email') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                            <p class="ag-field__help">{{ __('admin.customers.email_change_help') }}</p>
                        </div>
                        <button class="ag-btn ag-btn--primary" type="submit">{{ __('admin.customers.save_profile') }}</button>
                    </form>
                @else
                    <p class="ag-muted">{{ __('admin.customers.anonymized_locked') }}</p>
                @endif
            @else
                <dl class="ag-dl">
                    <div><dt>{{ __('common.name') }}</dt><dd>{{ $customer->name }}</dd></div>
                    <div><dt>{{ __('admin.customers.email') }}</dt><dd>{{ $customer->email }}</dd></div>
                </dl>
            @endcan
        </section>

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
    @endif

    @if ($panel === 'addresses')
        <section class="admin-panel">
            <h2 class="admin-panel__title">{{ __('admin.customers.addresses_heading') }}</h2>
            <div class="ag-table-wrap">
                <table class="ag-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.customers.address_label') }}</th>
                            <th>{{ __('common.name') }}</th>
                            <th>{{ __('admin.customers.address_line') }}</th>
                            <th>{{ __('admin.customers.address_flags') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($addresses as $address)
                            <tr wire:key="customer-address-{{ $address->id }}">
                                <td>{{ $address->label ?: '—' }}</td>
                                <td>{{ $address->name }}</td>
                                <td>
                                    {{ $address->line1 }}
                                    @if ($address->line2), {{ $address->line2 }}@endif
                                    · {{ $address->postal_code }} {{ $address->city }}
                                    · {{ $address->country }}
                                </td>
                                <td>
                                    @if ($address->is_default_billing)
                                        <span class="ag-badge">{{ __('admin.customers.default_billing') }}</span>
                                    @endif
                                    @if ($address->is_default_shipping)
                                        <span class="ag-badge">{{ __('admin.customers.default_shipping') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4">{{ __('admin.customers.no_addresses') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($panel === 'commerce')
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

                <div>
                    <h3 class="ag-section__title">{{ __('admin.customers.refunds_heading') }}</h3>
                    <div class="ag-table-wrap">
                        <table class="ag-table">
                            <thead>
                                <tr>
                                    <th>{{ __('admin.customers.amount') }}</th>
                                    <th>{{ __('common.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentRefunds as $refund)
                                    <tr>
                                        <td>{{ \App\Support\MoneyFormatter::format($refund->amount, $refund->currency) }}</td>
                                        <td>{{ __('admin.refunds.status.'.$refund->status->value) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2">{{ __('admin.customers.no_refunds') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($panel === 'credits')
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
                    <thead>
                        <tr>
                            <th>{{ __('admin.customers.entry_type') }}</th>
                            <th>{{ __('admin.customers.amount') }}</th>
                            <th>{{ __('admin.customers.reason') }}</th>
                            <th>{{ __('admin.customers.balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td>{{ __('admin.customers.'.$entry->entry_type) }}</td>
                                <td>{{ $entry->amount }}</td>
                                <td>{{ $entry->reason }}</td>
                                <td>{{ $entry->balance_after }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">{{ __('admin.customers.no_credit_entries') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($panel === 'capabilities')
        <p class="ag-muted" style="margin-bottom: 1rem;">{{ __('admin.customers.capabilities_lede') }}</p>
        @forelse ($customerDetailSections ?? [] as $section)
            @if ($section->permission === null || auth()->user()?->can($section->permission))
                @livewire($section->component, ['customer' => $customer], key($section->id.'-'.$customer->id))
            @endif
        @empty
            <section class="admin-panel">
                <p class="ag-muted">{{ __('admin.customers.no_capability_sections') }}</p>
            </section>
        @endforelse
    @endif

    @can('customers.manage')
        @if (! $customer->anonymized_at && $panel === 'profile')
            <x-ag.danger-zone :title="__('admin.customers.anonymize_heading')" :description="__('admin.customers.anonymize_lede')">
                <button class="ag-btn ag-btn--danger" type="button" wire:click="anonymize" wire:confirm="{{ __('admin.customers.anonymize_confirm') }}">
                    {{ __('admin.customers.anonymize') }}
                </button>
            </x-ag.danger-zone>
        @endif
    @endcan
</div>
