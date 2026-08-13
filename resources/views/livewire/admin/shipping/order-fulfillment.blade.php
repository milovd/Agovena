<section class="ag-section" aria-labelledby="shipping-fulfillment-heading" wire:key="shipping-fulfillment-{{ $order->id }}">
    <header class="ag-section__header">
        <h3 id="shipping-fulfillment-heading" class="ag-section__title">{{ __('shipping::admin.fulfillment_title') }}</h3>
        <p class="ag-section__lede">{{ __('shipping::admin.fulfillment_lede') }}</p>
    </header>
    <div class="ag-section__body">
        @if (session('status'))
            <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
        @endif

        @if ($shipments->isEmpty())
            <p class="ag-muted">{{ __('shipping::admin.no_shipments') }}</p>
        @else
            @foreach ($shipments as $shipment)
                <div class="ag-stack" wire:key="shipment-{{ $shipment->id }}" style="margin-bottom: 1rem;">
                    <p>
                        <strong>#{{ $shipment->id }}</strong>
                        — {{ __('shipping::status.'.$shipment->status->value) }}
                        @if ($shipment->shipping_method_label)
                            · {{ $shipment->shipping_method_label }}
                        @endif
                    </p>
                    <ul>
                        @foreach ($shipment->items as $row)
                            <li>{{ $row->quantity }} × {{ $row->orderItem?->label }}</li>
                        @endforeach
                    </ul>

                    @can('shipping.manage')
                        <div class="ag-grid ag-grid--2">
                            <div class="ag-field">
                                <label class="ag-field__label">{{ __('shipping::admin.carrier') }}</label>
                                <input class="ag-input" type="text" wire:model="carrier_name">
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label">{{ __('shipping::admin.tracking_number') }}</label>
                                <input class="ag-input" type="text" wire:model="tracking_number">
                            </div>
                            <div class="ag-field ag-grid__span-2">
                                <label class="ag-field__label">{{ __('shipping::admin.tracking_url') }}</label>
                                <input class="ag-input" type="url" wire:model="tracking_url">
                            </div>
                        </div>
                        <div class="ag-toolbar">
                            <button type="button" class="ag-btn ag-btn--ghost" wire:click="saveTracking({{ $shipment->id }})">{{ __('shipping::admin.save') }}</button>
                            @if ($shipment->status->value === 'pending' && $carriers !== [])
                                <div class="ag-field">
                                    <label class="ag-field__label">{{ __('shipping::admin.carrier_provider') }}</label>
                                    <select class="ag-input" wire:model="carrier_id">
                                        @foreach ($carriers as $carrier)
                                            <option value="{{ $carrier['id'] }}">{{ $carrier['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" class="ag-btn ag-btn--primary" wire:click="createCarrierShipment({{ $shipment->id }})">
                                    {{ __('shipping::admin.create_carrier_shipment') }}
                                </button>
                            @endif
                            @if ($shipment->external_ref)
                                <button type="button" class="ag-btn ag-btn--secondary" wire:click="syncCarrierTracking({{ $shipment->id }})">
                                    {{ __('shipping::admin.sync_tracking') }}
                                </button>
                            @endif
                            @if ($shipment->label_path)
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="downloadLabel({{ $shipment->id }})">
                                    {{ __('shipping::admin.download_label') }}
                                </button>
                            @endif
                            @if ($shipment->status->value === 'pending')
                                <button type="button" class="ag-btn ag-btn--secondary" wire:click="markProcessing({{ $shipment->id }})">{{ __('shipping::admin.mark_processing') }}</button>
                            @endif
                            @if (in_array($shipment->status->value, ['pending', 'processing'], true))
                                <button type="button" class="ag-btn ag-btn--primary" wire:click="markShipped({{ $shipment->id }})">{{ __('shipping::admin.mark_shipped') }}</button>
                            @endif
                            @if ($shipment->status->value === 'shipped')
                                <button type="button" class="ag-btn ag-btn--secondary" wire:click="markDelivered({{ $shipment->id }})">{{ __('shipping::admin.mark_delivered') }}</button>
                            @endif
                            @if (! in_array($shipment->status->value, ['delivered', 'cancelled'], true))
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="cancelShipment({{ $shipment->id }})">{{ __('shipping::admin.cancel_shipment') }}</button>
                            @endif
                        </div>
                    @endcan

                    @if ($shipment->tracking_number)
                        <p class="ag-muted">
                            {{ $shipment->carrier_name }}
                            · {{ $shipment->tracking_number }}
                            @if ($shipment->tracking_url)
                                · <a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener">{{ __('shipping::admin.tracking_url') }}</a>
                            @endif
                        </p>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
</section>
