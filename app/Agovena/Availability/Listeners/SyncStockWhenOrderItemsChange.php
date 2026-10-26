<?php

declare(strict_types=1);

namespace App\Agovena\Availability\Listeners;

use App\Agovena\Availability\InventoryService;
use App\Events\OrderItemsChanged;
use App\Events\OrderItemsChanging;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

/**
 * Keeps stock reservations in step when staff change the lines of an unpaid order: the stock of
 * removed and changed lines goes back first, then added and changed lines reserve again. Too
 * little stock refuses the whole edit.
 */
final class SyncStockWhenOrderItemsChange
{
    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function handle(OrderItemsChanging|OrderItemsChanged $event): void
    {
        $event instanceof OrderItemsChanging ? $this->changing($event) : $this->changed($event);
    }

    private function changing(OrderItemsChanging $event): void
    {
        foreach (array_keys($event->changes->removed + $event->changes->updated) as $itemId) {
            $this->inventory->releaseForItem((int) $itemId);
        }
    }

    private function changed(OrderItemsChanged $event): void
    {
        $itemIds = array_merge($event->changes->addedItemIds, array_keys($event->changes->updated));
        if ($itemIds === []) {
            return;
        }

        foreach (OrderItem::query()->whereIn('id', $itemIds)->get() as $item) {
            $product = $item->product_id === null ? null : Product::query()->with('capabilities')->find($item->product_id);
            if ($product === null || ! $product->hasCapability('inventory')) {
                continue;
            }

            try {
                $this->inventory->reserve($product, (int) $event->order->id, (int) $item->id, (int) $item->quantity);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages([
                    'lines' => $product->name.': '.($exception->errors()['product'][0] ?? $exception->getMessage()),
                ]);
            }
        }
    }
}
