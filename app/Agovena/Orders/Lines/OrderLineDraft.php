<?php

declare(strict_types=1);

namespace App\Agovena\Orders\Lines;

/**
 * One order line as staff want it to be. A null id adds a line; an existing id keeps or
 * changes that line; existing lines missing from the list are removed.
 */
final readonly class OrderLineDraft
{
    public function __construct(
        public ?int $id,
        public int $productId,
        public int $quantity,
        public int $unitAmount,
    ) {}
}
