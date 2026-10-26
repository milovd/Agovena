<?php

declare(strict_types=1);

namespace App\Agovena\Physical\Listeners;

use App\Agovena\Physical\Enums\ShipmentStatus;
use App\Agovena\Physical\Models\Shipment;
use App\Agovena\Physical\Models\ShipmentItem;
use App\Events\OrderItemsChanged;
use App\Events\OrderItemsChanging;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

/**
 * Keeps the pending shipment of an unpaid order in step with its lines. Shippable lines can only
 * change while the shipment is still pending, and can only be added when the order already has
 * a shipment (shipping was chosen and charged at checkout).
 */
final class SyncShipmentWhenOrderItemsChange
{
    public function handle(OrderItemsChanging|OrderItemsChanged $event): void
    {
        $event instanceof OrderItemsChanging ? $this->changing($event) : $this->changed($event);
    }

    private function changing(OrderItemsChanging $event): void
    {
        $changes = $event->changes;
        $touchesShippable = false;
        $needsShipment = false;

        foreach ($changes->added as $draft) {
            if ($this->isShippable($draft->productId)) {
                $touchesShippable = $needsShipment = true;
            }
        }
        foreach ($changes->updated as $change) {
            if ($this->isShippable($change['to_product_id'])) {
                $touchesShippable = $needsShipment = true;
            }
            if ($this->isShippable($change['product_id'])) {
                $touchesShippable = true;
            }
        }
        foreach ($changes->removed as $change) {
            if ($this->isShippable($change['product_id'])) {
                $touchesShippable = true;
            }
        }

        if (! $touchesShippable) {
            return;
        }

        $shipments = Shipment::query()->where('order_id', $event->order->id)->lockForUpdate()->get();
        if ($shipments->contains(static fn (Shipment $shipment): bool => ! in_array($shipment->status, [ShipmentStatus::Pending, ShipmentStatus::Cancelled], true))) {
            throw ValidationException::withMessages(['lines' => __('admin.orders.lines.errors.shipment_started')]);
        }
        if ($needsShipment && ! $shipments->contains(static fn (Shipment $shipment): bool => $shipment->status === ShipmentStatus::Pending)) {
            throw ValidationException::withMessages(['lines' => __('admin.orders.lines.errors.no_shipment')]);
        }
    }

    private function changed(OrderItemsChanged $event): void
    {
        $shipment = Shipment::query()
            ->where('order_id', $event->order->id)
            ->where('status', ShipmentStatus::Pending)
            ->latest('id')
            ->first();
        if ($shipment === null) {
            return;
        }

        $itemIds = array_merge($event->changes->addedItemIds, array_keys($event->changes->updated));
        foreach (OrderItem::query()->whereIn('id', $itemIds)->get() as $item) {
            if ($this->isShippable($item->product_id === null ? null : (int) $item->product_id)) {
                ShipmentItem::query()->updateOrCreate(
                    ['shipment_id' => $shipment->id, 'order_item_id' => $item->id],
                    ['quantity' => $item->quantity],
                );
            } else {
                ShipmentItem::query()->where('order_item_id', $item->id)->delete();
            }
        }

        if (! ShipmentItem::query()->where('shipment_id', $shipment->id)->exists()) {
            $shipment->delete();
        }
    }

    private function isShippable(?int $productId): bool
    {
        if ($productId === null) {
            return false;
        }

        return Product::query()->with('capabilities')->find($productId)?->hasCapability('shippable') === true;
    }
}
