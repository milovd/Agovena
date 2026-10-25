<section class="ag-card" aria-labelledby="shipping-returns-heading" wire:key="shipping-returns-{{ $order->id }}">
    <header class="ag-card__header">
        <x-ag.icon-tile name="rotate-ccw" tone="amber" />
        <h2 id="shipping-returns-heading" class="ag-card__title">{{ __('shipping::returns.order_section_title') }}</h2>
        <p class="ag-card__description">{{ __('shipping::returns.order_section_lede') }}</p>
    </header>
    <div class="ag-card__content">
        @if ($returns->isEmpty())
            <p class="ag-muted">{{ __('shipping::returns.order_section_empty') }}</p>
        @else
            <ul class="ag-stack" role="list">
                @foreach ($returns as $request)
                    <li wire:key="order-return-{{ $request->id }}">
                        <p>
                            <strong>#{{ $request->id }}</strong>
                            - {{ __('shipping::returns.statuses.'.$request->status->value) }}
                            ·
                            <a href="{{ route('admin.shipping.returns.show', $request) }}">{{ __('shipping::returns.view') }}</a>
                        </p>
                        <ul>
                            @foreach ($request->items as $item)
                                <li>{{ $item->quantity }} × {{ $item->orderItem?->label ?? __('common.em_dash') }}</li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
