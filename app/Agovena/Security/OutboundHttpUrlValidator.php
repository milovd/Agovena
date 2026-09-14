<?php

declare(strict_types=1);

namespace App\Agovena\Security;

use Illuminate\Validation\ValidationException;

final class OutboundHttpUrlValidator
{
    public function validate(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($url === '' || ! in_array($scheme, ['https', 'http'], true) || $host === ''
            || array_key_exists('user', $parts) || array_key_exists('pass', $parts) || array_key_exists('fragment', $parts)
            || str_contains($host, "\0")) {
            $this->reject();
        }

        if ($scheme !== 'https' && config('app.env') !== 'local' && config('app.env') !== 'testing') {
            $this->reject();
        }

        if (in_array($host, ['localhost', 'localhost.localdomain', 'metadata.google.internal'], true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')) {
            $this->reject();
        }

        $this->assertPublicAddress($host);

        return $url;
    }

    public function assertTlsVerification(bool $verifyTls): void
    {
        if (! $verifyTls && config('app.env') !== 'local' && config('app.env') !== 'testing') {
            $this->reject();
        }
    }

    public function resolvePublicIp(string $url): string
    {
        $validated = $this->validate($url);
        $host = strtolower((string) parse_url($validated, PHP_URL_HOST));

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $host;
        }

        $addresses = array_merge(
            gethostbynamel($host) ?: [],
            array_map(
                static fn (array $record): string => (string) ($record['ipv6'] ?? ''),
                function_exists('dns_get_record') ? (dns_get_record($host, DNS_AAAA) ?: []) : [],
            ),
        );

        foreach ($addresses as $address) {
            if ($address !== '' && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                return $address;
            }
        }

        $this->reject();
    }

    private function assertPublicAddress(string $host): void
    {
        if (str_contains($host, ':') || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                $this->reject();
            }

            return;
        }

        $addresses = array_merge(
            gethostbynamel($host) ?: [],
            array_map(
                static fn (array $record): string => (string) ($record['ipv6'] ?? ''),
                function_exists('dns_get_record') ? (dns_get_record($host, DNS_AAAA) ?: []) : [],
            ),
        );

        foreach ($addresses as $address) {
            if ($address !== '' && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                $this->reject();
            }
        }
    }

    private function reject(): never
    {
        throw ValidationException::withMessages([
            'provider' => __('validation.url'),
        ]);
    }
}
