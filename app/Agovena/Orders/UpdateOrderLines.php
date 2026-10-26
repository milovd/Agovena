<?php

declare(strict_types=1);

namespace App\Agovena\Orders;

use App\Agovena\Audit\AuditLogger;
use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Catalog\Options\ProductOptionValidator;
use App\Agovena\Orders\Lines\OrderLineChanges;
use App\Agovena\Orders\Lines\OrderLineDraft;
use App\Events\OrderItemsChanged;
use App\Events\OrderItemsChanging;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the lines of an unpaid order with the drafts staff entered, then recalculates the
 * totals. The caller holds the order lock inside a transaction and reissues the invoice.
 *
 * Product and quantity changes are only allowed for products whose capabilities opted in with
 * orderLineEditable; their owners keep stock, shipments and other line state in sync through
 * OrderItemsChanging and OrderItemsChanged. The price of any line may change.
 */
final class UpdateOrderLines
{
    public function __construct(
        private readonly UnpaidOrderEditGuard $guard,
        private readonly ProductCapabilityRegistry $capabilities,
        private readonly ProductOptionValidator $options,
        private readonly RecalculateOrderTotals $totals,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<OrderLineDraft>  $drafts
     * @return bool Whether anything changed
     */
    public function applyLocked(Order $locked, array $drafts, User $staff): bool
    {
        $this->guard->assertEditable($locked, forLineChange: true);

        if ($drafts === []) {
            $this->fail(__('admin.orders.lines.errors.empty'));
        }

        /** @var Collection<int, OrderItem> $items */
        $items = OrderItem::query()->where('order_id', $locked->id)->lockForUpdate()->get()->keyBy('id');
        $products = Product::query()
            ->with(['capabilities', 'purchaseOptions'])
            ->whereIn('id', array_merge(
                array_map(static fn (OrderLineDraft $draft): int => $draft->productId, $drafts),
                $items->pluck('product_id')->filter()->all(),
            ))
            ->get()
            ->keyBy('id');

        $added = [];
        $updated = [];
        $priceOnly = [];
        $seen = [];

        foreach ($drafts as $draft) {
            if ($draft->quantity < 1 || $draft->quantity > 10000) {
                $this->fail(__('admin.orders.lines.errors.quantity'));
            }
            if ($draft->unitAmount < 0) {
                $this->fail(__('admin.orders.lines.errors.price'));
            }
            if (! $products->has($draft->productId)) {
                $this->fail(__('admin.orders.lines.errors.product'));
            }

            if ($draft->id === null) {
                $added[] = $draft;

                continue;
            }

            $item = $items->get($draft->id);
            if ($item === null || isset($seen[$draft->id])) {
                $this->fail(__('admin.orders.lines.errors.product'));
            }
            $seen[$draft->id] = true;

            if ((int) $item->product_id !== $draft->productId || (int) $item->quantity !== $draft->quantity) {
                $updated[(int) $item->id] = [
                    'product_id' => $item->product_id === null ? null : (int) $item->product_id,
                    'quantity' => (int) $item->quantity,
                    'to_product_id' => $draft->productId,
                    'to_quantity' => $draft->quantity,
                ];
            } elseif ((int) $item->unit_amount !== $draft->unitAmount) {
                $priceOnly[(int) $item->id] = $draft->unitAmount;
            }
        }

        $removed = [];
        foreach ($items as $item) {
            if (! isset($seen[$item->id])) {
                $removed[(int) $item->id] = [
                    'product_id' => $item->product_id === null ? null : (int) $item->product_id,
                    'quantity' => (int) $item->quantity,
                ];
            }
        }

        $changes = new OrderLineChanges($added, $updated, $removed);
        if (! $changes->isStructural() && $priceOnly === []) {
            return false;
        }

        $this->assertStructuralChangesAllowed($changes, $items, $products);

        $before = $this->lineSnapshot($items->values()->all());

        if ($changes->isStructural()) {
            OrderItemsChanging::dispatch($locked, $changes);
        }

        $draftsById = [];
        foreach ($drafts as $draft) {
            if ($draft->id !== null) {
                $draftsById[$draft->id] = $draft;
            }
        }

        foreach ($items as $item) {
            if (isset($removed[$item->id])) {
                $item->delete();

                continue;
            }

            $draft = $draftsById[$item->id];
            $product = $products->get($draft->productId);
            $item->forceFill([
                'product_id' => $draft->productId,
                'label' => (int) $item->product_id === $draft->productId ? $item->label : (string) $product?->name,
                'quantity' => $draft->quantity,
                'unit_amount' => $draft->unitAmount,
                'line_total_amount' => $draft->unitAmount * $draft->quantity,
            ])->save();
        }

        $addedIds = [];
        foreach ($added as $draft) {
            $addedIds[] = (int) OrderItem::query()->create([
                'order_id' => $locked->id,
                'product_id' => $draft->productId,
                'label' => (string) $products->get($draft->productId)?->name,
                'quantity' => $draft->quantity,
                'unit_amount' => $draft->unitAmount,
                'line_total_amount' => $draft->unitAmount * $draft->quantity,
                'currency' => $locked->currency,
                'options_snapshot' => [],
            ])->id;
        }

        if ($changes->isStructural()) {
            OrderItemsChanged::dispatch($locked->fresh(['items']) ?? $locked, $changes->withAddedItemIds($addedIds));
        }

        $this->totals->handle($locked);

        $this->audit->log('order.lines_updated', $locked, [
            'order_number' => $locked->number,
            'staff_id' => $staff->id,
            'before' => $before,
            'after' => $this->lineSnapshot($locked->items->all()),
            'total_amount' => $locked->total_amount,
        ]);

        return true;
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     * @param  Collection<int, Product>  $products
     */
    private function assertStructuralChangesAllowed(OrderLineChanges $changes, $items, $products): void
    {
        foreach ($changes->productIds() as $productId) {
            $product = $products->get($productId);
            if ($product === null) {
                continue;
            }

            foreach ($product->capabilities as $capability) {
                $definition = $this->capabilities->get((string) $capability->capability);
                if ($definition === null || ! $definition->orderLineEditable) {
                    $this->fail(__('admin.orders.lines.errors.locked_product', ['product' => $product->name]));
                }
            }
        }

        $newProducts = array_map(static fn (OrderLineDraft $draft): int => $draft->productId, $changes->added);
        foreach ($changes->updated as $itemId => $change) {
            if ($change['product_id'] !== $change['to_product_id']) {
                // Checkout configuration belongs to the product it was entered for.
                if (($items->get($itemId)->options_snapshot ?? []) !== []) {
                    $this->fail(__('admin.orders.lines.errors.configured'));
                }
                $newProducts[] = $change['to_product_id'];
            }
        }

        foreach (array_unique($newProducts) as $productId) {
            $product = $products->get($productId);
            if ($product === null) {
                continue;
            }
            try {
                $this->options->validate($product, []);
            } catch (ValidationException) {
                $this->fail(__('admin.orders.lines.errors.needs_configuration', ['product' => $product->name]));
            }
        }
    }

    /**
     * @param  list<OrderItem>  $items
     * @return list<array{product_id: int|null, quantity: int, unit_amount: int}>
     */
    private function lineSnapshot(array $items): array
    {
        return array_map(static fn (OrderItem $item): array => [
            'product_id' => $item->product_id === null ? null : (int) $item->product_id,
            'quantity' => (int) $item->quantity,
            'unit_amount' => (int) $item->unit_amount,
        ], $items);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['lines' => $message]);
    }
}
