<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['import_run_id', 'line', 'entity', 'external_id', 'status', 'payload', 'imported_model_type', 'imported_model_id', 'error'])]
class ImportRow extends Model
{
    /** @return BelongsTo<ImportRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class, 'import_run_id');
    }

    protected function casts(): array
    {
        return [
            'line' => 'integer',
            'payload' => 'array',
            'imported_model_id' => 'integer',
        ];
    }
}
