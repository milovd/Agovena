<?php

declare(strict_types=1);

namespace App\Agovena\Api;

final class ApiIpAllowlist
{
    public const GROUP = 'api';

    public const KEY = 'ip_allowlist';

    private const MAX_ENTRIES = 256;

    /**
     * @return list<string>
     */
    public function parse(string $input): array
    {
        $input = trim($input);
        if ($input === '') {
            return [];
        }

        $tokens = preg_split('/[\s,]+/', $input, -1, PREG_SPLIT_NO_EMPTY);

        return $this->normalizeTokens(is_array($tokens) ? $tokens : []);
    }

    /**
     * @return list<string>
     */
    public function normalizeStored(mixed $stored): array
    {
        if ($stored === null || $stored === '') {
            return [];
        }

        if (is_string($stored)) {
            return $this->parse($stored);
        }

        if (! is_array($stored)) {
            throw new \InvalidArgumentException('The API IP allowlist must be a list of IP addresses.');
        }

        $tokens = [];
        foreach ($stored as $value) {
            if (! is_string($value)) {
                throw new \InvalidArgumentException('The API IP allowlist must contain only IP addresses.');
            }

            $tokens[] = trim($value);
        }

        return $this->normalizeTokens(array_values(array_filter(
            $tokens,
            static fn (string $token): bool => $token !== '',
        )));
    }

    public function allows(?string $requestIp, mixed $stored): bool
    {
        $normalizedRequestIp = $this->normalizeIp($requestIp);
        if ($normalizedRequestIp === null) {
            return false;
        }

        if ($stored === null || $stored === '' || (is_array($stored) && $stored === [])) {
            return true;
        }

        try {
            $allowedIps = $this->normalizeStored($stored);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return $allowedIps === [] || in_array($normalizedRequestIp, $allowedIps, true);
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function normalizeTokens(array $tokens): array
    {
        $normalized = [];
        foreach ($tokens as $token) {
            $ip = $this->normalizeIp($token);
            if ($ip === null) {
                throw new \InvalidArgumentException('The API IP allowlist contains an invalid IP address.');
            }

            $normalized[$ip] = true;
            if (count($normalized) > self::MAX_ENTRIES) {
                throw new \InvalidArgumentException('The API IP allowlist contains too many entries.');
            }
        }

        return array_keys($normalized);
    }

    private function normalizeIp(?string $ip): ?string
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $packed = inet_pton($ip);
        if ($packed === false) {
            return null;
        }

        $normalized = inet_ntop($packed);

        return is_string($normalized) ? $normalized : null;
    }
}
