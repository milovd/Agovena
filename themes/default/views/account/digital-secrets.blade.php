<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <h1 class="store-account-panel__title">{{ __('digital-delivery::customer.title') }}</h1>
            <p class="store-account-panel__lede">{{ __('digital-delivery::customer.lede') }}</p>
        </header>

        @if ($deliveries->isEmpty())
            <p class="store-muted">{{ __('digital-delivery::customer.empty') }}</p>
        @else
            <ul class="store-order-items" role="list">
                @foreach ($deliveries as $delivery)
                    <li class="store-order-items__row" wire:key="secret-{{ $delivery->id }}">
                        <div>
                            <strong>{{ $delivery->product?->name ?? __('digital-delivery::customer.item') }}</strong>
                            <p>
                                {{ __('digital-delivery::customer.order') }}:
                                {{ $delivery->order?->number }}
                            </p>
                            @if (($values[$delivery->id] ?? null) !== null)
                                <p><code class="store-code">{{ $values[$delivery->id] }}</code></p>
                            @elseif ($delivery->isPendingManual())
                                <p class="store-muted">{{ __('digital-delivery::customer.pending') }}</p>
                            @elseif ($delivery->isRevoked())
                                <p class="store-muted">{{ __('digital-delivery::customer.revoked') }}</p>
                            @endif
                        </div>
                        <span class="store-muted">{{ $delivery->granted_at?->toFormattedDateString() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
