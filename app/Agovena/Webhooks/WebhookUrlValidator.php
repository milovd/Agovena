<?php

declare(strict_types=1);

namespace App\Agovena\Webhooks;

final class WebhookUrlValidator
{
    public static function isAllowed(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || $host === '' || isset($parts['user'], $parts['pass'])) {
            return false;
        }

        if (in_array($host, ['localhost', 'localhost.localdomain', '[::1]'], true)) {
            return false;
        }

        if (preg_match('/^(?:0x[0-9a-f]+|0[0-7]+|[0-9]+)$/i', $host) === 1
            || preg_match('/^(?:[0-9]+\.){1,3}[0-9]+$/', $host) === 1
        ) {
            return false;
        }

        $ip = trim($host, '[]');
        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            return filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) !== false;
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        foreach ($records as $record) {
            $resolvedIp = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($resolvedIp) && filter_var(
                $resolvedIp,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) === false) {
                return false;
            }
        }

        return true;
    }
}
