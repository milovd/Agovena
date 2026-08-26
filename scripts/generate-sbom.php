<?php

declare(strict_types=1);

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php scripts/generate-sbom.php <output-file>\n");
    exit(2);
}

$root = dirname(__DIR__);
$output = $argv[1];

$readJson = static function (string $path): array {
    if (! is_file($path)) {
        throw new RuntimeException("Required lockfile is missing: ".basename($path));
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Required lockfile cannot be read: ".basename($path));
    }

    $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($decoded)) {
        throw new RuntimeException("Required lockfile is invalid: ".basename($path));
    }

    return $decoded;
};

$component = static function (string $name, string $version, string $purl, ?string $scope = null, array $licenses = []): array {
    $item = [
        'type' => 'library',
        'name' => $name,
        'version' => $version,
        'purl' => $purl,
    ];

    if ($scope !== null) {
        $item['scope'] = $scope;
    }

    if ($licenses !== []) {
        $item['licenses'] = array_map(
            static fn (string $license): array => ['license' => ['id' => $license]],
            $licenses
        );
    }

    return $item;
};

$composer = $readJson($root.'/composer.lock');
$npm = $readJson($root.'/package-lock.json');
$components = [];

foreach (['packages' => 'required', 'packages-dev' => 'development'] as $section => $scope) {
    foreach ($composer[$section] ?? [] as $package) {
        if (! is_array($package) || ! isset($package['name'], $package['version'])) {
            continue;
        }

        $name = (string) $package['name'];
        $version = (string) $package['version'];
        $licenses = array_values(array_filter(array_map('strval', $package['license'] ?? [])));
        $components[] = $component(
            $name,
            $version,
            'pkg:composer/'.$name.'@'.rawurlencode($version),
            $scope,
            $licenses
        );
    }
}

foreach ($npm['packages'] ?? [] as $path => $package) {
    if ($path === '' || ! is_array($package) || ! isset($package['version'])) {
        continue;
    }

    $name = (string) ($package['name'] ?? basename((string) $path));
    $version = (string) $package['version'];
    $scope = ! empty($package['dev']) ? 'development' : 'required';
    $components[] = $component(
        $name,
        $version,
        'pkg:npm/'.rawurlencode($name).'@'.rawurlencode($version),
        $scope,
        []
    );
}

usort($components, static fn (array $left, array $right): int => [$left['purl'], $left['version']]
    <=> [$right['purl'], $right['version']]);

$document = [
    'bomFormat' => 'CycloneDX',
    'specVersion' => '1.5',
    'serialNumber' => 'urn:uuid:'.substr(hash('sha256', json_encode($components, JSON_THROW_ON_ERROR)), 0, 8).'-'.substr(hash('sha256', json_encode($components, JSON_THROW_ON_ERROR)), 8, 4).'-'.substr(hash('sha256', json_encode($components, JSON_THROW_ON_ERROR)), 12, 4).'-'.substr(hash('sha256', json_encode($components, JSON_THROW_ON_ERROR)), 16, 4).'-'.substr(hash('sha256', json_encode($components, JSON_THROW_ON_ERROR)), 20, 12),
    'version' => 1,
    'metadata' => [
        'component' => [
            'type' => 'application',
            'name' => 'agovena/agovena',
            'version' => '0.0.1',
        ],
    ],
    'components' => $components,
];

$directory = dirname($output);
if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
    throw new RuntimeException("Cannot create SBOM directory.");
}

$json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
if (file_put_contents($output, $json, LOCK_EX) === false) {
    throw new RuntimeException("Cannot write SBOM output.");
}

printf("Wrote %s with %d components.\n", $output, count($components));
