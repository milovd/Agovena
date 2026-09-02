<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $expires_at
 * @property Carbon|null $converted_at
 */
final class ReferralVisit extends Model
{
    protected $fillable = [
        'referral_code_id',
        'visitor_hash',
        'clicks_count',
        'first_clicked_at',
        'last_clicked_at',
        'expires_at',
        'referred_customer_id',
        'converted_at',
    ];

    protected $casts = [
        'clicks_count' => 'integer',
        'first_clicked_at' => 'datetime',
        'last_clicked_at' => 'datetime',
        'expires_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    /** @return BelongsTo<ReferralCode, $this> */
    public function code(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class, 'referral_code_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function referred(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_customer_id');
    }
}
