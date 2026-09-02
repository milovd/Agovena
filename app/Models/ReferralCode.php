<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReferralCode extends Model
{
    protected $fillable = [
        'customer_id', 'code', 'uses_count', 'is_active', 'max_uses', 'expires_at',
        'reward_amount', 'reward_percentage', 'reward_currency', 'fraud_review_required',
    ];

    protected $casts = [
        'uses_count' => 'integer',
        'is_active' => 'boolean',
        'max_uses' => 'integer',
        'expires_at' => 'datetime',
        'reward_amount' => 'integer',
        'reward_percentage' => 'integer',
        'fraud_review_required' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
