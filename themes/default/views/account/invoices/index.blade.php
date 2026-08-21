<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        @include('theme::account.partials.breadcrumbs', [
            'items' => [
                ['label' => __('customer.account.nav_overview'), 'url' => route('customer.account')],
                ['label' => __('customer.account.invoices_title')],
            ],
        ])

        <header class="store-support-hero store-support-hero--compact">
            <div class="store-support-hero__copy">
                <span class="store-support-hero__icon" aria-hidden="true">
                    <x-ag.icon name="file-text" :size="22" />
                </span>
                <div>
                    <h1 class="store-support-hero__title">{{ __('customer.account.invoices_title') }}</h1>
                    <p class="store-support-hero__lede">{{ __('customer.account.invoices_lede') }}</p>
                </div>
            </div>
        </header>

        @if ($invoices->isEmpty())
            <x-ag.empty :title="__('customer.account.no_invoices')">
                <x-slot:icon>
                    <x-ag.icon name="file-text" :size="22" />
                </x-slot:icon>
                <x-slot:description>{{ __('customer.account.no_invoices_hint') }}</x-slot:description>
            </x-ag.empty>
        @else
            <div class="store-account-card-list" role="list">
                @foreach ($invoices as $invoice)
                    @php
                        $statusClass = match ($invoice->status->value) {
                            'paid' => 'is-success',
                            'void' => 'is-muted',
                            default => 'is-warning',
                        };
                    @endphp
                    <article class="store-account-entry" role="listitem">
                        <div class="store-account-entry__body">
                            <p class="store-account-entry__title">{{ $invoice->number }}</p>
                            <p class="store-account-entry__meta">
                                {{ $invoice->issued_at?->timezone(config('app.timezone'))?->locale(app()->getLocale())->translatedFormat('j F Y') }}
                            </p>
                            <p class="store-order-card__status {{ $statusClass }}">
                                {{ __('customer.account.invoice_statuses.'.$invoice->status->value) }}
                            </p>
                        </div>
                        <div class="store-account-entry__end">
                            <strong>{{ \App\Support\MoneyFormatter::formatDisplay($invoice->total_amount, $invoice->currency) }}</strong>
                            <a class="store-account-entry__action" href="{{ route('customer.invoices.show', $invoice) }}">
                                <x-ag.icon name="chevron-right" :size="16" />
                                <span class="visually-hidden">{{ __('customer.account.view_invoice') }}</span>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="store-account-panel__pagination">{{ $invoices->links() }}</div>
        @endif

        <section class="store-account-dashboard__recent" aria-labelledby="credit-notes-heading">
            <div class="store-account-dashboard__recent-head">
                <h2 id="credit-notes-heading" class="store-account-dashboard__section-title">{{ __('customer.account.credit_notes_title') }}</h2>
            </div>

            @if ($creditNotes->isEmpty())
                <p class="store-account-panel__empty">{{ __('customer.account.no_credit_notes') }}</p>
            @else
                <div class="store-account-card-list" role="list">
                    @foreach ($creditNotes as $creditNote)
                        <article class="store-account-entry" role="listitem">
                            <div class="store-account-entry__body">
                                <p class="store-account-entry__title">{{ $creditNote->number }}</p>
                                <p class="store-account-entry__meta">
                                    {{ $creditNote->issued_at?->timezone(config('app.timezone'))?->locale(app()->getLocale())->translatedFormat('j F Y') }}
                                </p>
                            </div>
                            <div class="store-account-entry__end">
                                <strong>{{ \App\Support\MoneyFormatter::formatDisplay($creditNote->total_amount, $creditNote->currency) }}</strong>
                                <a class="store-account-entry__action" href="{{ route('customer.credit-notes.show', $creditNote) }}">
                                    <x-ag.icon name="chevron-right" :size="16" />
                                    <span class="visually-hidden">{{ __('customer.account.view_credit_note') }}</span>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="store-account-panel__pagination">{{ $creditNotes->links() }}</div>
            @endif
        </section>
    </section>
</div>
