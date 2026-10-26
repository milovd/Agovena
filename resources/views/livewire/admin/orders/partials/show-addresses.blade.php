{{-- Order detail: billing and shipping addresses. Part of livewire.admin.orders.show. --}}
<section class="ag-card ag-order-addresses" aria-labelledby="order-addresses-heading">
    <header class="ag-card__header">
        <x-ag.icon-tile name="map-pin" tone="cyan" />
        <h2 id="order-addresses-heading" class="ag-card__title">{{ __('admin.orders.show.addresses') }}</h2>
        <p class="ag-card__description">{{ __('admin.orders.show.addresses_lede') }}</p>
        @if ($canUpdate && ! $editingAddresses)
            <button type="button" class="ag-icon-btn ag-card__action" wire:click="startEditAddresses" title="{{ __('admin.orders.inline.edit_addresses') }}" aria-label="{{ __('admin.orders.inline.edit_addresses') }}">
                <x-ag.icon name="pencil" :size="16" />
            </button>
        @endif
    </header>
    <div class="ag-card__content">
        @if ($editingAddresses && $canUpdate)
            <form class="ag-form admin-order__address-form" wire:submit="saveAddresses" novalidate>
                <div class="ag-order-addresses__grid">
                    @foreach ([
                        'billing' => __('admin.orders.show.billing_address'),
                        'shipping' => __('admin.orders.show.shipping_address'),
                    ] as $type => $label)
                        <fieldset class="admin-order__address-fields">
                            <legend class="ag-order-addresses__label">{{ $label }}</legend>
                            @if ($type === 'shipping')
                                <label class="ag-check">
                                    <input type="checkbox" wire:model.live="details.shippingSameAsBilling">
                                    <span>{{ __('admin.orders.edit.shipping_same_as_billing') }}</span>
                                </label>
                            @endif
                            @unless ($type === 'shipping' && $details->shippingSameAsBilling)
                                @foreach ([
                                    'name' => __('common.name'),
                                    'company' => __('admin.orders.edit.company'),
                                    'line1' => __('admin.orders.edit.address'),
                                    'line2' => __('admin.orders.edit.address_line2'),
                                    'postal_code' => __('admin.orders.edit.postal_code'),
                                    'city' => __('admin.orders.edit.city'),
                                    'region' => __('admin.orders.edit.region'),
                                    'country' => __('admin.orders.edit.country_code'),
                                    'phone' => __('admin.orders.edit.phone'),
                                ] as $field => $fieldLabel)
                                    <div class="ag-field admin-order__address-field admin-order__address-field--{{ $field }}">
                                        <label class="ag-field__label" for="order-{{ $type }}-{{ $field }}">{{ $fieldLabel }}</label>
                                        <input id="order-{{ $type }}-{{ $field }}" class="ag-input" type="{{ $field === 'phone' ? 'tel' : 'text' }}" wire:model="details.{{ $type }}.{{ $field }}" @if ($field === 'country') maxlength="2" @endif>
                                        @error('details.'.$type.'.'.$field) <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                                    </div>
                                @endforeach
                            @endunless
                        </fieldset>
                    @endforeach
                </div>
                <div class="ag-form__actions">
                    <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled" wire:target="saveAddresses">{{ __('common.save') }}</button>
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelEditAddresses">{{ __('common.cancel') }}</button>
                </div>
            </form>
        @else
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
        @endif
    </div>
</section>
