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
        $json = $this->readComposerFile($composerFile);
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
        $json = $this->readComposerFile($file);
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
        $command = array_merge([$this->phpBinary(), $binary, '--no-interaction', '--no-scripts', '--no-plugins', '--working-dir', $workingDir], $arguments);

        $process = new Process($command, $workingDir, timeout: (float) config('agovena.packages.composer_timeout', 180));
        $process->run();

        if (! $process->isSuccessful()) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.composer_failed', [
                    'error' => $this->scrubDiagnostics($process->getErrorOutput().' '.$process->getOutput()),
                ]),
            ]);
        }
    }

    private function installedPath(string $workingDir, string $packageName): string
    {
        $vendorRoot = $workingDir.DIRECTORY_SEPARATOR.'vendor';
        $path = $vendorRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $packageName);
        if (! is_dir($path)) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.composer_missing_path', ['name' => $packageName]),
            ]);
        }

        $vendorResolved = realpath($vendorRoot);
        $pathResolved = realpath($path);
        if ($vendorResolved === false || $pathResolved === false || is_link($vendorRoot)) {
            throw new \RuntimeException('Composer installed package path is not safely contained.');
        }

        $vendorResolved = $this->normalizePath($vendorResolved);
        $pathResolved = $this->normalizePath($pathResolved);
        if (! str_starts_with($pathResolved, $vendorResolved.DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Composer installed package path is outside the vendor root.');
        }

        for ($ancestor = $path; ; $ancestor = dirname($ancestor)) {
            if (is_link($ancestor)) {
                throw new \RuntimeException('Composer installed package path may not contain symbolic links.');
            }
            if ($this->normalizePath($ancestor) === $this->normalizePath($vendorRoot)) {
                break;
            }
            if ($ancestor === dirname($ancestor)) {
                throw new \RuntimeException('Composer installed package path has an invalid ancestor chain.');
            }
        }

        return $pathResolved;
    }

    private function scrubDiagnostics(string $diagnostics): string
    {
        $diagnostics = trim($diagnostics);
        $decoded = json_decode($diagnostics, true);
        if (is_array($decoded)) {
            $redacted = $this->redactDiagnosticValue($decoded);
            $diagnostics = json_encode($redacted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[REDACTED]';
        }

        $diagnostics = $this->redactDiagnosticString($diagnostics);

        return mb_substr($diagnostics, 0, 4000);
    }

    private function isSensitiveDiagnosticKey(string $key): bool
    {
        return preg_match('/(?:api[_-]?key|access[_-]?key|authorization|bearer|client[_-]?secret|credential|jwt|password|passwd|private[_-]?key|secret|signing[_-]?key|token)/i', $key) === 1;
    }

    private function redactDiagnosticValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $key => $nested) {
                $redacted[$key] = $this->isSensitiveDiagnosticKey((string) $key)
                    ? '[REDACTED]'
                    : $this->redactDiagnosticValue($nested);
            }

            return $redacted;
        }

        return is_string($value) ? $this->redactDiagnosticString($value) : $value;
    }

    private function redactDiagnosticString(string $value): string
    {
        $value = preg_replace(
            '/-----BEGIN [^-]*PRIVATE KEY-----.*?-----END [^-]*PRIVATE KEY-----/is',
            '[REDACTED]',
            $value,
        ) ?? $value;
        $value = preg_replace('/(Bearer\s+)[^\s,;]+/i', '$1[REDACTED]', $value) ?? $value;
        $value = preg_replace(
            '#([a-z][a-z0-9+.-]*://)[^/@\s]+(?::[^/@\s]+)?@#i',
            '$1[REDACTED]@',
            $value,
        ) ?? $value;
        $keyPattern = '(?:api[_-]?key|access[_-]?key|authorization|bearer|client[_-]?secret|credential|jwt|password|passwd|private[_-]?key|secret|signing[_-]?key|token)';
        $value = preg_replace_callback(
            '/([?&]'.$keyPattern.'\s*=\s*)[^&#\s]+/i',
            static fn (array $match): string => $match[1].'[REDACTED]',
            $value,
        ) ?? $value;
        $value = preg_replace(
            '/((?:["\']?'.$keyPattern.'["\']?)\s*(?::|=|=>)\s*)("(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|[^,}\]\s;]+)/i',
            '$1"[REDACTED]"',
            $value,
        ) ?? $value;
        $value = preg_replace(
            '/(^|[^A-Za-z0-9_])('.$keyPattern.')([[:space:]]+)[^[:space:],;]+/i',
            '$1$2$3[REDACTED]',
            $value,
        ) ?? $value;

        return $value;
    }

    /** @return array<string, mixed> */
    private function readComposerFile(string $file): array
    {
        try {
            $decoded = json_decode((string) File::get($file), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Managed composer.json is invalid.', 0, $exception);
        }

        if (! is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new \RuntimeException('Managed composer.json must contain an object.');
        }
        if (array_key_exists('require', $decoded) && ! is_array($decoded['require'])) {
            throw new \RuntimeException('Managed composer.json has an invalid require section.');
        }
        if (array_key_exists('repositories', $decoded) && ! is_array($decoded['repositories'])) {
            throw new \RuntimeException('Managed composer.json has an invalid repositories section.');
        }

        return $decoded;
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
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
