{{-- Order detail: billing and shipping addresses. Part of livewire.admin.orders.show. --}}
<section class="ag-section ag-order-addresses" aria-labelledby="order-addresses-heading">
    <header class="ag-section__header">
        <h3 id="order-addresses-heading" class="ag-section__title">{{ __('admin.orders.show.addresses') }}</h3>
        <p class="ag-section__lede">{{ __('admin.orders.show.addresses_lede') }}</p>
    </header>
    <div class="ag-section__body">
        <div class="ag-order-addresses__grid">
            @foreach ([
                'billing' => __('admin.orders.show.billing_address'),
                'shipping' => __('admin.orders.show.shipping_address'),
            ] as $type => $label)
                @php
                    $address = collect([
                        'name' => $order->{$type.'_name'},
                        'company' => $order->{$type.'_company'},
                        'line1' => $order->{$type.'_line1'},
                        'line2' => $order->{$type.'_line2'},
                        'postal_code' => $order->{$type.'_postal_code'},
                        'city' => $order->{$type.'_city'},
                        'region' => $order->{$type.'_region'},
                        'country' => $order->{$type.'_country'},
                        'phone' => $order->{$type.'_phone'},
                    ])->filter(static fn ($value): bool => filled($value));
                @endphp
                <div class="ag-order-addresses__card">
                    <h4 class="ag-order-addresses__label">{{ $label }}</h4>
                    @if ($address->isNotEmpty())
                        <address class="ag-order-addresses__value">
                            @if ($address->has('name')) <span>{{ $address->get('name') }}</span> @endif
                            @if ($address->has('company')) <span>{{ $address->get('company') }}</span> @endif
                            @if ($address->has('line1')) <span>{{ $address->get('line1') }}</span> @endif
                            @if ($address->has('line2')) <span>{{ $address->get('line2') }}</span> @endif
                            @if ($address->has('postal_code') || $address->has('city'))
                                <span>{{ trim(($address->get('postal_code') ?? '').' '.($address->get('city') ?? '')) }}</span>
                            @endif
                            @if ($address->has('region')) <span>{{ $address->get('region') }}</span> @endif
                            @if ($address->has('country')) <span>{{ $address->get('country') }}</span> @endif
                            @if ($address->has('phone')) <a href="tel:{{ $address->get('phone') }}">{{ $address->get('phone') }}</a> @endif
                        </address>
                    @else
                        <span class="ag-muted">{{ __('common.em_dash') }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
