<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $number
 * @property OrderStatus $status
 * @property string $customer_name
 * @property string $customer_email
 * @property int|null $customer_id
 * @property int $subtotal_amount
 * @property int $total_amount
 * @property string $currency
 * @property string|null $idempotency_key
 * @property-read Collection<int, OrderItem> $items
 * @property-read Payment|null $payment
 */
#[Fillable([
    'number',
    'status',
    'customer_name',
    'customer_email',
    'customer_id',
    'subtotal_amount',
    'total_amount',
    'currency',
    'idempotency_key',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal_amount' => 'integer',
            'total_amount' => 'integer',
        ];
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasOne<Payment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
