{{-- Order detail: line items. Part of livewire.admin.orders.show. --}}
<section class="ag-section" aria-labelledby="order-items-heading">
    <header class="ag-section__header">
        <h3 id="order-items-heading" class="ag-section__title">{{ __('admin.orders.show.items') }}</h3>
        <p class="ag-section__lede">{{ __('admin.orders.show.items_lede') }}</p>
    </header>
    <div class="ag-section__body">
        <div class="ag-table-wrap" role="region" aria-label="{{ __('admin.orders.show.items') }}" tabindex="0">
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
