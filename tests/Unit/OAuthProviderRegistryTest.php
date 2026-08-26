<?php

declare(strict_types=1);

use App\Agovena\Auth\OAuth\OAuthProviderRegistry;

it('exposes only the first-party Google and Discord OAuth metadata', function (): void {
    $providers = app(OAuthProviderRegistry::class)->all();

    expect(array_column($providers, 'id'))->toBe(['google', 'discord'])
        ->and(app(OAuthProviderRegistry::class)->get('google')->scopes)->toContain('openid')
        ->and(app(OAuthProviderRegistry::class)->get('discord')->scopes)->toContain('identify');
});
