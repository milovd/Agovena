<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['source', 'entity', 'mode', 'status', 'read', 'valid', 'duplicates', 'errors', 'started_at', 'completed_at'])]
class ImportRun extends Model
{
    /** @return HasMany<ImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    protected function casts(): array
    {
        return [
            'read' => 'integer',
            'valid' => 'integer',
            'duplicates' => 'integer',
            'errors' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
