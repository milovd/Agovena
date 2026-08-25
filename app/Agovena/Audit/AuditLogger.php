<?php

declare(strict_types=1);

namespace App\Agovena\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use JsonSerializable;

final class AuditLogger
{
    /**
     * Record a structured, redacted, append-only operational event.
     *
     * Existing callers can keep using the original three arguments. The optional
     * arguments add before/after snapshots and explicit result classification.
     *
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?array $before = null,
        ?array $after = null,
        ?string $outcome = null,
        ?string $severity = null,
        ?string $category = null,
        ?string $correlationId = null,
    ): AuditLog {
        $request = $this->request();
        $user = Auth::user();
        $isAdminContext = $request?->is('admin') || $request?->is('admin/*');
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

        $eventId = (string) Str::uuid();
        $requestId = $this->requestIdentifier($request, 'audit_request_id', 'X-Request-ID');
        $correlationId = $this->normaliseIdentifier(
            $correlationId
                ?? $this->requestIdentifier($request, 'audit_correlation_id', 'X-Correlation-ID')
                ?? $requestId,
        );
        $cleanProperties = $this->redact($properties);
        $cleanBefore = $before === null ? null : $this->redact($before);
        $cleanAfter = $after === null ? null : $this->redact($after);
        $route = $this->routeName($request);
        $resolvedOutcome = $outcome ?? $this->outcomeFor($action);
        $method = $request?->method();
        $statusCode = $request?->attributes->get('audit_status_code');
        $context = $this->redact([
            'source' => $request === null ? (app()->runningInConsole() ? 'console' : 'system') : 'http',
            'route' => $route,
            'method' => $method,
            'path' => $request?->path(),
            'locale' => $request?->getLocale(),
        ]);
        $createdAt = now();

        $log = new AuditLog([
            'event_id' => $eventId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'category' => $category ?? $this->categoryFor($action),
            'severity' => $severity ?? $this->severityFor($action, $resolvedOutcome),
            'outcome' => $resolvedOutcome,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $cleanProperties,
            'before' => $cleanBefore,
            'after' => $cleanAfter,
            'context' => array_filter($context, static fn (mixed $value): bool => $value !== null && $value !== ''),
            'ip' => $request?->ip(),
            'user_agent' => $this->limitString($request?->userAgent(), 1024),
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'route' => $route,
            'method' => $method,
            'status_code' => is_numeric($statusCode) ? (int) $statusCode : null,
            'created_at' => $createdAt,
        ]);
        $log->integrity_hash = hash(
            'sha256',
            (string) json_encode($log->integrityPayload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
        $log->save();

        return $log;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function redactForOutput(array $values): array
    {
        return $this->redact($values);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $properties
     */
    public function logChange(
        string $action,
        Model $subject,
        array $before,
        array $after,
        array $properties = [],
        ?string $outcome = null,
        ?string $severity = null,
        ?string $category = null,
    ): AuditLog {
        return $this->log($action, $subject, $properties, $before, $after, $outcome, $severity, $category);
    }

    private function request(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        return app('request');
    }

    private function requestIdentifier(?Request $request, string $attribute, string $header): ?string
    {
        if ($request === null) {
            return null;
        }

        $existing = $request->attributes->get($attribute);
        if (is_string($existing) && $existing !== '') {
            return $this->normaliseIdentifier($existing);
        }

        $value = $request->header($header);
        $identifier = $this->normaliseIdentifier(is_string($value) ? $value : null) ?? (string) Str::uuid();
        $request->attributes->set($attribute, $identifier);

        return $identifier;
    }

    private function normaliseIdentifier(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        if ($value === null || $value === '') {
            return null;
        }

        return $this->limitString($value, 100);
    }

    private function routeName(?Request $request): ?string
    {
        $route = $request?->route();
        if ($route === null) {
            return null;
        }

        $name = $route->getName();

        return is_string($name) ? $this->limitString($name, 255) : null;
    }

    private function categoryFor(string $action): string
    {
        $prefix = strtolower((string) str($action)->before('.'));

        return match ($prefix) {
            'admin', 'appearance', 'settings', 'module', 'extension', 'role', 'user' => 'admin',
            'auth', 'login', 'password', 'two_factor', 'api_token' => 'auth',
            'order', 'cart', 'checkout', 'invoice', 'credit_note', 'product', 'inventory' => 'commerce',
            'payment' => 'payment',
            'refund' => 'refund',
            'customer', 'privacy' => 'privacy',
            'ticket', 'support' => 'support',
            'notification', 'email' => 'notification',
            'webhook' => 'webhook',
            'security' => 'security',
            default => 'system',
        };
    }

    private function outcomeFor(string $action): string
    {
        return match (strtolower((string) str($action)->afterLast('.'))) {
            'failed', 'failure', 'error', 'rejected' => 'failure',
            'denied', 'forbidden', 'unauthorized', 'access_denied', 'permission_denied' => 'denied',
            'pending', 'queued', 'processing' => 'pending',
            default => 'success',
        };
    }

    private function severityFor(string $action, ?string $outcome): string
    {
        if (in_array($outcome, ['failure', 'denied'], true)) {
            return str_starts_with($action, 'auth.') || str_starts_with($action, 'security.') ? 'critical' : 'warning';
        }

        return in_array($this->categoryFor($action), ['security', 'refund', 'payment'], true) ? 'warning' : 'info';
    }

    /** @param array<string, mixed> $values */
    /** @return array<string, mixed> */
    private function redact(array $values): array
    {
        $redacted = [];
        foreach ($values as $key => $value) {
            $keyString = strtolower((string) $key);
            if ($this->isSensitiveKey($keyString)) {
                $redacted[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $redacted[$key] = $this->redact($value);

                continue;
            }

            if ($value instanceof JsonSerializable) {
                $serialised = $value->jsonSerialize();
                $redacted[$key] = is_array($serialised) ? $this->redact($serialised) : $this->redactString($serialised);

                continue;
            }

            $redacted[$key] = is_string($value) ? $this->redactString($value) : $value;
        }

        return $redacted;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach ([
            'password', 'secret', 'token', 'authorization', 'cookie', 'api_key', 'apikey',
            'access_key', 'private_key', 'client_secret', 'connection_string', 'webhook_secret',
            'signing_secret', 'card_number', 'cardnumber', 'pan', 'cvv', 'cvc', 'otp',
            'recovery_code', 'plain_text', 'plaintext', 'passphrase', 'credential',
            'email', 'phone', 'address', 'customer_name', 'full_name',
        ] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function redactString(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (preg_match('/Bearer\s+\S+|-----BEGIN [^-]+-----|(?:sk|pk|test|live)_[A-Za-z0-9_-]{12,}/i', $value) === 1) {
            return '[REDACTED]';
        }

        return $this->limitString($value, 4000);
    }

    private function limitString(?string $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strlen($value) > $length ? mb_substr($value, 0, $length).'...[TRUNCATED]' : $value;
    }
}
