<?php

declare(strict_types=1);

use App\Agovena\Auth\OAuth\OAuthOidcTokenVerifier;
use App\Agovena\Auth\OAuth\OAuthProviderRegistry;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

it('rejects malformed and unsigned Google OIDC tokens', function (): void {
    $provider = app(OAuthProviderRegistry::class)->get('google');
    config()->set('services.oauth.google.client_id', 'google-client-id');

    expect(fn () => app(OAuthOidcTokenVerifier::class)->verify($provider, 'not-a-jwt', 'nonce'))
        ->toThrow(ValidationException::class);

    $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'none', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    $claims = rtrim(strtr(base64_encode(json_encode([
        'iss' => 'https://accounts.google.com',
        'aud' => 'google-client-id',
        'sub' => 'unsigned-user',
        'email' => 'unsigned@example.test',
        'email_verified' => true,
        'nonce' => 'nonce',
        'exp' => time() + 600,
    ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

    expect(fn () => app(OAuthOidcTokenVerifier::class)->verify($provider, $header.'.'.$claims.'.', 'nonce'))
        ->toThrow(ValidationException::class);
});

it('fails closed when the configured Google JWKS endpoint cannot be reached', function (): void {
    $provider = app(OAuthProviderRegistry::class)->get('google');
    config()->set('services.oauth.google.client_id', 'google-client-id');
    Http::fake(['https://www.googleapis.com/oauth2/v3/certs' => Http::response([], 503)]);

    expect(fn () => app(OAuthOidcTokenVerifier::class)->verify($provider, 'a.b.c', 'nonce'))
        ->toThrow(ValidationException::class);
});
