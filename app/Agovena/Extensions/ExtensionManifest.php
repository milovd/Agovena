<?php

declare(strict_types=1);

namespace App\Agovena\Extensions;

final readonly class ExtensionManifest
{
    /**
     * @param  list<string>  $dependencies  Other extension ids
     * @param  list<string>  $moduleDependencies  Required module ids
     * @param  list<array{key: string, label: string, type?: string, secret?: bool, required?: bool, default?: mixed, help?: string}>  $settings
     * @param  array<string, string>  $autoloadPsr4
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
        public array $moduleDependencies = [],
        public string $author = 'Agovena',
        public array $settings = [],
        public array $autoloadPsr4 = [],
        public bool $productionReady = false,
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

        $moduleDeps = $data['module_dependencies'] ?? [];
        if (! is_array($moduleDeps)) {
            $moduleDeps = [];
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
                'help' => (string) ($setting['help'] ?? ''),
            ];
        }

        $psr4 = [];
        $autoload = $data['autoload']['psr-4'] ?? [];
        if (is_array($autoload)) {
            foreach ($autoload as $prefix => $relative) {
                if (is_string($prefix) && is_string($relative)) {
                    $psr4[$prefix] = $relative;
                }
            }
        }

        $productionReady = $data['production_ready'] ?? false;
        if (! is_bool($productionReady)) {
            throw new \InvalidArgumentException('Extension manifest production_ready must be boolean.');
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
            moduleDependencies: array_values(array_map('strval', $moduleDeps)),
            author: (string) ($data['author'] ?? 'Agovena'),
            settings: $normalizedSettings,
            autoloadPsr4: $psr4,
            productionReady: $productionReady,
        );
    }
}
