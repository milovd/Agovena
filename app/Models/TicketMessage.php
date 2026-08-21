<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ticket_id', 'author_type', 'author_id', 'body', 'is_internal'])]
class TicketMessage extends Model
{
    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return HasMany<TicketMessageAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketMessageAttachment::class);
    }
}
