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
            new OAuthProviderDefinition('google', 'https://accounts.google.com/o/oauth2/v2/auth', ['openid', 'email', 'profile']),
            new OAuthProviderDefinition('discord', 'https://discord.com/oauth2/authorize', ['identify', 'email']),
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
