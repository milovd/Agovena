<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Installed extension lifecycle row. Disable keeps this row and settings.
 *
 * @property int $id
 * @property string $extension_id
 * @property string $version
 * @property bool $enabled
 * @property Carbon|null $installed_at
 * @property Carbon|null $enabled_at
 * @property Carbon|null $disabled_at
 * @property array<string, mixed>|null $meta
 */
#[Fillable([
    'extension_id',
    'version',
    'enabled',
    'installed_at',
    'enabled_at',
    'disabled_at',
    'meta',
])]
class AgovenaExtension extends Model
{
    protected $table = 'agovena_extensions';

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
