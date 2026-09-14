<?php

declare(strict_types=1);

use App\Agovena\Payments\PaymentRedirectUrlValidator;
use Illuminate\Validation\ValidationException;

it('accepts redirect URLs from explicitly allowed origins', function (): void {
    config()->set('agovena.payments.return_url_origins', ['https://shop.example.test']);

    expect(app(PaymentRedirectUrlValidator::class)->validate('https://shop.example.test/checkout/complete'))
        ->toBe('https://shop.example.test/checkout/complete');
});

it('rejects redirect URLs from untrusted origins or with unsafe URL parts', function (string $url): void {
    config()->set('agovena.payments.return_url_origins', ['https://shop.example.test']);

    expect(fn () => app(PaymentRedirectUrlValidator::class)->validate($url))
        ->toThrow(ValidationException::class);
})->with([
    'https://evil.example.test/phishing',
    'https://shop.example.test@evil.example.test/phishing',
    'https://shop.example.test/checkout#token',
]);
