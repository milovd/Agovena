<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['consent_event_id', 'category', 'decision'])]
class ConsentEventCategory extends Model
{
    protected function casts(): array
    {
        return ['decision' => 'boolean'];
    }

    /** @return BelongsTo<ConsentEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(ConsentEvent::class, 'consent_event_id');
    }
}
