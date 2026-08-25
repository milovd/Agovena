<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

#[Fillable([
    'event_id', 'actor_type', 'actor_id', 'action', 'category', 'severity', 'outcome',
    'subject_type', 'subject_id', 'properties', 'before', 'after', 'context', 'ip',
    'user_agent', 'request_id', 'correlation_id', 'route', 'method', 'status_code',
    'integrity_hash',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Audit logs are append-only and cannot be updated.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Audit logs are append-only and can only be pruned by the retention command.');
        });
    }

    public const CATEGORIES = [
        'admin', 'auth', 'commerce', 'data', 'notification', 'payment', 'privacy',
        'refund', 'security', 'support', 'system', 'webhook',
    ];

    public const SEVERITIES = ['info', 'warning', 'critical'];

    public const OUTCOMES = ['success', 'failure', 'denied', 'pending'];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'before' => 'array',
            'after' => 'array',
            'context' => 'array',
            'status_code' => 'integer',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = '%'.trim($term).'%';

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('event_id', 'like', $term)
                ->orWhere('action', 'like', $term)
                ->orWhere('subject_type', 'like', $term)
                ->orWhere('subject_id', 'like', $term)
                ->orWhere('actor_id', 'like', $term)
                ->orWhere('ip', 'like', $term)
                ->orWhere('request_id', 'like', $term)
                ->orWhere('correlation_id', 'like', $term)
                ->orWhere('properties', 'like', $term)
                ->orWhere('before', 'like', $term)
                ->orWhere('after', 'like', $term);
        });
    }

    /** @return array<string, mixed> */
    public function integrityPayload(): array
    {
        return [
            'event_id' => $this->event_id,
            'actor_type' => $this->actor_type,
            'actor_id' => $this->actor_id,
            'action' => $this->action,
            'category' => $this->category,
            'severity' => $this->severity,
            'outcome' => $this->outcome,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'properties' => $this->properties,
            'before' => $this->before,
            'after' => $this->after,
            'context' => $this->context,
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            'request_id' => $this->request_id,
            'correlation_id' => $this->correlation_id,
            'route' => $this->route,
            'method' => $this->method,
            'status_code' => $this->status_code,
        ];
    }

    public function integrityIsValid(): bool
    {
        if (! is_string($this->integrity_hash) || $this->integrity_hash === '') {
            return false;
        }

        return hash_equals(
            $this->integrity_hash,
            hash('sha256', (string) json_encode($this->integrityPayload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
        );
    }

    /** @return array<string, mixed> */
    public function prettyJson(?array $value): array
    {
        return $value ?? [];
    }
}
