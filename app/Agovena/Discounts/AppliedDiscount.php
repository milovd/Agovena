<?php

declare(strict_types=1);

namespace App\Agovena\Discounts;

use App\Agovena\Money\Money;
use App\Models\DiscountCode;

final readonly class AppliedDiscount
{
    public function __construct(
        public DiscountCode $code,
        public Money $amount,
    ) {}
}
