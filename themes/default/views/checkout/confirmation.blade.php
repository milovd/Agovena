<div class="store-confirmation">
    <h1 class="store-title">{{ __('storefront.confirmation.title') }}</h1>
    <p class="store-lede">{!! __('storefront.confirmation.lede', ['number' => '<strong>'.e($order->number).'</strong>']) !!}</p>

    <section aria-labelledby="confirm-items">
        <h2 id="confirm-items" class="store-subtitle">{{ __('storefront.confirmation.items') }}</h2>
        <ul>
            @foreach ($order->items as $item)
                <li>{{ $item->quantity }} × {{ $item->label }} — {{ \App\Support\MoneyFormatter::format($item->line_total_amount, $item->currency) }}</li>
            @endforeach
        </ul>
        <p>{{ __('storefront.confirmation.total', ['amount' => \App\Support\MoneyFormatter::format($order->total_amount, $order->currency)]) }}</p>
    </section>

    <p class="store-note">{{ __('storefront.confirmation.note') }}</p>
    <a class="store-btn" href="{{ route('storefront.home') }}">{{ __('storefront.confirmation.back') }}</a>
</div>
