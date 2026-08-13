<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceItemKind: string
{
    case Product = 'product';
    case Shipping = 'shipping';
    case Discount = 'discount';
    case Tax = 'tax';
    case Credit = 'credit';

    public function isAdjustment(): bool
    {
        return in_array($this, [self::Discount, self::Credit], true);
    }
}
