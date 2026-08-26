<?php

declare(strict_types=1);

namespace App\Agovena\Auth\OAuth;

use App\Agovena\Settings\SettingsRepository;

final class OAuthProviderAvailability
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function enabled(OAuthProviderDefinition $provider): bool
    {
        $config = config('services.oauth.'.$provider->id, []);
        $default = is_array($config) && (bool) ($config['enabled'] ?? false);

        return filter_var(
            $this->settings->get('auth', 'oauth_'.$provider->id.'_enabled', $default),
            FILTER_VALIDATE_BOOLEAN,
        );
    }
}
