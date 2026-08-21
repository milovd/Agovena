<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Private ticket attachment. Never stored on the public disk.
 *
 * @property int $id
 * @property int $ticket_message_id
 * @property string $disk
 * @property string $path
 * @property string $original_filename
 * @property string $mime
 * @property string $extension
 * @property int $size
 * @property string|null $checksum
 */
#[Fillable([
    'ticket_message_id',
    'disk',
    'path',
    'original_filename',
    'mime',
    'extension',
    'size',
    'checksum',
])]
class TicketMessageAttachment extends Model
{
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /** @return BelongsTo<TicketMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }
}
