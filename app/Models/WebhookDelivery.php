<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'delivery_id',
        'webhook_endpoint_id',
        'event_type',
        'payload',
        'status',
        'attempt_count',
        'response_status',
        'response_body',
        'last_error',
        'failure_code',
        'next_attempt_at',
        'failed_at',
        'dead_lettered_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'next_attempt_at' => 'datetime',
            'failed_at' => 'datetime',
            'dead_lettered_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<WebhookEndpoint, $this> */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
