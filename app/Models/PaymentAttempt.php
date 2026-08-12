<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentAttemptStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Provider-facing payment attempt. Separate from Order and Payment.
 *
 * @property int $id
 * @property int $payment_id
 * @property int $order_id
 * @property string $gateway_id
 * @property PaymentAttemptStatus $status
 * @property string|null $external_id
 * @property int $amount
 * @property string $currency
 * @property string|null $idempotency_key
 * @property string|null $redirect_url
 * @property array<string, mixed>|null $request_meta
 * @property array<string, mixed>|null $response_meta
 * @property Carbon|null $initiated_at
 * @property Carbon|null $completed_at
 */
#[Fillable([
    'payment_id',
    'order_id',
    'gateway_id',
    'status',
    'external_id',
    'amount',
    'currency',
    'idempotency_key',
    'redirect_url',
    'request_meta',
    'response_meta',
    'initiated_at',
    'completed_at',
])]
class PaymentAttempt extends Model
{
    protected $table = 'payment_attempts';

    protected function casts(): array
    {
        return [
            'status' => PaymentAttemptStatus::class,
            'amount' => 'integer',
            'request_meta' => 'array',
            'response_meta' => 'array',
            'initiated_at' => 'datetime',
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
}
