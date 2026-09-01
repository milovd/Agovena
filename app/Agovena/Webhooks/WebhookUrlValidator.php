<?php

declare(strict_types=1);

namespace App\Agovena\Webhooks;

final class WebhookUrlValidator
{
    public static function isAllowed(string $url): bool
    {
        return self::allowedPublicIp($url) !== null;
    }

    public static function allowedPublicIp(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        if (in_array($host, ['localhost', 'localhost.localdomain', '[::1]'], true)) {
            return null;
        }

        if (preg_match('/^(?:0x[0-9a-f]+|0[0-7]+|[0-9]+)$/i', $host) === 1
            || preg_match('/^(?:[0-9]+\.){1,3}[0-9]+$/', $host) === 1
        ) {
            return null;
        }

        $ip = trim($host, '[]');
        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            return filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) !== false ? $ip : null;
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        if ($records === []) {
            return null;
        }

        $publicIp = null;
        foreach ($records as $record) {
            $resolvedIp = $record['ip'] ?? $record['ipv6'] ?? null;
            if (! is_string($resolvedIp) || filter_var(
                $resolvedIp,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) === false) {
                return null;
            }
            $publicIp ??= $resolvedIp;
        }

        return $publicIp;
    }
}
