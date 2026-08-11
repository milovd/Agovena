<?php

declare(strict_types=1);

namespace App\Agovena\Theme;

/**
 * Minimal Theme runtime boundary. Presentation-only; no business rules.
 */
final class Theme
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $viewsPath,
        public readonly string $cssEntry,
    ) {}

    public function view(string $name): string
    {
        return "theme::{$name}";
    }
}
