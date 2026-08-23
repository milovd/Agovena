<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Named provisioner connection. Credentials are encrypted as one settings payload.
 *
 * @property int $id
 * @property string $name
 * @property string $provider_key
 * @property array<string, mixed> $settings
 * @property bool $is_active
 */
class ProvisioningServer extends Model
{
    protected $fillable = [
        'name',
        'provider_key',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }
}
