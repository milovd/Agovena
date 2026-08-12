<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Installed module lifecycle row. Disable keeps this row and module tables.
 *
 * @property int $id
 * @property string $module_id
 * @property string $version
 * @property bool $enabled
 * @property Carbon|null $installed_at
 * @property Carbon|null $enabled_at
 * @property Carbon|null $disabled_at
 * @property array<string, mixed>|null $meta
 */
#[Fillable([
    'module_id',
    'version',
    'enabled',
    'installed_at',
    'enabled_at',
    'disabled_at',
    'meta',
])]
class AgovenaModule extends Model
{
    protected $table = 'agovena_modules';

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'installed_at' => 'datetime',
            'enabled_at' => 'datetime',
            'disabled_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
