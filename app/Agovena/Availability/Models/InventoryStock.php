<?php

declare(strict_types=1);

namespace App\Agovena\Availability\Models;

use App\Agovena\Availability\AvailabilityMode;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property int $quantity
 * @property string $availability_mode
 * @property string|null $provider_key
 * @property bool $track_stock
 * @property bool $allow_oversell
 */
final class InventoryStock extends Model
{
    protected $table = 'inventory_stocks';

    protected $fillable = [
        'product_id',
        'availability_mode',
        'provider_key',
        'quantity',
        'track_stock',
        'allow_oversell',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'availability_mode' => AvailabilityMode::class,
            'track_stock' => 'boolean',
            'allow_oversell' => 'boolean',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isAvailable(int $quantity): bool
    {
        if ($this->availability_mode === AvailabilityMode::Unlimited || ! $this->track_stock || $this->allow_oversell) {
            return true;
        }

        return $this->quantity >= $quantity;
    }
}
