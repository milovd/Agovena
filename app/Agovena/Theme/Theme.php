<?php

declare(strict_types=1);

namespace App\Agovena\Theme;

/**
 * Active Theme runtime boundary. Presentation-only; no business rules.
 */
final class Theme
{
    /**
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $viewsPath,
        public readonly string $cssEntry,
        public readonly string $version = '1.0.0',
        public readonly string $description = '',
        public readonly ?string $previewPath = null,
        public readonly array $capabilities = [],
        public readonly string $basePath = '',
        public readonly ?string $adminCssEntry = null,
    ) {}

    public function view(string $name): string
    {
        return "theme::{$name}";
    }

    public function provides(ThemeSurface|string $surface): bool
    {
        $value = $surface instanceof ThemeSurface ? $surface->value : $surface;

        return in_array($value, $this->capabilities, true);
    }

    public function settingsGroup(): string
    {
        return 'theme.'.$this->id;
    }
}
