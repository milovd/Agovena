<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $to
 * @property string|null $subject
 * @property string|null $notification_key
 * @property string $status
 * @property string|null $error
 */
final class EmailLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'to',
        'subject',
        'notification_key',
        'status',
        'error',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
