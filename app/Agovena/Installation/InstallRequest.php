<?php

declare(strict_types=1);

namespace App\Agovena\Installation;

final readonly class InstallRequest
{
    public function __construct(
        public string $ownerName,
        public string $ownerEmail,
        public string $ownerPassword,
        public string $siteName,
        public string $locale,
        public string $timezone,
        public string $currencyCode,
        public string $themeId,
        public ?string $logoPath = null,
        public ?string $faviconPath = null,
        /** @var list<string> */
        public array $presetIds = [],
    ) {}
}
