<?php

declare(strict_types=1);

namespace App\Agovena\Theme;

final class ThemeManifest
{
    /**
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $version,
        public readonly string $cssEntry,
        public readonly string $description = '',
        public readonly ?string $preview = null,
        public readonly array $capabilities = [],
        public readonly ?string $adminCssEntry = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $fallbackId): self
    {
        $id = is_string($data['id'] ?? null) && $data['id'] !== '' ? $data['id'] : $fallbackId;
        $name = is_string($data['name'] ?? null) && $data['name'] !== '' ? $data['name'] : $id;
        $version = is_string($data['version'] ?? null) && $data['version'] !== '' ? $data['version'] : '1.0.0';
        $css = is_string($data['css'] ?? null) && $data['css'] !== ''
            ? $data['css']
            : 'themes/'.$id.'/resources/css/theme.css';
        $description = is_string($data['description'] ?? null) ? $data['description'] : '';
        $preview = is_string($data['preview'] ?? null) && $data['preview'] !== '' ? $data['preview'] : null;
        $adminCss = is_string($data['admin_css'] ?? null) && $data['admin_css'] !== ''
            ? $data['admin_css']
            : null;
        $capabilities = [];
        if (isset($data['capabilities']) && is_array($data['capabilities'])) {
            foreach ($data['capabilities'] as $cap) {
                if (is_string($cap) && $cap !== '') {
                    $capabilities[] = $cap;
                }
            }
        }

        return new self(
            id: $id,
            name: $name,
            version: $version,
            cssEntry: $css,
            description: $description,
            preview: $preview,
            capabilities: $capabilities,
            adminCssEntry: $adminCss,
        );
    }
}
