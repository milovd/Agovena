<?php

declare(strict_types=1);

namespace App\Agovena\Theme;

use App\Agovena\Media\PublicMedia;
use App\Agovena\Settings\SettingsRepository;

/**
 * Resolves the storefront mark. Never returns a URL that 404s.
 */
final class StorefrontBrand
{
    public const BUNDLED_LOGO = 'vendor/agovena/logo.png';

    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function siteName(): string
    {
        return (string) $this->settings->get('general', 'site_name', config('app.name', 'Agovena'));
    }

    public function logoUrl(): string
    {
        $configured = $this->settings->get('branding', 'logo_path');
        $url = is_string($configured) ? PublicMedia::url($configured) : null;

        return $url ?? asset(self::BUNDLED_LOGO);
    }

    public function faviconUrl(): ?string
    {
        $favicon = $this->settings->get('branding', 'favicon_path');
        $url = is_string($favicon) ? PublicMedia::url($favicon) : null;
        if ($url !== null) {
            return $url;
        }

        $logo = $this->settings->get('branding', 'logo_path');

        return is_string($logo) ? PublicMedia::url($logo) : null;
    }
}
