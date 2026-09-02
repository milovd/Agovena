@php
    $nameParts = collect(preg_split('/\s+/', trim($customer->name)) ?: [])->filter();
    $initials = $nameParts->take(2)->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $formatMoney = static fn (int $amount, string $currency): string => \App\Support\MoneyFormatter::format($amount, $currency);
@endphp

<div class="admin-page customer-workspace">
    <x-ag.page-header :heading="$customer->name" :lede="$customer->email">
        <x-slot:breadcrumbs>
            <x-ag.breadcrumbs :items="[
                ['label' => __('admin.nav_groups.overview'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.customers.title'), 'url' => route('admin.customers.index')],
                ['label' => $customer->name],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:back>
            <x-ag.back :href="route('admin.customers.index')" :label="__('admin.customers.back')" />
        </x-slot:back>
        <x-slot:actions>
            @if ($customer->anonymized_at)
                <span class="ag-badge">{{ __('admin.customers.anonymized_badge') }}</span>
            @elseif ($customer->deletion_requested_at)
                <span class="ag-badge ag-badge--warning">{{ __('admin.customers.deletion_requested_badge') }}</span>
            @endif
            @if ($user?->hasVerifiedEmail())
                <span class="ag-badge ag-badge--success">{{ __('admin.customers.email_verified') }}</span>
            @else
                <span class="ag-badge ag-badge--warning">{{ __('admin.customers.email_unverified') }}</span>
            @endif
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <section class="customer-workspace__hero" aria-labelledby="customer-workspace-title">
        <div class="customer-workspace__identity">
            <span class="customer-workspace__avatar" aria-hidden="true">{{ $initials ?: '?' }}</span>
            <div>
                <p class="customer-workspace__eyebrow">{{ __('admin.customers.workspace_eyebrow') }}</p>
                <h2 id="customer-workspace-title" class="customer-workspace__name">{{ $customer->name }}</h2>
                <p class="customer-workspace__meta">
                    {{ __('admin.customers.customer_since', ['date' => $customer->created_at?->format('d M Y')]) }}
                    <span aria-hidden="true">·</span>
                    {{ __('admin.customers.user_id') }} #{{ $user?->id ?? '-' }}
                </p>
            </div>
        </div>
        <div class="customer-workspace__hero-status">
            <span class="customer-workspace__hero-status-label">{{ __('admin.customers.account_status') }}</span>
            <strong>{{ $customer->anonymized_at ? __('admin.customers.anonymized_badge') : __('admin.customers.status_active') }}</strong>
        </div>
    </section>

    <div class="customer-workspace__metrics" aria-label="{{ __('admin.customers.summary_aria') }}">
        <article class="customer-workspace__metric">
            <span class="customer-workspace__metric-label">{{ __('admin.orders.title') }}</span>
            <strong class="customer-workspace__metric-value">{{ $stats['orders'] }}</strong>
            <span class="customer-workspace__metric-note">{{ __('admin.customers.summary_orders') }}</span>
        </article>
        <article class="customer-workspace__metric">
            <span class="customer-workspace__metric-label">{{ __('admin.invoices.title') }}</span>
            <strong class="customer-workspace__metric-value">{{ $stats['invoices'] }}</strong>
            <span class="customer-workspace__metric-note">{{ __('admin.customers.summary_invoices') }}</span>
        </article>
        <article class="customer-workspace__metric">
            <span class="customer-workspace__metric-label">{{ __('admin.tickets.title') }}</span>
            <strong class="customer-workspace__metric-value">{{ $stats['tickets'] }}</strong>
            <span class="customer-workspace__metric-note">{{ __('admin.customers.summary_tickets') }}</span>
        </article>
        <article class="customer-workspace__metric customer-workspace__metric--accent">
            <span class="customer-workspace__metric-label">{{ __('admin.customers.credit_heading') }}</span>
            <strong class="customer-workspace__metric-value">{{ $formatMoney($balanceAmount, $currency) }}</strong>
            <span class="customer-workspace__metric-note">{{ __('admin.customers.summary_credit') }}</span>
        </article>
    </div>

    <div class="customer-workspace__layout">
        <main class="customer-workspace__main">
            <section class="customer-workspace__card customer-workspace__card--profile" aria-labelledby="profile-heading">
                <header class="customer-workspace__section-header">
                    <div>
                        <p class="customer-workspace__eyebrow">{{ __('admin.customers.profile_eyebrow') }}</p>
                        <h2 id="profile-heading" class="customer-workspace__section-title">{{ __('admin.customers.workspace_profile') }}</h2>
                        <p class="customer-workspace__section-lede">{{ __('admin.customers.workspace_profile_lede') }}</p>
                    </div>
                    @can('customers.manage')
                        <span class="customer-workspace__edit-hint">{{ __('admin.customers.editable_by_staff') }}</span>
                    @endcan
                </header>

                @can('customers.manage')
                    @if (! $customer->anonymized_at)
                        <form class="customer-workspace__profile-form" wire:submit="saveProfile">
                            <div class="customer-workspace__field-grid">
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
                            </div>
                            <button class="ag-btn ag-btn--primary" type="submit">{{ __('admin.customers.save_profile') }}</button>
                        </form>
                    @else
                        <p class="customer-workspace__locked">{{ __('admin.customers.anonymized_locked') }}</p>
                    @endif
                @else
                    <dl class="customer-workspace__details">
                        <div><dt>{{ __('common.name') }}</dt><dd>{{ $customer->name }}</dd></div>
                        <div><dt>{{ __('admin.customers.email') }}</dt><dd>{{ $customer->email }}</dd></div>
                    </dl>
                @endcan

                @if (($propertyDefinitions ?? collect())->isNotEmpty() && ! $customer->anonymized_at)
                    <div class="customer-workspace__subsection">
                        <header class="customer-workspace__subsection-header">
                            <h3>{{ __('admin.customers.contact_details_heading') }}</h3>
                            <p>{{ __('admin.customers.contact_details_lede') }}</p>
                        </header>
                        @can('customers.manage')
                            <form class="customer-workspace__property-form" wire:submit="saveProperties">
                                <div class="customer-workspace__field-grid customer-workspace__field-grid--properties">
                                    @include('partials.custom-property-fields')
                                </div>
                                <button class="ag-btn ag-btn--secondary" type="submit">{{ __('admin.customers.save_contact_details') }}</button>
                            </form>
                        @else
                            <div class="customer-workspace__field-grid customer-workspace__field-grid--properties">
                                @include('partials.custom-property-fields', ['propertyEditable' => false])
                            </div>
                        @endcan
                    </div>
                @endif

                <div class="customer-workspace__subsection">
                    <header class="customer-workspace__subsection-header">
                        <h3>{{ __('admin.customers.saved_addresses_heading') }}</h3>
                        <p>{{ __('admin.customers.saved_addresses_lede') }}</p>
                    </header>
                    <div class="customer-workspace__address-list">
                        @forelse ($addresses as $address)
                            <article class="customer-workspace__address" wire:key="customer-address-{{ $address->id }}">
                                <div class="customer-workspace__address-title">
                                    <strong>{{ $address->label ?: __('admin.customers.address_label') }}</strong>
                                    <div>
                                        @if ($address->is_default_billing)
                                            <span class="ag-badge">{{ __('admin.customers.default_billing') }}</span>
                                        @endif
                                        @if ($address->is_default_shipping)
                                            <span class="ag-badge">{{ __('admin.customers.default_shipping') }}</span>
                                        @endif
                                    </div>
                                </div>
                                <p>
                                    {{ $address->name }}<br>
                                    @if ($address->company){{ $address->company }}<br>@endif
                                    {{ $address->line1 }}@if ($address->line2), {{ $address->line2 }}@endif<br>
                                    {{ $address->postal_code }} {{ $address->city }}@if ($address->region), {{ $address->region }}@endif<br>
                                    {{ $address->country }}
                                </p>
                            </article>
                        @empty
                            <p class="customer-workspace__empty">{{ __('admin.customers.no_addresses') }}</p>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="customer-workspace__card" aria-labelledby="activity-heading">
                <header class="customer-workspace__section-header">
                    <div>
                        <p class="customer-workspace__eyebrow">{{ __('admin.customers.activity_eyebrow') }}</p>
                        <h2 id="activity-heading" class="customer-workspace__section-title">{{ __('admin.customers.activity_heading') }}</h2>
                        <p class="customer-workspace__section-lede">{{ __('admin.customers.activity_lede') }}</p>
                    </div>
                </header>

                <div class="customer-workspace__activity-grid">
                    @can('orders.view')
                        <article class="customer-workspace__activity-card">
                            <header class="customer-workspace__activity-header">
                                <h3>{{ __('admin.orders.title') }}</h3>
                                <span class="ag-badge">{{ $stats['orders'] }}</span>
                            </header>
                            <ul class="customer-workspace__activity-list">
                                @forelse ($recentOrders as $order)
                                    <li>
                                        <div>
                                            <a href="{{ route('admin.orders.show', $order) }}">{{ $order->number }}</a>
                                            <span>{{ $order->created_at?->format('d M Y') }}</span>
                                        </div>
                                        <div class="customer-workspace__activity-end">
                                            <span class="ag-badge">{{ __('admin.orders.status.'.$order->status->value) }}</span>
                                            <strong>{{ $formatMoney($order->total_amount, $order->currency) }}</strong>
                                        </div>
                                    </li>
                                @empty
                                    <li class="customer-workspace__empty">{{ __('admin.customers.no_orders') }}</li>
                                @endforelse
                            </ul>
                            @if ($stats['orders'] > 6)
                                <a class="customer-workspace__view-all" href="{{ route('admin.orders.index') }}">{{ __('admin.customers.view_all_orders') }}</a>
                            @endif
                        </article>
                    @endcan

                    @can('invoices.view')
                        <article class="customer-workspace__activity-card">
                            <header class="customer-workspace__activity-header">
                                <h3>{{ __('admin.invoices.title') }}</h3>
                                <span class="ag-badge">{{ $stats['invoices'] }}</span>
                            </header>
                            <ul class="customer-workspace__activity-list">
                                @forelse ($recentInvoices as $invoice)
                                    <li>
                                        <div>
                                            <a href="{{ route('admin.invoices.show', $invoice) }}">{{ $invoice->number }}</a>
                                            <span>{{ $invoice->issued_at?->format('d M Y') }}</span>
                                        </div>
                                        <div class="customer-workspace__activity-end">
                                            <span class="ag-badge">{{ __('admin.invoices.status.'.$invoice->status->value) }}</span>
                                            <strong>{{ $formatMoney($invoice->total_amount, $invoice->currency) }}</strong>
                                        </div>
                                    </li>
                                @empty
                                    <li class="customer-workspace__empty">{{ __('admin.customers.no_invoices') }}</li>
                                @endforelse
                            </ul>
                            @if ($stats['invoices'] > 6)
                                <a class="customer-workspace__view-all" href="{{ route('admin.invoices.index') }}">{{ __('admin.customers.view_all_invoices') }}</a>
                            @endif
                        </article>
                    @endcan

                    @can('tickets.view')
                        <article class="customer-workspace__activity-card">
                            <header class="customer-workspace__activity-header">
                                <h3>{{ __('admin.tickets.title') }}</h3>
                                <span class="ag-badge">{{ $stats['tickets'] }}</span>
                            </header>
                            <ul class="customer-workspace__activity-list">
                                @forelse ($recentTickets as $ticket)
                                    <li>
                                        <div>
                                            <a href="{{ route('admin.tickets.show', $ticket) }}">{{ $ticket->number }}</a>
                                            <span>{{ $ticket->subject }}</span>
                                        </div>
                                        <div class="customer-workspace__activity-end">
                                            <span class="ag-badge">{{ __('admin.tickets.status.'.$ticket->status->value) }}</span>
                                            <span>{{ $ticket->last_reply_at?->format('d M Y') ?: $ticket->created_at?->format('d M Y') }}</span>
                                        </div>
                                    </li>
                                @empty
                                    <li class="customer-workspace__empty">{{ __('admin.customers.no_tickets') }}</li>
                                @endforelse
                            </ul>
                            @if ($stats['tickets'] > 6)
                                <a class="customer-workspace__view-all" href="{{ route('admin.tickets.index') }}">{{ __('admin.customers.view_all_tickets') }}</a>
                            @endif
                        </article>
                    @endcan

                    <article class="customer-workspace__activity-card">
                        <header class="customer-workspace__activity-header">
                            <h3>{{ __('admin.customers.financial_activity_heading') }}</h3>
                            <span class="ag-badge">{{ $stats['creditNotes'] }}</span>
                        </header>
                        <ul class="customer-workspace__activity-list">
                            @forelse ($recentCreditNotes as $note)
                                <li>
                                    <div>
                                        <a href="{{ route('admin.credit-notes.show', $note) }}">{{ $note->number }}</a>
                                        <span>{{ $note->issued_at?->format('d M Y') }}</span>
                                    </div>
                                    <div class="customer-workspace__activity-end">
                                        <span class="ag-badge">{{ __('admin.credit_notes.status.'.$note->status->value) }}</span>
                                        <strong>{{ $formatMoney($note->total_amount, $note->currency) }}</strong>
                                    </div>
                                </li>
                            @empty
                                <li class="customer-workspace__empty">{{ __('admin.customers.no_credit_notes') }}</li>
                            @endforelse
                        </ul>
                        @if ($recentRefunds->isNotEmpty())
                            <p class="customer-workspace__activity-footnote">{{ __('admin.customers.refunds_count', ['count' => $recentRefunds->count()]) }}</p>
                        @endif
                    </article>
                </div>
            </section>

            <section class="customer-workspace__card" aria-labelledby="capabilities-heading">
                <header class="customer-workspace__section-header">
                    <div>
                        <p class="customer-workspace__eyebrow">{{ __('admin.customers.capabilities_eyebrow') }}</p>
                        <h2 id="capabilities-heading" class="customer-workspace__section-title">{{ __('admin.customers.capabilities_heading') }}</h2>
                        <p class="customer-workspace__section-lede">{{ __('admin.customers.capabilities_lede') }}</p>
                    </div>
                </header>
                <div class="customer-workspace__capabilities">
                    @forelse ($customerDetailSections ?? [] as $section)
                        @if ($section->permission === null || auth()->user()?->can($section->permission))
                            @livewire($section->component, ['customer' => $customer], key($section->id.'-'.$customer->id))
                        @endif
                    @empty
                        <p class="customer-workspace__empty">{{ __('admin.customers.no_capability_sections') }}</p>
                    @endforelse
                </div>
            </section>
        </main>

        <aside class="customer-workspace__sidebar">
            <section class="customer-workspace__card" aria-labelledby="security-heading">
                <header class="customer-workspace__section-header">
                    <div>
                        <p class="customer-workspace__eyebrow">{{ __('admin.customers.security_eyebrow') }}</p>
                        <h2 id="security-heading" class="customer-workspace__section-title">{{ __('admin.customers.security_heading') }}</h2>
                    </div>
                </header>
                @if ($user)
                    <div class="customer-workspace__status-list">
                        <div><span>{{ __('admin.customers.email_verification_status') }}</span><strong>{{ $user->hasVerifiedEmail() ? __('admin.customers.email_verified') : __('admin.customers.email_unverified') }}</strong></div>
                        <div><span>{{ __('admin.customers.two_factor_status') }}</span><strong>{{ $user->hasTwoFactorEnabled() ? __('admin.customers.two_factor_on') : __('admin.customers.two_factor_off') }}</strong></div>
                    </div>
                    @can('customers.manage')
                        @if ($user->hasTwoFactorEnabled())
                            <div class="customer-workspace__security-warning">
                                <strong>{{ __('admin.customers.two_factor_enabled_warning_title') }}</strong>
                                <p>{{ __('admin.customers.two_factor_enabled_warning') }}</p>
                                <button class="ag-btn ag-btn--danger-outline" type="button" wire:click="disableTwoFactor" wire:confirm="{{ __('admin.customers.disable_two_factor_confirm') }}" wire:loading.attr="disabled">
                                    {{ __('admin.customers.disable_two_factor') }}
                                </button>
                            </div>
                        @else
                            <form class="customer-workspace__password-form" wire:submit="changePassword">
                                <div class="customer-workspace__subsection-header">
                                    <h3>{{ __('admin.customers.password_heading') }}</h3>
                                    <p>{{ __('admin.customers.password_lede') }}</p>
                                </div>
                                <div class="ag-field">
                                    <label class="ag-field__label" for="customer-password">{{ __('admin.customers.new_password') }}</label>
                                    <input id="customer-password" class="ag-input" type="password" wire:model="password" autocomplete="new-password" required>
                                    @error('password') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                                </div>
                                <div class="ag-field">
                                    <label class="ag-field__label" for="customer-password-confirmation">{{ __('admin.customers.confirm_password') }}</label>
                                    <input id="customer-password-confirmation" class="ag-input" type="password" wire:model="password_confirmation" autocomplete="new-password" required>
                                </div>
                                <button class="ag-btn ag-btn--secondary" type="submit" wire:loading.attr="disabled">{{ __('admin.customers.set_password') }}</button>
                            </form>
                        @endif
                        <div class="customer-workspace__security-actions">
                            @if ($user->hasVerifiedEmail())
                                <button class="ag-btn ag-btn--ghost" type="button" wire:click="markEmailUnverified" wire:confirm="{{ __('admin.customers.mark_email_unverified_confirm') }}">{{ __('admin.customers.mark_email_unverified') }}</button>
                            @else
                                <button class="ag-btn ag-btn--ghost" type="button" wire:click="markEmailVerified" wire:confirm="{{ __('admin.customers.mark_email_verified_confirm') }}">{{ __('admin.customers.mark_email_verified') }}</button>
                            @endif
                        </div>
                    @endcan
                @else
                    <p class="customer-workspace__empty">{{ __('admin.customers.password_no_user') }}</p>
                @endif
            </section>

            <section class="customer-workspace__card" aria-labelledby="credits-heading">
                <header class="customer-workspace__section-header">
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

            <section class="customer-workspace__card" aria-labelledby="access-heading">
                <header class="customer-workspace__section-header">
                    <div>
                        <p class="customer-workspace__eyebrow">{{ __('admin.customers.access_eyebrow') }}</p>
                        <h2 id="access-heading" class="customer-workspace__section-title">{{ __('admin.customers.access_heading') }}</h2>
                    </div>
                </header>
                @if ($user)
                    <div class="customer-workspace__role-list">
                        @forelse (($user->roles ?? collect()) as $role)
                            <span class="ag-badge">{{ $role->name }}</span>
                        @empty
                            <span class="customer-workspace__empty">{{ __('admin.customers.no_roles') }}</span>
                        @endforelse
                    </div>
                    @can('users.update')
                        <form class="customer-workspace__roles-form" wire:submit="saveRoles">
                            <fieldset class="ag-fieldset">
                                <legend class="ag-fieldset__legend">{{ __('admin.customers.roles') }}</legend>
                                <div class="customer-workspace__role-options">
                                    @forelse ($availableRoles as $roleOption)
                                        <label class="ag-check" wire:key="customer-role-{{ $roleOption->id }}">
                                            <input type="checkbox" value="{{ $roleOption->name }}" wire:model="selectedRoles">
                                            <span>{{ $roleOption->name }}</span>
                                        </label>
                                    @empty
                                        <p class="ag-field__help">{{ __('admin.customers.no_roles_available') }}</p>
                                    @endforelse
                                </div>
                                @error('selectedRoles') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                                @error('selectedRoles.*') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                            </fieldset>
                            <button class="ag-btn ag-btn--ghost" type="submit">{{ __('admin.customers.save_roles') }}</button>
                        </form>
                    @endcan
                @else
                    <p class="customer-workspace__empty">{{ __('admin.customers.no_user') }}</p>
                @endif
            </section>

            @can('customers.manage')
                <section class="customer-workspace__card customer-workspace__card--danger" aria-labelledby="actions-heading">
                    <header class="customer-workspace__section-header">
                        <div>
                            <p class="customer-workspace__eyebrow">{{ __('admin.customers.actions_eyebrow') }}</p>
                            <h2 id="actions-heading" class="customer-workspace__section-title">{{ __('admin.customers.actions_heading') }}</h2>
                            <p class="customer-workspace__section-lede">{{ __('admin.customers.actions_lede') }}</p>
                        </div>
                    </header>
                    @if (! $customer->anonymized_at)
                        <div class="customer-workspace__danger-action">
                            <strong>{{ __('admin.customers.anonymize_heading') }}</strong>
                            <p>{{ __('admin.customers.anonymize_lede') }}</p>
                            <button class="ag-btn ag-btn--danger-outline" type="button" wire:click="anonymize" wire:confirm="{{ __('admin.customers.anonymize_confirm') }}">{{ __('admin.customers.anonymize') }}</button>
                        </div>
                    @endif
                    <div class="customer-workspace__danger-action">
                        <strong>{{ __('admin.customers.full_delete_heading') }}</strong>
                        <p>{{ __('admin.customers.full_delete_lede') }}</p>
                        @if ($fullDeleteBlockers === [])
                            <button class="ag-btn ag-btn--danger" type="button" wire:click="fullDelete" wire:confirm="{{ __('admin.customers.full_delete_confirm') }}">{{ __('admin.customers.full_delete') }}</button>
                        @else
                            <p class="customer-workspace__blocked"><strong>{{ __('admin.customers.full_delete_unavailable') }}</strong></p>
                            <ul class="customer-workspace__blocked-list">
                                @foreach ($fullDeleteBlockers as $blocker)
                                    <li>{{ $blocker }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </section>
            @endcan
        </aside>
    </div>

    @include('livewire.admin.partials.confirm-password-modal')
</div>