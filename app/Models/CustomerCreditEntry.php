<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

#[Fillable([
    'customer_id', 'entry_type', 'amount', 'balance_after', 'reason',
    'reference_type', 'reference_id', 'staff_user_id', 'meta',
])]
class CustomerCreditEntry extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Credit ledger entries are immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Credit ledger entries are immutable.');
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
