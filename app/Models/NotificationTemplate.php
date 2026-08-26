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
        'mail_format',
        'notification_title',
        'notification_body',
        'enabled',
        'mail_enabled',
        'in_app_enabled',
        'push_enabled',
        'user_choice',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'mail_enabled' => 'boolean',
            'in_app_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'user_choice' => 'boolean',
        ];
    }
}
