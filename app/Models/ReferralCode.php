<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReferralCode extends Model
{
    protected $fillable = ['customer_id', 'code', 'uses_count', 'is_active'];

    protected $casts = ['uses_count' => 'integer', 'is_active' => 'boolean'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
