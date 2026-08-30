<?php

declare(strict_types=1);

namespace App\Events;

use App\Agovena\Cart\PricedCartLine;

final class OrderPreflight
{
    /** @var list<PricedCartLine> */
    public readonly array $lines;

    /** @var array<int, array<string, mixed>> */
    public array $checks = [];

    /**
     * @param  list<PricedCartLine>  $lines
     */
    public function __construct(array $lines)
    {
        $this->lines = $lines;
    }
}
