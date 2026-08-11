<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';

    public function isPurchasable(): bool
    {
        return $this === self::Active;
    }
}
