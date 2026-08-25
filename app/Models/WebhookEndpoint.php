<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property list<string> $events
 */
class WebhookEndpoint extends Model
{
    protected $fillable = [
        'name',
        'url',
        'secret',
        'events',
        'active',
        'failure_count',
        'last_failure_at',
        'last_delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'active' => 'boolean',
            'last_failure_at' => 'datetime',
            'last_delivered_at' => 'datetime',
        ];
    }

    /** @return HasMany<WebhookDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
