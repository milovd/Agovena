<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

final readonly class CheckoutProgressItem
{
    public function __construct(
        public CheckoutStep $step,
        public string $state,
        public int $position,
        public int $total,
    ) {}

    public function isCurrent(): bool
    {
        return $this->state === 'current';
    }

    public function isCompleted(): bool
    {
        return $this->state === 'completed';
    }
}
