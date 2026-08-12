<?php

declare(strict_types=1);

namespace App\Agovena\Extensions;

final readonly class ExtensionManifest
{
    /**
     * @param  list<string>  $dependencies  Other extension ids
     * @param  list<array{key: string, label: string, type?: string, secret?: bool, required?: bool, default?: mixed}>  $settings
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $description,
        public string $provider,
        public string $path,
        public ExtensionCategory $category,
        public string $agovena = '*',
        public array $dependencies = [],
        public string $author = 'Agovena',
        public array $settings = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $path): self
    {
        if (! isset($data['id'], $data['name'], $data['provider'], $data['category'])) {
            throw new \InvalidArgumentException('Extension manifest is missing required keys (id, name, provider, category).');
        }

        $category = ExtensionCategory::tryFrom((string) $data['category']);
        if ($category === null) {
            throw new \InvalidArgumentException('Unknown extension category ['.$data['category'].'].');
        }

        $deps = $data['dependencies'] ?? [];
        if (! is_array($deps)) {
            $deps = [];
        }

        $settings = $data['settings'] ?? [];
        if (! is_array($settings)) {
            $settings = [];
        }

        $normalizedSettings = [];
        foreach ($settings as $setting) {
            if (! is_array($setting) || ! isset($setting['key'])) {
                continue;
            }

            $normalizedSettings[] = [
                'key' => (string) $setting['key'],
                'label' => (string) ($setting['label'] ?? $setting['key']),
                'type' => (string) ($setting['type'] ?? 'string'),
                'secret' => (bool) ($setting['secret'] ?? false),
                'required' => (bool) ($setting['required'] ?? false),
                'default' => $setting['default'] ?? null,
            ];
        }

        return new self(
            id: (string) $data['id'],
            name: (string) $data['name'],
            version: (string) ($data['version'] ?? '0.0.0'),
            description: (string) ($data['description'] ?? ''),
            provider: (string) $data['provider'],
            path: $path,
            category: $category,
            agovena: (string) ($data['agovena'] ?? '*'),
            dependencies: array_values(array_map('strval', $deps)),
            author: (string) ($data['author'] ?? 'Agovena'),
            settings: $normalizedSettings,
        );
    }
}
