<?php

declare(strict_types=1);

namespace App\Agovena\Modules;

final readonly class ModuleManifest
{
    /**
     * @param  list<string>  $dependencies  Other module ids
     * @param  array<string, string>  $autoloadPsr4
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $description,
        public string $provider,
        public string $path,
        public string $agovena = '*',
        public array $dependencies = [],
        public string $author = 'Agovena',
        public array $autoloadPsr4 = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $path): self
    {
        $deps = $data['dependencies'] ?? [];
        if (! is_array($deps)) {
            $deps = [];
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

        return new self(
            id: (string) $data['id'],
            name: (string) $data['name'],
            version: (string) ($data['version'] ?? '0.0.0'),
            description: (string) ($data['description'] ?? ''),
            provider: (string) $data['provider'],
            path: $path,
            agovena: (string) ($data['agovena'] ?? '*'),
            dependencies: array_values(array_map('strval', $deps)),
            author: (string) ($data['author'] ?? 'Agovena'),
            autoloadPsr4: $psr4,
        );
    }
}
