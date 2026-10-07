{{-- Customer workspace: orders, invoices and activity card. Part of livewire.admin.customers.show. --}}
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
