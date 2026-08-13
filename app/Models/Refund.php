<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money movement reversing a Payment. Distinct from CreditNote (accounting document).
 *
 * @property int $id
 * @property int $payment_id
 * @property int $order_id
 * @property int|null $invoice_id
 * @property int|null $credit_note_id
 * @property int|null $created_by
 * @property int $amount
 * @property string $currency
 * @property RefundStatus $status
 * @property string|null $reason
 * @property string|null $provider_reference
 * @property Carbon|null $completed_at
 */
#[Fillable([
    'payment_id',
    'order_id',
    'invoice_id',
    'credit_note_id',
    'created_by',
    'amount',
    'currency',
    'status',
    'reason',
    'provider_reference',
    'completed_at',
])]
class Refund extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => RefundStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<CreditNote, $this> */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
