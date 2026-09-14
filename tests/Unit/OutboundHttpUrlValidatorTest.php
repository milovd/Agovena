<?php

declare(strict_types=1);

use App\Agovena\Security\OutboundHttpUrlValidator;
use Illuminate\Validation\ValidationException;

it('accepts HTTPS provider URLs without credentials or fragments', function (): void {
    expect(app(OutboundHttpUrlValidator::class)->validate('https://provider.example.test:8443/api'))
        ->toBe('https://provider.example.test:8443/api');
});

it('rejects local and private provider targets', function (string $url): void {
    expect(fn () => app(OutboundHttpUrlValidator::class)->validate($url))
        ->toThrow(ValidationException::class);
})->with([
    'http://127.0.0.1:8080',
    'https://localhost/api',
    'https://169.254.169.254/latest/meta-data',
    'https://10.0.0.5/api',
    'https://[::1]/api',
]);

it('rejects provider URLs with credentials, fragments, or insecure schemes', function (string $url): void {
    expect(fn () => app(OutboundHttpUrlValidator::class)->validate($url))
        ->toThrow(ValidationException::class);
})->with([
    'https://token:secret@provider.example.test/api',
    'https://provider.example.test/api#fragment',
    'ftp://provider.example.test/api',
]);

it('only allows disabled TLS verification in local or testing environments', function (): void {
    config()->set('app.env', 'production');

    expect(fn () => app(OutboundHttpUrlValidator::class)->assertTlsVerification(true))
        ->not->toThrow(Throwable::class)
        ->and(fn () => app(OutboundHttpUrlValidator::class)->assertTlsVerification(false))
        ->toThrow(ValidationException::class);
});
