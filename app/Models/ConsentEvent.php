<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'consent_version', 'choice', 'source', 'ip_hash', 'user_agent_hash'])]
class ConsentEvent extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ConsentEventCategory, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(ConsentEventCategory::class);
    }
}
