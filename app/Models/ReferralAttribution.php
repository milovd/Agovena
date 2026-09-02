<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReferralAttribution extends Model
{
    protected $fillable = [
        'order_id',
        'referral_code_id',
        'referrer_customer_id',
        'referred_customer_id',
        'code_snapshot',
        'status',
        'reward_amount',
        'reward_percentage',
        'reward_currency',
        'fraud_review_required',
        'reviewed_at',
        'credited_at',
    ];

    protected $casts = [
        'reward_amount' => 'integer',
        'reward_percentage' => 'integer',
        'fraud_review_required' => 'boolean',
        'reviewed_at' => 'datetime',
        'credited_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function code(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class, 'referral_code_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referrer_customer_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_customer_id');
    }
}
