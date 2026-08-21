@props([
    'order',
    'compact' => false,
])

@php
    $created = $order->created_at?->timezone(config('app.timezone'))?->locale(app()->getLocale());
    $dateLabel = $created?->translatedFormat('j F Y') ?? __('common.em_dash');
    $statusValue = $order->status->value;
    $statusClass = match ($statusValue) {
        'paid' => 'is-success',
        'cancelled' => 'is-muted',
        default => 'is-warning',
    };
@endphp

<article class="store-order-card {{ $compact ? 'store-order-card--compact' : '' }}">
    <p class="store-order-card__meta">
        {{ __('customer.account.order_card_heading', [
            'date' => $dateLabel,
            'number' => $order->number,
        ]) }}
    </p>

    <div class="store-order-card__panel">
        <ul class="store-order-card__items" role="list">
            @forelse ($order->items as $item)
                @php
                    $imageUrl = \App\Agovena\Media\ProductMedia::primaryUrl($item->product);
                    $productUrl = $item->product?->slug
                        ? route('storefront.product', $item->product->slug)
                        : null;
                @endphp
                <li class="store-order-card__item">
                    <div class="store-order-card__thumb" aria-hidden="true">
                        @if ($imageUrl)
                            <img src="{{ $imageUrl }}" alt="">
                        @else
                            <span class="store-order-card__thumb-placeholder"></span>
                        @endif
                    </div>
                    <div class="store-order-card__item-body">
                        @if ($productUrl)
                            <a class="store-order-card__item-title" href="{{ $productUrl }}">{{ $item->label }}</a>
                        @else
                            <p class="store-order-card__item-title">{{ $item->label }}</p>
                        @endif
                        <p class="store-order-card__item-price">
                            {{ __('customer.account.order_line_qty_price', [
                                'qty' => $item->quantity,
                                'price' => \App\Support\MoneyFormatter::formatDisplay($item->unit_amount, $item->currency),
                            ]) }}
                        </p>
                    </div>
                </li>
            @empty
                <li class="store-order-card__item store-order-card__item--empty">
                    <p class="store-order-card__item-title">{{ __('customer.account.order_no_items') }}</p>
                </li>
            @endforelse
        </ul>

        <div class="store-order-card__footer">
            <p class="store-order-card__status {{ $statusClass }}">
                {{ __('customer.account.order_statuses.'.$statusValue) }}
            </p>
            <a class="store-order-card__action" href="{{ route('customer.orders.show', $order) }}">
                {{ __('customer.account.view_order') }}
            </a>
        </div>
    </div>
</article>
