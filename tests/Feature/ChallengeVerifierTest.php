<?php

declare(strict_types=1);

use App\Agovena\Abuse\ChallengeVerifierRegistry;
use App\Agovena\Settings\SettingsRepository;
use Illuminate\Support\Facades\Http;

it('verifies configured turnstile and recaptcha challenges without exposing tokens', function (string $provider, string $endpoint): void {
    app(SettingsRepository::class)->set('security', $provider.'_secret', '[REDACTED]');
    Http::fake([$endpoint => Http::response(['success' => true], 200)]);

    $result = app(ChallengeVerifierRegistry::class)->verify($provider, 'challenge-token', '203.0.113.10');

    expect($result->accepted)->toBeTrue()->and($result->provider)->toBe($provider);
})->with([
    'turnstile' => ['turnstile', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'],
    'recaptcha' => ['recaptcha', 'https://www.google.com/recaptcha/api/siteverify'],
]);

it('fails closed when a challenge provider is not configured or responds negatively', function (): void {
    Http::fake(['https://challenges.cloudflare.com/*' => Http::response(['success' => false], 200)]);

    $missing = app(ChallengeVerifierRegistry::class)->verify('turnstile', 'token', '203.0.113.10');
    app(SettingsRepository::class)->set('security', 'turnstile_secret', '[REDACTED]');
    $rejected = app(ChallengeVerifierRegistry::class)->verify('turnstile', 'token', '203.0.113.10');

    expect($missing->accepted)->toBeFalse()->and($rejected->accepted)->toBeFalse();
});
