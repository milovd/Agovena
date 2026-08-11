<div class="admin-page">
    <div class="admin-page__header">
        <h2 class="admin-page__heading">Orders</h2>
    </div>

    @if ($orders->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">No orders yet</p>
            <p class="ag-empty__text">Orders appear here after guest checkout on the storefront.</p>
        </div>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th scope="col">Number</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Status</th>
                        <th scope="col">Total</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr wire:key="order-{{ $order->id }}">
                            <td>{{ $order->number }}</td>
                            <td>{{ $order->customer_name }}</td>
                            <td><span class="ag-badge">{{ $order->status->value }}</span></td>
                            <td>{{ \App\Support\MoneyFormatter::format($order->total_amount, $order->currency) }}</td>
                            <td>
                                <a class="ag-btn ag-btn--ghost" href="{{ route('admin.orders.show', $order) }}">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
    @endif
</div>
