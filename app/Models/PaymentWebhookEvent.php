<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Persistent webhook ingress record for idempotency / replay protection.
 *
 * @property int $id
 * @property string $gateway_id
 * @property string|null $external_event_id
 * @property string|null $external_payment_id
 * @property string $status
 * @property string $processing_status
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $processed_at
 */
#[Fillable([
    'gateway_id',
    'external_event_id',
    'external_payment_id',
    'status',
    'processing_status',
    'payload',
    'processed_at',
])]
class PaymentWebhookEvent extends Model
{
    protected $table = 'payment_webhook_events';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
