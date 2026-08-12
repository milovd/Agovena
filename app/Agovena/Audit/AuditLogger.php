<?php

declare(strict_types=1);

namespace App\Agovena\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class AuditLogger
{
    /** @param array<string, mixed> $properties */
    public function log(string $action, ?Model $subject = null, array $properties = []): AuditLog
    {
        $user = Auth::user();
        $isAdminContext = request()->is('admin') || request()->is('admin/*');

        $actorType = 'system';
        $actorId = null;

        if ($user instanceof User) {
            if ($isAdminContext || $user->canAccessAdmin()) {
                $actorType = 'staff';
                $actorId = $user->getKey();
            } else {
                $actorType = 'customer';
                $actorId = $user->customer?->getKey() ?? $user->getKey();
            }
        }

        return AuditLog::query()->create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $this->redact($properties),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (preg_match('/password|secret|token|authorization|cookie/i', (string) $key) === 1) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
