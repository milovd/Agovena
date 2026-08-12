<?php

declare(strict_types=1);

namespace Agovena\Modules\Shipping;

use Agovena\Modules\Shipping\Enums\ShipmentStatus;
use Agovena\Modules\Shipping\Models\Shipment;
use Agovena\Modules\Shipping\Models\ShipmentItem;
use Agovena\Modules\Shipping\Models\ShippingMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ShipmentService
{
    public function createPendingForOrder(Order $order, ?int $shippingMethodId): Shipment
    {
        $order->loadMissing('items');

        $shippableItems = [];
        foreach ($order->items as $item) {
            if ($item->product_id === null) {
                continue;
            }
            $product = Product::query()->with('capabilities')->find($item->product_id);
            if ($product === null || ! $product->hasCapability('shippable')) {
                continue;
            }
            $shippableItems[] = $item;
        }

        if ($shippableItems === []) {
            throw ValidationException::withMessages([
                'shipping' => __('shipping::errors.no_shippable_items'),
            ]);
        }

        $method = null;
        if ($shippingMethodId !== null) {
            $method = ShippingMethod::query()->find($shippingMethodId);
        }

        return DB::transaction(function () use ($order, $shippableItems, $method): Shipment {
            $shipment = Shipment::query()->create([
                'order_id' => $order->id,
                'status' => ShipmentStatus::Pending,
                'shipping_method_id' => $method?->id,
                'shipping_method_label' => $order->shipping_method_label ?? $method?->name,
                'shipping_amount' => (int) ($order->shipping_amount ?? 0),
                'currency' => $order->currency,
            ]);

            foreach ($shippableItems as $item) {
                /** @var OrderItem $item */
                ShipmentItem::query()->create([
                    'shipment_id' => $shipment->id,
                    'order_item_id' => $item->id,
                    'quantity' => $item->quantity,
                ]);
            }

            return $shipment->load('items');
        });
    }

    public function markProcessing(Shipment $shipment): Shipment
    {
        return $this->transition($shipment, ShipmentStatus::Processing);
    }

    public function markShipped(
        Shipment $shipment,
        ?string $carrierName = null,
        ?string $trackingNumber = null,
        ?string $trackingUrl = null,
    ): Shipment {
        if ($carrierName !== null) {
            $shipment->carrier_name = $carrierName;
        }
        if ($trackingNumber !== null) {
            $shipment->tracking_number = $trackingNumber;
        }
        if ($trackingUrl !== null) {
            $shipment->tracking_url = $trackingUrl !== '' ? $trackingUrl : null;
        }
        $shipment->shipped_at ??= now();

        return $this->transition($shipment, ShipmentStatus::Shipped);
    }

    public function markDelivered(Shipment $shipment): Shipment
    {
        $shipment->delivered_at ??= now();

        return $this->transition($shipment, ShipmentStatus::Delivered);
    }

    public function cancel(Shipment $shipment): Shipment
    {
        return $this->transition($shipment, ShipmentStatus::Cancelled);
    }

    public function updateTracking(
        Shipment $shipment,
        ?string $carrierName,
        ?string $trackingNumber,
        ?string $trackingUrl,
    ): Shipment {
        $shipment->carrier_name = $carrierName;
        $shipment->tracking_number = $trackingNumber;
        $shipment->tracking_url = filled($trackingUrl) ? $trackingUrl : null;
        $shipment->save();

        return $shipment->fresh() ?? $shipment;
    }

    private function transition(Shipment $shipment, ShipmentStatus $next): Shipment
    {
        if (! $shipment->status->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => __('shipping::errors.invalid_transition', [
                    'from' => $shipment->status->value,
                    'to' => $next->value,
                ]),
            ]);
        }

        $shipment->status = $next;
        $shipment->save();

        return $shipment->fresh(['items']) ?? $shipment;
    }
}
