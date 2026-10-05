<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

test('release SBOM preserves npm dependency licenses from the lockfile', function (): void {
    $output = tempnam(sys_get_temp_dir(), 'agovena-sbom-');
    expect($output)->toBeString();

    try {
        $process = new Process([PHP_BINARY, base_path('scripts/generate-sbom.php'), $output], base_path());
        $process->mustRun();

        $bom = json_decode((string) file_get_contents($output), true, 512, JSON_THROW_ON_ERROR);
        $lock = json_decode((string) file_get_contents(base_path('package-lock.json')), true, 512, JSON_THROW_ON_ERROR);
        $components = collect($bom['components'])->keyBy('purl');

        foreach ($lock['packages'] as $path => $package) {
            if ($path === '' || ! isset($package['version'])) {
                continue;
            }

            $name = (string) ($package['name'] ?? basename($path));
            $purl = 'pkg:npm/'.rawurlencode($name).'@'.rawurlencode((string) $package['version']);
            expect($components->has($purl))->toBeTrue();
            expect($components->get($purl)['licenses'])->toBe([
                ['license' => ['id' => $package['license']]],
            ]);
        }
    } finally {
        unlink($output);
    }
});
