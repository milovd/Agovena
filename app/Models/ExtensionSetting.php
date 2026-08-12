<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $extension_id
 * @property string $key
 * @property string|null $value
 * @property bool $is_secret
 */
#[Fillable(['extension_id', 'key', 'value', 'is_secret'])]
class ExtensionSetting extends Model
{
    protected $table = 'extension_settings';

    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
        ];
    }
}
