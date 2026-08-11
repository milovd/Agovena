@if ($recentOrders->isEmpty())
    <div class="ag-empty" role="status">
        <p class="ag-empty__title">No orders yet</p>
        <p class="ag-empty__text">Guest checkout orders will appear here.</p>
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
                    <th scope="col"><span class="visually-hidden">Open</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recentOrders as $order)
                    <tr wire:key="recent-order-{{ $order->id }}">
                        <td>{{ $order->number }}</td>
                        <td>{{ $order->customer_name }}</td>
                        <td><span class="ag-badge">{{ $order->status->value }}</span></td>
                        <td>{{ \App\Support\MoneyFormatter::format($order->total_amount, $order->currency) }}</td>
                        <td>
                            @can('orders.view')
                                <a class="ag-btn ag-btn--ghost" href="{{ route('admin.orders.show', $order) }}">View</a>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
