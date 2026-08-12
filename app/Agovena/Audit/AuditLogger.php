<?php

declare(strict_types=1);

namespace App\Agovena\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class AuditLogger
{
    /** @param array<string, mixed> $properties */
    public function log(string $action, ?Model $subject = null, array $properties = []): AuditLog
    {
        $staff = Auth::guard('staff')->user();
        $customer = Auth::guard('customer')->user();

        return AuditLog::query()->create([
            'actor_type' => $staff !== null ? 'staff' : ($customer !== null ? 'customer' : 'system'),
            'actor_id' => $staff?->getAuthIdentifier() ?? $customer?->getAuthIdentifier(),
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
