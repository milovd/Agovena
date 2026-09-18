<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BackInStockSubscription extends Model
{
    protected $fillable = [
        'product_id',
        'email',
        'token_hash',
        'delivery_attempts',
        'delivery_claimed_at',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'delivery_claimed_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
