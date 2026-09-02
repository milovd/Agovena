@php
    $unpaid = $unpaidOrders ?? collect();
    $firstName = trim(explode(' ', (string) $customer->name)[0] ?: (string) $customer->name);
@endphp

<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <div class="store-account-dashboard">
            <header class="store-account-dashboard__welcome">
                <div class="store-account-dashboard__welcome-top">
                    <p class="store-account-dashboard__kicker">{{ __('customer.account.overview_title') }}</p>
                    <p class="store-account-dashboard__customer-no">{{ __('customer.account.customer_number', ['number' => $customer->id]) }}</p>
                </div>
                <div class="store-account-dashboard__welcome-main">
                    <div class="store-account-dashboard__welcome-copy">
                        <h1 class="store-account-dashboard__welcome-title">{{ __('customer.account.welcome', ['name' => $firstName]) }}</h1>
                        <p class="store-account-dashboard__welcome-lede">{{ __('customer.account.overview_lede') }}</p>
                    </div>
                    <div class="store-account-dashboard__welcome-actions">
                        <a class="store-btn store-btn--primary" href="{{ route('customer.tickets.index') }}">{{ __('customer.account.nav_tickets') }}</a>
                        <a class="store-btn store-btn--outline" href="{{ route('customer.profile') }}">{{ __('customer.account.nav_profile') }}</a>
                    </div>
                </div>
            </header>

            @if (($overviewCards ?? []) !== [])
                <div class="store-account-dashboard__metrics" role="list">
                    @foreach ($overviewCards as $card)
                        @if ($card->routeName)
                            <a
                                class="store-account-card store-account-card--link"
                                href="{{ route($card->routeName, $card->routeParams ?? []) }}"
                                role="listitem"
                            >
                        @else
                            <div class="store-account-card" role="listitem">
                        @endif
                            <p class="store-account-dashboard__value">{{ $card->countOrValue }}</p>
                            <p class="store-account-dashboard__label">{{ __($card->label) }}</p>
                            @if ($card->hint)
                                <p class="store-account-dashboard__hint">{{ __($card->hint) }}</p>
                            @endif
                        @if ($card->routeName)
                            </a>
                        @else
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            @if ($referralsEnabled)
                @php $referralPercentage = $referralCode?->reward_percentage ?? $referralRewardPercentage; @endphp
                <x-ag.card class="store-account-dashboard__referral-card">
                    <x-ag.card.header>
                        <x-ag.card.title>{{ __('customer.account.referral_card_heading') }}</x-ag.card.title>
                        <x-ag.card.description>{{ __('customer.account.referral_card_lede', ['percentage' => $referralPercentage]) }}</x-ag.card.description>
                    </x-ag.card.header>
                    <x-ag.card.content>
                        @if ($referralCode)
                            <p class="store-account-entry__meta">{{ __('customer.account.referral_card_code', ['code' => $referralCode->code]) }}</p>
                        @else
                            <p class="store-account-entry__meta">{{ __('customer.account.referral_card_create_hint') }}</p>
                        @endif
                    </x-ag.card.content>
                    <x-ag.card.footer>
                        <a class="store-btn store-btn--primary" href="{{ route('customer.referrals') }}">{{ __('customer.account.referral_card_cta') }}</a>
                    </x-ag.card.footer>
                </x-ag.card>
            @endif

            @if (! $emailVerified)
                <x-ag.card class="ag-card--alert">
                    <x-ag.card.header>
                        <x-ag.card.title>{{ __('customer.account.email_status') }}</x-ag.card.title>
                        <x-ag.card.description>{{ __('customer.account.email_needs_verification_hint') }}</x-ag.card.description>
                    </x-ag.card.header>
                    <x-ag.card.footer>
                        <a class="store-btn store-btn--primary" href="{{ route('customer.verification.notice') }}">{{ __('customer.auth.resend_verification') }}</a>
                    </x-ag.card.footer>
                </x-ag.card>
            @endif

            @if ($unpaid->isNotEmpty())
                <x-ag.card class="ag-card--alert">
                    <x-ag.card.header>
                        <x-ag.card.title>{{ __('customer.account.unpaid_heading') }}</x-ag.card.title>
                        <x-ag.card.description>{{ __('customer.account.unpaid_lede') }}</x-ag.card.description>
                    </x-ag.card.header>
                    <x-ag.card.content>
                        <ul class="ag-row-list" role="list">
                            @foreach ($unpaid as $order)
                                <li class="ag-row-list__item">
                                    <div>
                                        <a class="ag-row-list__title" href="{{ route('customer.orders.show', $order) }}">{{ $order->number }}</a>
                                        <p class="ag-row-list__meta">{{ __('customer.account.payment_statuses.'.($order->payment?->status->value ?? 'pending')) }}</p>
                                    </div>
                                    <div class="ag-row-list__end">
                                        <a class="store-btn store-btn--primary" href="{{ route('customer.orders.show', $order) }}">{{ __('customer.account.pay_now') }}</a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </x-ag.card.content>
                </x-ag.card>
            @endif

            <div class="store-account-dashboard__grid">
                <section class="store-account-dashboard__recent" aria-labelledby="recent-orders-heading">
                    <div class="store-account-dashboard__recent-head">
                        <div>
                            <h2 id="recent-orders-heading" class="store-account-dashboard__section-title">{{ __('customer.account.recent_orders') }}</h2>
                            <p class="store-account-dashboard__hint">{{ __('customer.account.recent_orders_lede') }}</p>
                        </div>
                        @if ($recentOrders->isNotEmpty())
                            <a class="store-account-dashboard__more" href="{{ route('customer.orders.index') }}">{{ __('customer.account.view_all_orders') }}</a>
                        @endif
                    </div>

                    @if ($recentOrders->isEmpty())
                        <x-ag.empty :title="__('customer.account.no_orders')">
                            <x-slot:icon>
                                <x-ag.icon name="shopping-bag" :size="22" />
                            </x-slot:icon>
                            <x-slot:description>{{ __('customer.account.no_orders_hint') }}</x-slot:description>
                            <x-slot:actions>
                                <a class="store-btn store-btn--outline" href="{{ route('storefront.home') }}">{{ __('customer.account.browse_catalog') }}</a>
                            </x-slot:actions>
                        </x-ag.empty>
                    @else
                        <div class="store-order-list store-order-list--recent" role="list">
                            @foreach ($recentOrders as $order)
                                <div role="listitem">
                                    @include('theme::account.partials.order-card', ['order' => $order, 'compact' => true])
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="store-account-dashboard__recent" aria-labelledby="recent-invoices-heading">
                    <div class="store-account-dashboard__recent-head">
                        <div>
                            <h2 id="recent-invoices-heading" class="store-account-dashboard__section-title">{{ __('customer.account.recent_invoices') }}</h2>
                            <p class="store-account-dashboard__hint">{{ __('customer.account.recent_invoices_lede') }}</p>
                        </div>
                        @if ($recentInvoices->isNotEmpty())
                            <a class="store-account-dashboard__more" href="{{ route('customer.invoices.index') }}">{{ __('customer.account.view_all_invoices') }}</a>
                        @endif
                    </div>

                    @if ($recentInvoices->isEmpty())
                        <x-ag.empty :title="__('customer.account.no_invoices')">
                            <x-slot:icon>
                                <x-ag.icon name="file-text" :size="22" />
                            </x-slot:icon>
                            <x-slot:description>{{ __('customer.account.no_invoices_hint') }}</x-slot:description>
                        </x-ag.empty>
                    @else
                        <ul class="ag-row-list" role="list">
                            @foreach ($recentInvoices as $invoice)
                                @php
                                    $invoiceVariant = match ($invoice->status->value) {
                                        'paid' => 'success',
                                        'void' => 'muted',
                                        default => 'info',
                                    };
                                @endphp
                                <li class="ag-row-list__item">
                                    <div>
                                        <a class="ag-row-list__title" href="{{ route('customer.invoices.show', $invoice) }}">{{ $invoice->number }}</a>
                                        <p class="ag-row-list__meta">{{ $invoice->issued_at?->format('Y-m-d') }}</p>
                                    </div>
                                    <div class="ag-row-list__end">
                                        <x-ag.badge :variant="$invoiceVariant">{{ __('customer.account.invoice_statuses.'.$invoice->status->value) }}</x-ag.badge>
                                        <strong>{{ \App\Support\MoneyFormatter::formatDisplay($invoice->total_amount, $invoice->currency) }}</strong>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        </div>
    </section>
</div>
