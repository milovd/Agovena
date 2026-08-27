<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['import_run_id', 'import_row_id', 'source', 'entity', 'external_id'])]
class ImportIdentityReservation extends Model
{
    /** @return BelongsTo<ImportRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class, 'import_run_id');
    }

    /** @return BelongsTo<ImportRow, $this> */
    public function row(): BelongsTo
    {
        return $this->belongsTo(ImportRow::class, 'import_row_id');
    }
}
