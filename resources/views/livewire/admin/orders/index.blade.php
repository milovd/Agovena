<div class="admin-page">
    <x-ag.page-header :heading="__('admin.orders.title')" :lede="__('admin.orders.lede')" />

    <div class="ag-toolbar ag-toolbar--filters">
        <div class="ag-toolbar__filters">
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="order-search">{{ __('admin.orders.search_label') }}</label>
                <input id="order-search" class="ag-input ag-input--search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('admin.orders.search_placeholder') }}">
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="order-status">{{ __('admin.orders.status_label') }}</label>
                <select id="order-status" class="ag-select" wire:model.live="status">
                    <option value="">{{ __('admin.orders.status_all') }}</option>
                    @foreach ($orderStatuses as $case)
                        <option value="{{ $case->value }}">{{ __('admin.orders.status.'.$case->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="payment-status">{{ __('admin.orders.payment_status_label') }}</label>
                <select id="payment-status" class="ag-select" wire:model.live="paymentStatus">
                    <option value="">{{ __('admin.orders.payment_status_all') }}</option>
                    @foreach ($paymentStatuses as $case)
                        <option value="{{ $case->value }}">{{ __('admin.orders.payment_status.'.$case->value) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @if ($orders->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ $search || $status || $paymentStatus ? __('admin.orders.empty.filtered_title') : __('admin.orders.empty.title') }}</p>
            <p class="ag-empty__text">
                {{ $search || $status || $paymentStatus ? __('admin.orders.empty.filtered_text') : __('admin.orders.empty.text') }}
            </p>
        </div>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table ag-table--orders">
                <thead>
                    <tr>
                        <th scope="col">{{ __('admin.orders.order_column') }}</th>
                        <th scope="col">{{ __('common.customer') }}</th>
                        <th scope="col">{{ __('common.total') }}</th>
                        <th scope="col">{{ __('common.status') }}</th>
                        <th scope="col" class="ag-table__col--md">{{ __('admin.orders.payment_column') }}</th>
                        <th scope="col" class="ag-table__col--lg">{{ __('common.created') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr wire:key="order-{{ $order->id }}">
                            <td><span class="ag-table__name">{{ $order->number }}</span></td>
                            <td>
                                <div class="ag-table__primary">
                                    <span>{{ $order->customer_name }}</span>
                                    <span class="ag-muted">{{ $order->customer_email }}</span>
                                </div>
                            </td>
                            <td>{{ \App\Support\MoneyFormatter::format($order->total_amount, $order->currency) }}</td>
                            <td>
                                <span @class([
                                    'ag-badge',
                                    'ag-badge--success' => $order->status->value === 'paid',
                                    'ag-badge--warning' => $order->status->value === 'pending',
                                    'ag-badge--muted' => $order->status->value === 'cancelled',
                                ])>{{ __('admin.orders.status.'.$order->status->value) }}</span>
                            </td>
                            <td class="ag-table__col--md">
                                @if ($order->payment)
                                    <span @class([
                                        'ag-badge',
                                        'ag-badge--success' => $order->payment->status->value === 'paid',
                                        'ag-badge--warning' => $order->payment->status->value === 'pending',
                                        'ag-badge--muted' => $order->payment->status->value === 'cancelled',
                                    ])>{{ __('admin.orders.payment_status.'.$order->payment->status->value) }}</span>
                                @else
                                    <span class="ag-muted">{{ __('common.em_dash') }}</span>
                                @endif
                            </td>
                            <td class="ag-table__col--lg">
                                <span class="ag-muted" title="{{ $order->created_at?->toDateTimeString() }}">
                                    {{ $order->created_at?->diffForHumans() }}
                                </span>
                            </td>
                            <td class="ag-table__actions">
                                <a
                                    class="ag-icon-btn"
                                    href="{{ route('admin.orders.show', $order) }}"
                                    title="{{ __('admin.orders.open') }}"
                                    aria-label="{{ __('admin.orders.open_aria', ['number' => $order->number]) }}"
                                >
                                    <x-ag.icon name="external-link" :size="16" />
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="ag-pagination">{{ $orders->links() }}</div>
    @endif
</div>
