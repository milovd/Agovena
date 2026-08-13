<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string|null $subject
 * @property string|null $body
 * @property bool $enabled
 */
final class NotificationTemplate extends Model
{
    protected $fillable = [
        'key',
        'subject',
        'body',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
