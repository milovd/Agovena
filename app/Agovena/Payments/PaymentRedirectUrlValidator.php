<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use Illuminate\Validation\ValidationException;

final class PaymentRedirectUrlValidator
{
    public function validate(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '' || ! in_array($scheme, ['http', 'https'], true)
            || array_key_exists('user', $parts) || array_key_exists('pass', $parts)
            || array_key_exists('fragment', $parts)) {
            $this->reject();
        }

        $origin = $scheme.'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '');
        $allowedOrigins = config('agovena.payments.return_url_origins', []);
        $allowedOrigins = is_array($allowedOrigins) ? array_map(
            static fn (mixed $allowed): string => rtrim(strtolower(trim((string) $allowed)), '/'),
            $allowedOrigins,
        ) : [];

        if (! in_array($origin, $allowedOrigins, true)) {
            $this->reject();
        }

        return $url;
    }

    private function reject(): never
    {
        throw ValidationException::withMessages([
            'return_url' => __('validation.url'),
        ]);
    }
}
