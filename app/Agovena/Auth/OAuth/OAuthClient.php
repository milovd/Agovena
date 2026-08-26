<?php

declare(strict_types=1);

namespace App\Agovena\Auth\OAuth;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

final class OAuthClient
{
    /** @return array<string, mixed> */
    public function exchangeCode(OAuthProviderDefinition $provider, string $code, string $redirectUri): array
    {
        $config = config('services.oauth.'.$provider->id, []);
        $clientId = is_array($config) ? (string) ($config['client_id'] ?? '') : '';
        $clientSecret = is_array($config) ? (string) ($config['client_secret'] ?? '') : '';
        if ($clientId === '' || $clientSecret === '') {
            throw ValidationException::withMessages(['provider' => 'The OAuth provider is not configured.']);
        }

        $response = Http::asForm()->timeout(10)->post($provider->tokenEndpoint, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);
        if (! $response->successful()) {
            throw ValidationException::withMessages(['provider' => 'The OAuth token exchange failed.']);
        }

        $payload = $response->json();

        return is_array($payload) && is_string($payload['access_token'] ?? null)
            ? $payload
            : throw ValidationException::withMessages(['provider' => 'The OAuth token response is invalid.']);
    }

    /** @return array<string, mixed> */
    public function userInfo(OAuthProviderDefinition $provider, string $accessToken): array
    {
        $response = Http::withToken($accessToken)->timeout(10)->get($provider->userInfoEndpoint);
        if (! $response->successful()) {
            throw ValidationException::withMessages(['provider' => 'The OAuth profile request failed.']);
        }

        $payload = $response->json();

        return is_array($payload)
            ? $payload
            : throw ValidationException::withMessages(['provider' => 'The OAuth profile response is invalid.']);
    }
}
