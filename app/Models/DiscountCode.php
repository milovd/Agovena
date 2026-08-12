<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $type
 * @property int $value
 * @property string|null $currency
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $max_uses
 * @property int|null $max_uses_per_customer
 * @property int $min_subtotal_amount
 * @property bool $is_active
 */
#[Fillable([
    'code',
    'type',
    'value',
    'currency',
    'starts_at',
    'ends_at',
    'max_uses',
    'max_uses_per_customer',
    'min_subtotal_amount',
    'is_active',
])]
class DiscountCode extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_uses' => 'integer',
            'max_uses_per_customer' => 'integer',
            'min_subtotal_amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<DiscountRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(DiscountRedemption::class);
    }
}
