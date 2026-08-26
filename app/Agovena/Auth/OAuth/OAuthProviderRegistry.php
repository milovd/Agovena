<?php

declare(strict_types=1);

namespace App\Agovena\Auth\OAuth;

use InvalidArgumentException;

final class OAuthProviderRegistry
{
    /** @return list<OAuthProviderDefinition> */
    public function all(): array
    {
        return [
            new OAuthProviderDefinition(
                id: 'google',
                authorizationEndpoint: 'https://accounts.google.com/o/oauth2/v2/auth',
                tokenEndpoint: 'https://oauth2.googleapis.com/token',
                userInfoEndpoint: 'https://openidconnect.googleapis.com/v1/userinfo',
                scopes: ['openid', 'email', 'profile'],
                oidc: true,
                issuer: 'https://accounts.google.com',
                jwksEndpoint: 'https://www.googleapis.com/oauth2/v3/certs',
            ),
            new OAuthProviderDefinition(
                id: 'discord',
                authorizationEndpoint: 'https://discord.com/oauth2/authorize',
                tokenEndpoint: 'https://discord.com/api/oauth2/token',
                userInfoEndpoint: 'https://discord.com/api/users/@me',
                scopes: ['identify', 'email'],
            ),
        ];
    }

    public function get(string $id): OAuthProviderDefinition
    {
        foreach ($this->all() as $provider) {
            if ($provider->id === $id) {
                return $provider;
            }
        }

        throw new InvalidArgumentException('Unsupported OAuth provider.');
    }
}
