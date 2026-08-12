@if ($recentOrders->isEmpty())
    <div class="ag-empty" role="status">
        <p class="ag-empty__title">{{ __('admin.dashboard.recent_orders.empty_title') }}</p>
        <p class="ag-empty__text">{{ __('admin.dashboard.recent_orders.empty_text') }}</p>
    </div>
@else
    <div class="ag-table-wrap">
        <table class="ag-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('admin.dashboard.recent_orders.number') }}</th>
                    <th scope="col">{{ __('admin.dashboard.recent_orders.customer') }}</th>
                    <th scope="col">{{ __('common.status') }}</th>
                    <th scope="col">{{ __('admin.dashboard.recent_orders.total') }}</th>
                    <th scope="col"><span class="visually-hidden">{{ __('admin.dashboard.recent_orders.open') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recentOrders as $order)
                    <tr wire:key="recent-order-{{ $order->id }}">
                        <td>{{ $order->number }}</td>
                        <td>{{ $order->customer_name }}</td>
                        <td><span class="ag-badge">{{ __('admin.orders.status.'.$order->status->value) }}</span></td>
                        <td>{{ \App\Support\MoneyFormatter::format($order->total_amount, $order->currency) }}</td>
                        <td>
                            @can('orders.view')
                                <a class="ag-btn ag-btn--ghost" href="{{ route('admin.orders.show', $order) }}">{{ __('common.view') }}</a>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
