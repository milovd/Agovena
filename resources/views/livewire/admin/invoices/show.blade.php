<div class="admin-page admin-invoice">
    <x-ag.page-header
        :heading="$invoice->number"
        :lede="__('admin.invoices.show_lede')"
    >
        <x-slot:back>
            <x-ag.back :href="route('admin.invoices.index')" :label="__('admin.invoices.title')" />
        </x-slot:back>
        <x-slot:actions>
            <a class="ag-btn ag-btn--secondary" href="{{ route('admin.invoices.print', $invoice) }}">
                {{ __('admin.invoices.print') }}
            </a>
            <a class="ag-btn ag-btn--primary" href="{{ route('admin.invoices.pdf', $invoice) }}">
                {{ __('admin.invoices.download_pdf') }}
            </a>
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div class="ag-alert ag-alert--danger" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="ag-order-layout">
        <div class="ag-order-layout__main">
            <section class="ag-section" aria-labelledby="invoice-items-heading">
                <header class="ag-section__header">
                    <h2 id="invoice-items-heading" class="ag-section__title">{{ __('admin.invoices.items') }}</h2>
                </header>
                <div class="ag-section__body">
                    <div class="ag-table-wrap">
                        <table class="ag-table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('common.product') }}</th>
                                    <th scope="col">{{ __('common.quantity') }}</th>
                                    <th scope="col">{{ __('common.total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoice->items as $item)
                                    <tr>
                                        <td>{{ $item->label }}</td>
                                        <td>{{ $item->quantity }}</td>
                                        <td>{{ \App\Support\MoneyFormatter::format($item->line_total_amount, $item->currency) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <dl class="ag-dl ag-dl--totals">
                        <div>
                            <dt>{{ __('common.subtotal') }}</dt>
                            <dd>{{ \App\Support\MoneyFormatter::format($invoice->subtotal_amount, $invoice->currency) }}</dd>
                        </div>
                        @if ($invoice->discount_amount > 0)
                            <div>
                                <dt>{{ __('common.discount') }}</dt>
                                <dd>−{{ \App\Support\MoneyFormatter::format($invoice->discount_amount, $invoice->currency) }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt>{{ __('common.tax') }}</dt>
                            <dd>{{ \App\Support\MoneyFormatter::format($invoice->tax_amount, $invoice->currency) }}</dd>
                        </div>
                        <div class="ag-dl__total">
                            <dt>{{ __('common.total') }}</dt>
                            <dd>{{ \App\Support\MoneyFormatter::format($invoice->total_amount, $invoice->currency) }}</dd>
                        </div>
                    </dl>
                    <p class="ag-muted">{{ __('admin.invoices.immutable_hint') }}</p>
                </div>
            </section>

            @if ($invoice->creditNotes->isNotEmpty())
                <section class="ag-section" aria-labelledby="invoice-credits-heading">
                    <header class="ag-section__header">
                        <h2 id="invoice-credits-heading" class="ag-section__title">{{ __('admin.credit_notes.title') }}</h2>
                    </header>
                    <div class="ag-section__body">
                        <ul class="ag-list">
                            @foreach ($invoice->creditNotes as $note)
                                <li class="ag-list__row">
                                    <div>
                                        <a href="{{ route('admin.credit-notes.show', $note) }}"><strong>{{ $note->number }}</strong></a>
                                        <p class="ag-muted">{{ $note->issued_at?->format('Y-m-d') }} · {{ $note->reason }}</p>
                                    </div>
                                    <strong>{{ \App\Support\MoneyFormatter::format($note->total_amount, $note->currency) }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            @endif

            @if ($invoice->refunds->isNotEmpty())
                <section class="ag-section" aria-labelledby="invoice-refunds-heading">
                    <header class="ag-section__header">
                        <h2 id="invoice-refunds-heading" class="ag-section__title">{{ __('admin.refunds.history') }}</h2>
                    </header>
                    <div class="ag-section__body">
                        <ul class="ag-list">
                            @foreach ($invoice->refunds as $refund)
                                <li class="ag-list__row">
                                    <div>
                                        <strong>{{ \App\Support\MoneyFormatter::format($refund->amount, $refund->currency) }}</strong>
                                        <p class="ag-muted">
                                            {{ __('admin.refunds.status.'.$refund->status->value) }}
                                            · {{ $refund->created_at?->toDayDateTimeString() }}
                                            @if ($refund->reason)
                                                · {{ $refund->reason }}
                                            @endif
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            @endif
        </div>

        <aside class="ag-order-layout__side">
            <section class="ag-section" aria-labelledby="invoice-details-heading">
                <header class="ag-section__header">
                    <h2 id="invoice-details-heading" class="ag-section__title">{{ __('admin.invoices.details') }}</h2>
                </header>
                <div class="ag-section__body">
                    <dl class="ag-dl">
                        <div>
                            <dt>{{ __('common.status') }}</dt>
                            <dd>
                                <span @class([
                                    'ag-badge',
                                    'ag-badge--success' => $invoice->status->value === 'paid',
                                    'ag-badge--info' => $invoice->status->value === 'issued',
                                    'ag-badge--danger' => $invoice->status->value === 'void',
                                ])>{{ __('admin.invoices.status.'.$invoice->status->value) }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt>{{ __('admin.invoices.issued') }}</dt>
                            <dd>{{ $invoice->issued_at?->format('Y-m-d') }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('common.customer') }}</dt>
                            <dd>
                                <span>{{ $invoice->customer_name }}</span>
                                <span class="ag-muted">{{ $invoice->customer_email }}</span>
                            </dd>
                        </div>
                        @if ($invoice->order)
                            <div>
                                <dt>{{ __('admin.invoices.order') }}</dt>
                                <dd><a href="{{ route('admin.orders.show', $invoice->order) }}">{{ $invoice->order->number }}</a></dd>
                            </div>
                        @endif
                        <div>
                            <dt>{{ __('admin.invoices.credited') }}</dt>
                            <dd>{{ \App\Support\MoneyFormatter::format($invoice->creditedAmount(), $invoice->currency) }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('admin.invoices.remaining_creditable') }}</dt>
                            <dd>{{ \App\Support\MoneyFormatter::format($invoice->remainingCreditable(), $invoice->currency) }}</dd>
                        </div>
                        @if ($payment)
                            <div>
                                <dt>{{ __('admin.refunds.refunded') }}</dt>
                                <dd>{{ \App\Support\MoneyFormatter::format($payment->refundedAmount(), $payment->currency) }}</dd>
                            </div>
                            <div>
                                <dt>{{ __('admin.refunds.remaining') }}</dt>
                                <dd>{{ \App\Support\MoneyFormatter::format($payment->remainingRefundable(), $payment->currency) }}</dd>
                            </div>
                        @endif
                        @if ($invoice->billing_line1)
                            <div>
                                <dt>{{ __('admin.invoices.billing') }}</dt>
                                <dd>
                                    {{ $invoice->billing_name }}<br>
                                    {{ $invoice->billing_line1 }}<br>
                                    {{ $invoice->billing_postal_code }} {{ $invoice->billing_city }}<br>
                                    {{ $invoice->billing_country }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </section>

            @if ($canVoid || $canCredit || $canRefund)
                <section class="ag-section" aria-labelledby="invoice-actions-heading">
                    <header class="ag-section__header">
                        <h2 id="invoice-actions-heading" class="ag-section__title">{{ __('admin.invoices.actions') }}</h2>
                    </header>
                    <div class="ag-section__body">
                        <div class="ag-actions ag-actions--stack">
                            @if ($canCredit)
                                <a class="ag-btn ag-btn--secondary" href="{{ route('admin.invoices.credit', $invoice) }}">
                                    {{ __('admin.credit_notes.issue') }}
                                </a>
                            @endif

                            @if ($canVoid)
                                @if (! $confirmingVoid)
                                    <button type="button" class="ag-btn ag-btn--danger-outline" wire:click="startVoid">
                                        {{ __('admin.invoices.void_action') }}
                                    </button>
                                @else
                                    <div class="ag-confirm" role="dialog" aria-labelledby="confirm-void-title" aria-modal="true">
                                        <h4 id="confirm-void-title">{{ __('admin.invoices.void_confirm_title') }}</h4>
                                        <p>{{ __('admin.invoices.void_confirm_text') }}</p>
                                        <div class="ag-confirm__actions">
                                            <button type="button" class="ag-btn ag-btn--danger" wire:click="voidInvoice">{{ __('common.confirm') }}</button>
                                            <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelVoid">{{ __('common.cancel') }}</button>
                                        </div>
                                    </div>
                                @endif
                            @endif

                            @if ($canRefund)
                                @if (! $confirmingRefund)
                                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="startRefund">
                                        {{ __('admin.refunds.record') }}
                                    </button>
                                @else
                                    <div class="ag-confirm" role="dialog" aria-labelledby="confirm-refund-title" aria-modal="true">
                                        <h4 id="confirm-refund-title">{{ __('admin.refunds.confirm_title') }}</h4>
                                        <p>{{ __('admin.refunds.confirm_text') }}</p>
                                        <div class="ag-field">
                                            <label class="ag-field__label" for="refund-amount">{{ __('admin.refunds.amount') }}</label>
                                            <input id="refund-amount" class="ag-input" type="text" wire:model="refundAmount" inputmode="decimal">
                                        </div>
                                        <div class="ag-field">
                                            <label class="ag-field__label" for="refund-reason">{{ __('admin.refunds.reason') }}</label>
                                            <textarea id="refund-reason" class="ag-input" rows="3" wire:model="refundReason"></textarea>
                                        </div>
                                        @if ($invoice->creditNotes->isNotEmpty())
                                            <div class="ag-field">
                                                <label class="ag-field__label" for="refund-credit-note">{{ __('admin.refunds.credit_note_optional') }}</label>
                                                <select id="refund-credit-note" class="ag-select" wire:model="refundCreditNoteId">
                                                    <option value="">{{ __('admin.refunds.no_credit_note') }}</option>
                                                    @foreach ($invoice->creditNotes as $note)
                                                        <option value="{{ $note->id }}">{{ $note->number }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif
                                        <div class="ag-confirm__actions">
                                            <button type="button" class="ag-btn ag-btn--primary" wire:click="recordRefund">{{ __('common.confirm') }}</button>
                                            <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelRefund">{{ __('common.cancel') }}</button>
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                </section>
            @endif
        </aside>
    </div>

    @include('livewire.admin.partials.confirm-password-modal')
</div>
