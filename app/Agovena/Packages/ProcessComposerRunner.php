<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

final class ProcessComposerRunner implements ComposerRunner
{
    public function require(string $packageName, string $constraint, ?string $repositoryUrl = null): ComposerInstallResult
    {
        $workingDir = $this->ensureWorkingDirectory();
        $this->writeComposerFile($workingDir, $packageName, $constraint, $repositoryUrl);
        $this->run($workingDir, ['update', $packageName, '--with-dependencies', '--no-dev']);

        $path = $this->installedPath($workingDir, $packageName);
        $version = $this->installedVersion($workingDir, $packageName) ?? $constraint;

        return new ComposerInstallResult($packageName, $version, $path);
    }

    public function remove(string $packageName): void
    {
        $workingDir = $this->workingDirectory();
        if (! is_dir($workingDir)) {
            return;
        }

        $composerFile = $workingDir.DIRECTORY_SEPARATOR.'composer.json';
        if (! is_file($composerFile)) {
            return;
        }

        /** @var array<string, mixed> $json */
        $json = json_decode((string) File::get($composerFile), true) ?: [];
        unset($json['require'][$packageName]);
        File::put($composerFile, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        if (is_file($workingDir.DIRECTORY_SEPARATOR.'composer.lock')) {
            $this->run($workingDir, ['update', '--lock', '--no-dev']);
        }
    }

    public function latestVersion(string $packageName): ?string
    {
        return null;
    }

    private function ensureWorkingDirectory(): string
    {
        $dir = $this->workingDirectory();
        File::ensureDirectoryExists($dir);

        $composerFile = $dir.DIRECTORY_SEPARATOR.'composer.json';
        if (! is_file($composerFile)) {
            File::put($composerFile, json_encode($this->emptyComposerFile(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        }

        return $dir;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyComposerFile(): array
    {
        return [
            'name' => 'agovena/installed-packages',
            'description' => 'Merchant-installed Agovena packages. Managed by Agovena.',
            'minimum-stability' => 'stable',
            'prefer-stable' => true,
            'config' => [
                'secure-http' => true,
                'audit' => ['abandoned' => 'report'],
            ],
            'require' => new \stdClass,
        ];
    }

    private function writeComposerFile(string $workingDir, string $packageName, string $constraint, ?string $repositoryUrl): void
    {
        $file = $workingDir.DIRECTORY_SEPARATOR.'composer.json';
        /** @var array<string, mixed> $json */
        $json = json_decode((string) File::get($file), true) ?: $this->emptyComposerFile();
        if (! isset($json['require']) || ! is_array($json['require'])) {
            $json['require'] = [];
        }
        $json['require'][$packageName] = $constraint === '' ? '*' : $constraint;

        if ($repositoryUrl !== null && $repositoryUrl !== '') {
            $repositories = isset($json['repositories']) && is_array($json['repositories']) ? $json['repositories'] : [];
            $already = false;
            foreach ($repositories as $repository) {
                if (is_array($repository) && ($repository['url'] ?? null) === $repositoryUrl) {
                    $already = true;
                    break;
                }
            }
            if (! $already) {
                $repositories[] = [
                    'type' => 'vcs',
                    'url' => $repositoryUrl,
                ];
            }
            $json['repositories'] = $repositories;
        }

        File::put($file, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    /**
     * @param  list<string>  $arguments
     */
    private function run(string $workingDir, array $arguments): void
    {
        $binary = $this->composerBinary();
        $command = array_merge([$this->phpBinary(), $binary, '--no-interaction', '--no-scripts', '--working-dir', $workingDir], $arguments);

        $process = new Process($command, $workingDir, timeout: (float) config('agovena.packages.composer_timeout', 180));
        $process->run();

        if (! $process->isSuccessful()) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.composer_failed', [
                    'error' => trim($process->getErrorOutput().' '.$process->getOutput()),
                ]),
            ]);
        }
    }

    private function installedPath(string $workingDir, string $packageName): string
    {
        $path = $workingDir.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $packageName);
        if (! is_dir($path)) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.composer_missing_path', ['name' => $packageName]),
            ]);
        }

        return $path;
    }

    private function installedVersion(string $workingDir, string $packageName): ?string
    {
        $installed = $workingDir.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR.'installed.json';
        if (! is_file($installed)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = json_decode((string) File::get($installed), true) ?: [];
        $packages = $data['packages'] ?? $data;
        if (! is_array($packages)) {
            return null;
        }

        foreach ($packages as $package) {
            if (is_array($package) && ($package['name'] ?? null) === $packageName) {
                return isset($package['version']) ? ltrim((string) $package['version'], 'v') : null;
            }
        }

        return null;
    }

    private function workingDirectory(): string
    {
        return storage_path('app/packages/composer');
    }

    private function phpBinary(): string
    {
        return PHP_BINARY;
    }

    private function composerBinary(): string
    {
        $configured = config('agovena.packages.composer_binary');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        $phar = base_path('composer.phar');
        if (is_file($phar)) {
            return $phar;
        }

        $vendorBin = base_path('vendor/bin/composer');
        if (is_file($vendorBin)) {
            return $vendorBin;
        }

        throw ValidationException::withMessages([
            'package' => __('admin.packages.composer_missing'),
        ]);
    }
}
