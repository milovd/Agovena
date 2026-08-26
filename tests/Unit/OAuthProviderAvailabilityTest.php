<?php

declare(strict_types=1);

use App\Agovena\Auth\OAuth\OAuthProviderAvailability;
use App\Agovena\Auth\OAuth\OAuthProviderRegistry;
use App\Agovena\Settings\SettingsRepository;

it('uses env defaults but lets admin settings disable an OAuth provider', function (): void {
    config()->set('services.oauth.discord', [
        'enabled' => true,
        'client_id' => 'discord-client-id',
    ]);
    $provider = app(OAuthProviderRegistry::class)->get('discord');
    $availability = app(OAuthProviderAvailability::class);

    expect($availability->enabled($provider))->toBeTrue();

    app(SettingsRepository::class)->set('auth', 'oauth_discord_enabled', false);

    expect($availability->enabled($provider))->toBeFalse();
});
