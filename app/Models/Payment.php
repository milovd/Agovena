<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Payment is separate from Order lifecycle.
 * Order ≠ Payment ≠ PaymentAttempt. Provider attempts live on PaymentAttempt.
 *
 * @property int $id
 * @property int $order_id
 * @property int $amount
 * @property string $currency
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property Carbon|null $paid_at
 * @property string|null $reference
 */
#[Fillable([
    'order_id',
    'amount',
    'currency',
    'method',
    'status',
    'paid_at',
    'reference',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function refundedAmount(): int
    {
        return (int) $this->refunds()
            ->where('status', RefundStatus::Completed)
            ->sum('amount');
    }

    public function remainingRefundable(): int
    {
        if (! in_array($this->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
            return 0;
        }

        return max(0, (int) $this->amount - $this->refundedAmount());
    }
}
