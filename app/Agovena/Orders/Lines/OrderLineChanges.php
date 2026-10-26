<?php

declare(strict_types=1);

namespace App\Agovena\Orders\Lines;

/**
 * The structural difference between an order's lines before and after a staff edit. Price-only
 * changes are not structural: they never touch stock, shipments or module state.
 */
final readonly class OrderLineChanges
{
    /**
     * @param  list<OrderLineDraft>  $added  Lines to create (ids are filled in after the write)
     * @param  array<int, array{product_id: int|null, quantity: int, to_product_id: int, to_quantity: int}>  $updated  Keyed by order item id
     * @param  array<int, array{product_id: int|null, quantity: int}>  $removed  Keyed by order item id
     * @param  list<int>  $addedItemIds
     */
    public function __construct(
        public array $added,
        public array $updated,
        public array $removed,
        public array $addedItemIds = [],
    ) {}

    public function isStructural(): bool
    {
        return $this->added !== [] || $this->updated !== [] || $this->removed !== [];
    }

    /** @param list<int> $addedItemIds */
    public function withAddedItemIds(array $addedItemIds): self
    {
        return new self($this->added, $this->updated, $this->removed, $addedItemIds);
    }

    /** @return list<int> Every product a structural change touches, before and after. */
    public function productIds(): array
    {
        $ids = array_map(static fn (OrderLineDraft $line): int => $line->productId, $this->added);
        foreach ($this->updated as $change) {
            $ids[] = $change['product_id'];
            $ids[] = $change['to_product_id'];
        }
        foreach ($this->removed as $change) {
            $ids[] = $change['product_id'];
        }

        return array_values(array_unique(array_filter($ids, static fn (?int $id): bool => $id !== null)));
    }
}
