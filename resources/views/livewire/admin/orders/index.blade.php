<div class="admin-page">
    <x-ag.page-header heading="Orders" lede="Review guest checkouts and payment status." />

    <div class="ag-toolbar ag-toolbar--filters">
        <div class="ag-toolbar__filters">
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="order-search">Search orders</label>
                <input id="order-search" class="ag-input ag-input--search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search number, name, email">
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="order-status">Order status</label>
                <select id="order-status" class="ag-select" wire:model.live="status">
                    <option value="">All order statuses</option>
                    @foreach ($orderStatuses as $case)
                        <option value="{{ $case->value }}">{{ ucfirst($case->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="payment-status">Payment status</label>
                <select id="payment-status" class="ag-select" wire:model.live="paymentStatus">
                    <option value="">All payment statuses</option>
                    @foreach ($paymentStatuses as $case)
                        <option value="{{ $case->value }}">{{ ucfirst($case->value) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @if ($orders->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ $search || $status || $paymentStatus ? 'No matching orders' : 'No orders yet' }}</p>
            <p class="ag-empty__text">
                {{ $search || $status || $paymentStatus ? 'Try adjusting search or filters.' : 'Orders appear here after guest checkout on the storefront.' }}
            </p>
        </div>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table ag-table--orders">
                <thead>
                    <tr>
                        <th scope="col">Order</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Total</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="ag-table__col--md">Payment</th>
                        <th scope="col" class="ag-table__col--lg">Created</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
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
                                ])>{{ ucfirst($order->status->value) }}</span>
                            </td>
                            <td class="ag-table__col--md">
                                @if ($order->payment)
                                    <span @class([
                                        'ag-badge',
                                        'ag-badge--success' => $order->payment->status->value === 'paid',
                                        'ag-badge--warning' => $order->payment->status->value === 'pending',
                                        'ag-badge--muted' => $order->payment->status->value === 'cancelled',
                                    ])>{{ ucfirst($order->payment->status->value) }}</span>
                                @else
                                    <span class="ag-muted">—</span>
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
                                    title="Open order"
                                    aria-label="Open order {{ $order->number }}"
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
