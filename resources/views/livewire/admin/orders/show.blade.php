<div class="admin-page admin-page--order">
    <x-ag.page-header :heading="__('admin.orders.show.title', ['number' => $order->number])" :lede="__('admin.orders.show.lede')">
        <x-slot:breadcrumbs>
            <x-ag.breadcrumbs :items="[
                ['label' => __('admin.nav_groups.overview'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.orders.title'), 'url' => route('admin.orders.index')],
                ['label' => $order->number],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:back>
            <x-ag.back :href="route('admin.orders.index')" :label="__('admin.orders.title')" />
        </x-slot:back>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <div class="ag-order-layout">
        <div class="ag-order-layout__main">
            <section class="ag-section" aria-labelledby="order-items-heading">
                <header class="ag-section__header">
                    <h3 id="order-items-heading" class="ag-section__title">{{ __('admin.orders.show.items') }}</h3>
                    <p class="ag-section__lede">{{ __('admin.orders.show.items_lede') }}</p>
                </header>
                <div class="ag-section__body">
                    <div class="ag-table-wrap">
                        <table class="ag-table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('common.product') }}</th>
                                    <th scope="col">{{ __('common.quantity') }}</th>
                                    <th scope="col">{{ __('admin.orders.show.unit') }}</th>
                                    <th scope="col">{{ __('admin.orders.show.line') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($order->items as $item)
                                    <tr>
                                        <td>{{ $item->label }}</td>
                                        <td>{{ $item->quantity }}</td>
                                        <td>{{ \App\Support\MoneyFormatter::format($item->unit_amount, $item->currency) }}</td>
                                        <td>{{ \App\Support\MoneyFormatter::format($item->line_total_amount, $item->currency) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            @foreach ($orderDetailSections ?? [] as $section)
                @livewire($section->component, ['order' => $order], key($section->id.'-'.$order->id))
            @endforeach
        </div>

        <aside class="ag-order-layout__side">
            <section class="ag-section" aria-labelledby="order-summary-heading">
                <header class="ag-section__header">
                    <h3 id="order-summary-heading" class="ag-section__title">{{ __('admin.orders.show.summary') }}</h3>
                </header>
                <div class="ag-section__body">
                    <dl class="ag-dl">
                        <div>
                            <dt>{{ __('admin.orders.status_label') }}</dt>
                            <dd>
                                <span @class([
                                    'ag-badge',
                                    'ag-badge--success' => $order->status->value === 'paid',
                                    'ag-badge--warning' => $order->status->value === 'pending',
                                    'ag-badge--muted' => $order->status->value === 'cancelled',
                                ])>{{ __('admin.orders.status.'.$order->status->value) }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt>{{ __('common.created') }}</dt>
                            <dd>{{ $order->created_at?->toDayDateTimeString() }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('common.subtotal') }}</dt>
                            <dd>{{ \App\Support\MoneyFormatter::format($order->subtotal_amount, $order->currency) }}</dd>
                        </div>
                        @if (($order->shipping_amount ?? 0) > 0 || filled($order->shipping_method_label))
                            <div>
                                <dt>{{ __('common.shipping') }}</dt>
                                <dd>
                                    {{ \App\Support\MoneyFormatter::format((int) $order->shipping_amount, $order->currency) }}
                                    @if ($order->shipping_method_label)
                                        <span class="ag-muted">({{ $order->shipping_method_label }})</span>
                                    @endif
                                </dd>
                            </div>
                        @endif
                        <div>
                            <dt>{{ __('common.total') }}</dt>
                            <dd><strong>{{ \App\Support\MoneyFormatter::format($order->total_amount, $order->currency) }}</strong></dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="ag-section" aria-labelledby="customer-heading">
                <header class="ag-section__header">
                    <h3 id="customer-heading" class="ag-section__title">{{ __('common.customer') }}</h3>
                </header>
                <div class="ag-section__body">
                    <dl class="ag-dl">
                        <div><dt>{{ __('common.name') }}</dt><dd>{{ $order->customer_name }}</dd></div>
                        <div><dt>{{ __('common.email') }}</dt><dd><a href="mailto:{{ $order->customer_email }}">{{ $order->customer_email }}</a></dd></div>
                    </dl>
                </div>
            </section>

            <section class="ag-section" aria-labelledby="payment-heading">
                <header class="ag-section__header">
                    <h3 id="payment-heading" class="ag-section__title">{{ __('admin.orders.show.payment') }}</h3>
                </header>
                <div class="ag-section__body">
                    @if ($order->payment)
                        <dl class="ag-dl">
                            <div><dt>{{ __('common.method') }}</dt><dd>{{ $paymentGatewayLabel }}</dd></div>
                            <div>
                                <dt>{{ __('common.status') }}</dt>
                                <dd>
                                    <span @class([
                                        'ag-badge',
                                        'ag-badge--success' => $order->payment->status->value === 'paid',
                                        'ag-badge--warning' => $order->payment->status->value === 'pending',
                                        'ag-badge--muted' => in_array($order->payment->status->value, ['cancelled', 'failed', 'expired'], true),
                                        'ag-badge--info' => $order->payment->status->value === 'partially_refunded',
                                        'ag-badge--danger' => $order->payment->status->value === 'refunded',
                                    ])>{{ __('admin.orders.payment_status.'.$order->payment->status->value) }}</span>
                                </dd>
                            </div>
                            <div><dt>{{ __('common.amount') }}</dt><dd>{{ \App\Support\MoneyFormatter::format($order->payment->amount, $order->payment->currency) }}</dd></div>
                            @if ($order->payment->refundedAmount() > 0)
                                <div>
                                    <dt>{{ __('admin.refunds.refunded') }}</dt>
                                    <dd>{{ \App\Support\MoneyFormatter::format($order->payment->refundedAmount(), $order->payment->currency) }}</dd>
                                </div>
                                <div>
                                    <dt>{{ __('admin.refunds.net_paid') }}</dt>
                                    <dd>{{ \App\Support\MoneyFormatter::format($order->payment->amount - $order->payment->refundedAmount(), $order->payment->currency) }}</dd>
                                </div>
                            @endif
                            @if ($order->payment->paid_at)
                                <div><dt>{{ __('admin.orders.show.paid_at') }}</dt><dd>{{ $order->payment->paid_at->toDayDateTimeString() }}</dd></div>
                            @endif
                            @if ($order->payment->reference)
                                <div><dt>{{ __('common.reference') }}</dt><dd>{{ $order->payment->reference }}</dd></div>
                            @endif
                        </dl>

                        @if ($order->payment->attempts->isNotEmpty())
                            <h4 class="ag-section__title" style="margin-top:1rem;">{{ __('admin.orders.show.attempts') }}</h4>
                            <div class="ag-table-wrap">
                                <table class="ag-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">{{ __('admin.orders.show.gateway') }}</th>
                                            <th scope="col">{{ __('admin.orders.show.provider_reference') }}</th>
                                            <th scope="col">{{ __('common.status') }}</th>
                                            <th scope="col">{{ __('common.created') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($order->payment->attempts as $attempt)
                                            <tr>
                                                <td>{{ $attempt->gateway_id }}</td>
                                                <td>{{ $attempt->external_id ?: '-' }}</td>
                                                <td>{{ __('admin.orders.attempt_status.'.$attempt->status->value) }}</td>
                                                <td>{{ $attempt->created_at?->toDayDateTimeString() }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if ($order->refunds->isNotEmpty())
                            <h4 class="ag-section__title" style="margin-top:1rem;">{{ __('admin.orders.show.refunds') }}</h4>
                            <ul class="ag-list">
                                @foreach ($order->refunds as $refund)
                                    <li>
                                        {{ \App\Support\MoneyFormatter::format($refund->amount, $refund->currency) }}
                                        - {{ __('admin.refunds.status.'.$refund->status->value) }}
                                        @if ($refund->provider_reference)
                                            ({{ $refund->provider_reference }})
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($canRecord)
                            @if (! $confirmingPayment)
                                <button type="button" class="ag-btn ag-btn--primary" wire:click="startRecordPayment">
                                    {{ __('admin.orders.show.record_payment') }}
                                </button>
                            @else
                                <div class="ag-confirm" role="dialog" aria-labelledby="confirm-payment-title" aria-modal="true">
                                    <h4 id="confirm-payment-title">{{ __('admin.orders.show.confirm_title') }}</h4>
                                    <p>{{ __('admin.orders.show.confirm_text') }}</p>
                                    <div class="ag-field">
                                        <label class="ag-field__label" for="reference">{{ __('admin.orders.show.reference_label') }}</label>
                                        <input id="reference" class="ag-input" type="text" wire:model="reference">
                                    </div>
                                    <div class="ag-confirm__actions">
                                        <button type="button" class="ag-btn ag-btn--primary" wire:click="recordPayment">{{ __('common.confirm') }}</button>
                                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelRecordPayment">{{ __('common.cancel') }}</button>
                                    </div>
                                </div>
                            @endif
                        @endif
                    @else
                        <p class="ag-muted">{{ __('admin.orders.show.no_payment') }}</p>
                    @endif

                    @if ($canCancelUnpaid)
                        @if (! $confirmingCancel)
                            <button type="button" class="ag-btn ag-btn--danger" wire:click="startCancelUnpaid" style="margin-top: var(--ag-space-3);">
                                {{ __('admin.orders.show.cancel_unpaid') }}
                            </button>
                        @else
                            <div class="ag-confirm" role="dialog" aria-labelledby="confirm-cancel-title" aria-modal="true">
                                <h4 id="confirm-cancel-title">{{ __('admin.orders.show.cancel_confirm_title') }}</h4>
                                <p>{{ __('admin.orders.show.cancel_confirm_text') }}</p>
                                <div class="ag-confirm__actions">
                                    <button type="button" class="ag-btn ag-btn--danger" wire:click="cancelUnpaid">{{ __('common.confirm') }}</button>
                                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="abortCancelUnpaid">{{ __('common.cancel') }}</button>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </section>

            @if ($order->invoice || $order->creditNotes->isNotEmpty())
                <section class="ag-section" aria-labelledby="documents-heading">
                    <header class="ag-section__header">
                        <h3 id="documents-heading" class="ag-section__title">{{ __('admin.orders.show.documents') }}</h3>
                    </header>
                    <div class="ag-section__body">
                        <dl class="ag-dl">
                            @if ($order->invoice)
                                <div>
                                    <dt>{{ __('admin.invoices.number') }}</dt>
                                    <dd><a href="{{ route('admin.invoices.show', $order->invoice) }}">{{ $order->invoice->number }}</a></dd>
                                </div>
                            @endif
                            @foreach ($order->creditNotes as $note)
                                <div>
                                    <dt>{{ __('admin.credit_notes.singular') }}</dt>
                                    <dd><a href="{{ route('admin.credit-notes.show', $note) }}">{{ $note->number }}</a></dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                </section>
            @endif
        </aside>
    </div>

    @include('livewire.admin.partials.confirm-password-modal')
</div>
