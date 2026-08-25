<?php

declare(strict_types=1);

namespace App\Agovena\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;

final class AuditLogQuery
{
    /** @param array<string, mixed> $filters */
    public function build(array $filters = []): Builder
    {
        $query = AuditLog::query();

        foreach (['category', 'severity', 'outcome', 'actor_type', 'method'] as $field) {
            $value = $filters[$field] ?? null;
            if (is_string($value) && $value !== '') {
                $query->where($field, $value);
            }
        }

        foreach (['action', 'request_id', 'correlation_id'] as $field) {
            $value = $filters[$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $query->where($field, 'like', '%'.trim($value).'%');
            }
        }

        foreach (['actor_id', 'subject_id'] as $field) {
            $value = $filters[$field] ?? null;
            if (is_string($value) && trim($value) !== '' && ctype_digit(trim($value))) {
                $query->where($field, (int) trim($value));
            }
        }

        if (is_string($filters['subject_type'] ?? null) && trim((string) $filters['subject_type']) !== '') {
            $query->where('subject_type', 'like', '%'.trim((string) $filters['subject_type']).'%');
        }

        if (is_string($filters['ip'] ?? null) && trim((string) $filters['ip']) !== '') {
            $query->where('ip', 'like', '%'.trim((string) $filters['ip']).'%');
        }

        if (is_string($filters['from'] ?? null) && $filters['from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (is_string($filters['to'] ?? null) && $filters['to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        if (is_string($filters['search'] ?? null) && trim($filters['search']) !== '') {
            $query->search($filters['search']);
        }

        return $query->latest('id');
    }
}
