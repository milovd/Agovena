{{-- Order detail: line items and totals, editable in place while the order is unpaid. Part of livewire.admin.orders.show. --}}
@php
    $currency = (string) $order->currency;
    $editLines = $canUpdate && $linesLockReason === null;
    $lineTotal = static function (array $line) use ($currency): ?string {
        try {
            $unit = \App\Support\MoneyFormatter::minorFromMajorInput(trim($line['price']), $currency);
        } catch (\InvalidArgumentException) {
            return null;
        }
        $quantity = trim($line['quantity']);

        return ctype_digit($quantity) ? \App\Support\MoneyFormatter::format($unit * (int) $quantity, $currency) : null;
    };
@endphp
<section class="ag-card admin-order__items" aria-labelledby="order-items-heading">
    <header class="ag-card__header">
        <x-ag.icon-tile name="package" tone="blue" />
        <h2 id="order-items-heading" class="ag-card__title">{{ __('admin.orders.show.items') }}</h2>
        @if ($canUpdate && $linesLockReason !== null)
            <span class="ag-card__action admin-order__lock" title="{{ $linesLockReason }}" aria-label="{{ $linesLockReason }}" role="img">
                <x-ag.icon name="lock" :size="16" />
            </span>
        @endif
    </header>
    <div class="ag-card__content">
        @error('lines') <p class="ag-alert ag-alert--danger" role="alert">{{ $message }}</p> @enderror

        <div class="ag-table-wrap admin-order__lines-wrap" role="region" aria-label="{{ __('admin.orders.show.items') }}" tabindex="0">
            <table class="ag-table admin-order__lines">
                <thead>
                    <tr>
                        <th scope="col">{{ __('common.product') }}</th>
                        <th scope="col" class="admin-order__col-qty">{{ __('common.quantity') }}</th>
                        <th scope="col" class="admin-order__col-price">{{ __('admin.orders.inline.price') }}</th>
                        <th scope="col" class="admin-order__col-total">{{ __('admin.orders.show.line') }}</th>
                        @if ($editLines)
                            <th scope="col" class="admin-order__col-action"><span class="visually-hidden">{{ __('admin.orders.actions.more') }}</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $index => $line)
                        @php($recurring = $lineRecurring[$line['key']] ?? null)
                        <tr wire:key="order-line-{{ $line['key'] }}">
                            <td>
                                <div class="admin-order__line">
                                    @if ($editLines)
                                        <x-ag.search-select
                                            :id="'order-line-'.$index"
                                            :field="'line.'.$index"
                                            :selected="$line['label']"
                                            :placeholder="__('admin.orders.inline.product')"
                                            :open="$picker === 'line.'.$index"
                                            :results="$picker === 'line.'.$index ? $pickerResults : []"
                                            :invalid="$errors->has('lines.'.$index.'.product')"
                                        />
                                    @else
                                        <span>{{ $line['label'] }}</span>
                                    @endif
                                    @if ($recurring)
                                        <span class="admin-order__line-meta">
                                            <x-ag.icon name="repeat" :size="13" />
                                            <span>{{ $recurring['count'] }} × {{ __('subscriptions::interval.'.$recurring['interval']) }}</span>
                                            @if ($recurring['subscription'])
                                                @if ($canViewSubscriptions)
                                                    <a href="{{ route('admin.subscriptions.show', $recurring['subscription']) }}">{{ __('admin.orders.inline.subscription', ['number' => $recurring['subscription']->number]) }}</a>
                                                @else
                                                    <span>{{ __('admin.orders.inline.subscription', ['number' => $recurring['subscription']->number]) }}</span>
                                                @endif
                                            @endif
                                        </span>
                                    @endif
                                    @error('lines.'.$index.'.product') <span class="ag-field__error" role="alert">{{ $message }}</span> @enderror
                                </div>
                            </td>
                            <td class="admin-order__col-qty">
                                @if ($editLines)
                                    <input class="ag-input admin-order__qty" type="number" min="1" max="10000" inputmode="numeric" wire:model.blur="lines.{{ $index }}.quantity" aria-label="{{ __('common.quantity') }}" @class(['is-invalid' => $errors->has('lines.'.$index.'.quantity')])>
                                @else
                                    {{ $line['quantity'] }}
                                @endif
                            </td>
                            <td class="admin-order__col-price">
                                @if ($editLines)
                                    <input class="ag-input admin-order__price" type="text" inputmode="decimal" wire:model.blur="lines.{{ $index }}.price" aria-label="{{ __('admin.orders.inline.price') }}" @class(['is-invalid' => $errors->has('lines.'.$index.'.price')])>
                                @else
                                    {{ \App\Support\MoneyFormatter::format(\App\Support\MoneyFormatter::minorFromMajorInput($line['price'], $currency), $currency) }}
                                @endif
                            </td>
                            <td class="admin-order__col-total">{{ $lineTotal($line) ?? __('common.em_dash') }}</td>
                            @if ($editLines)
                                <td class="admin-order__col-action">
                                    <button type="button" class="ag-icon-btn ag-icon-btn--danger" wire:click="removeLine({{ $index }})" title="{{ __('admin.orders.inline.remove_line') }}" aria-label="{{ __('admin.orders.inline.remove_line') }}">
                                        <x-ag.icon name="trash" :size="16" />
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($editLines)
            <button type="button" class="ag-icon-btn admin-order__add-line" wire:click="addLine" title="{{ __('admin.orders.inline.add_line') }}" aria-label="{{ __('admin.orders.inline.add_line') }}">
                <x-ag.icon name="plus" :size="16" />
            </button>
        @endif

        <dl class="admin-order__totals">
            <div>
                <dt>{{ __('common.subtotal') }}</dt>
                <dd>{{ \App\Support\MoneyFormatter::format($order->subtotal_amount, $currency) }}</dd>
            </div>
            @if ((int) $order->discount_amount > 0)
                <div>
                    <dt>{{ __('common.discount') }} @if ($order->discount_code)<span class="ag-muted">({{ $order->discount_code }})</span>@endif</dt>
                    <dd>-{{ \App\Support\MoneyFormatter::format((int) $order->discount_amount, $currency) }}</dd>
                </div>
            @endif
            @if (($order->shipping_amount ?? 0) > 0 || filled($order->shipping_method_label))
                <div>
                    <dt>
                        {{ __('common.shipping') }}
                        @if ($order->shipping_method_label)
                            <span class="ag-muted">({{ $order->shipping_method_label }})</span>
                        @endif
                    </dt>
                    <dd>{{ \App\Support\MoneyFormatter::format((int) $order->shipping_amount, $currency) }}</dd>
                </div>
            @endif
            @if ((int) $order->tax_amount > 0)
                <div>
                    <dt>{{ $order->tax_rate_name ?: __('common.tax') }}</dt>
                    <dd>{{ \App\Support\MoneyFormatter::format((int) $order->tax_amount, $currency) }}</dd>
                </div>
            @endif
            @if ((int) $order->payment_fee_amount > 0)
                <div>
                    <dt>{{ __('common.payment_fee') }}</dt>
                    <dd>{{ \App\Support\MoneyFormatter::format((int) $order->payment_fee_amount, $currency) }}</dd>
                </div>
            @endif
            <div class="admin-order__total">
                <dt>{{ __('common.total') }}</dt>
                <dd>{{ \App\Support\MoneyFormatter::format($order->total_amount, $currency) }}</dd>
            </div>
        </dl>
    </div>
</section>
