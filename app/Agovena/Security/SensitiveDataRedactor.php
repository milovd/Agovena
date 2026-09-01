<?php

declare(strict_types=1);

namespace App\Agovena\Security;

final class SensitiveDataRedactor
{
    public static function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && self::isSensitiveKey($key)) {
            return '[REDACTED]';
        }

        if (is_string($value) && self::containsEmbeddedSecret($value)) {
            return '[REDACTED]';
        }

        if (! is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $nestedKey => $nestedValue) {
            if (is_string($nestedKey) && str_ends_with(strtolower(trim($nestedKey)), '_encrypted')) {
                continue;
            }

            if (is_array($nestedValue)
                && is_string($nestedValue['key'] ?? null)
                && array_key_exists('value', $nestedValue)
                && self::isSensitiveKey($nestedValue['key'])
            ) {
                $nestedValue['value'] = '[REDACTED]';
                if (array_key_exists('display', $nestedValue)) {
                    $nestedValue['display'] = '[REDACTED]';
                }
            }

            $redacted[$nestedKey] = self::redact(
                $nestedValue,
                is_string($nestedKey) ? $nestedKey : null,
            );
        }

        return $redacted;
    }

    public static function isSensitiveKey(string $key): bool
    {
        $normalizedKey = strtolower(trim($key));

        return $normalizedKey === 'environment'
            || $normalizedKey === 'server_settings'
            || str_ends_with($normalizedKey, '_encrypted')
            || preg_match('/(?:api[_-]?key|access[_-]?key|token|secret|password|passwd|credential|authorization|private[_-]?key|connection|string|dsn)/', $normalizedKey) === 1;
    }

    public static function isSensitiveValue(mixed $value): bool
    {
        return is_string($value) && self::containsEmbeddedSecret($value);
    }

    private static function containsEmbeddedSecret(string $value): bool
    {
        return preg_match('~\b[a-z][a-z0-9+.-]*://[^/\s:@]+:[^@\s]+@~i', $value) === 1
            || preg_match("~(?:^|[?&;\\s])(?:api[_-]?key|access[_-]?key|token|secret|password|passwd|pwd|credential|authorization)\\s*[:=]\\s*(?:\"[^\"]+\"|'[^']+'|[^\\s&;]+)~i", $value) === 1
            || preg_match('#\b(?:Bearer|Basic)\s+[A-Za-z0-9._~+/=-]{12,}#i', $value) === 1;
    }
}
