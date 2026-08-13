<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

use App\Enums\PackageKind;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

final class PackageManifestReader
{
    /**
     * @return array{
     *     kind: PackageKind,
     *     id: string,
     *     name: string,
     *     version: string,
     *     description: string,
     *     provider: string,
     *     agovena: string,
     *     autoload: array<string, string>
     * }
     */
    public function read(string $directory): array
    {
        $moduleFile = $directory.DIRECTORY_SEPARATOR.'module.json';
        $extensionFile = $directory.DIRECTORY_SEPARATOR.'extension.json';

        if (is_file($moduleFile)) {
            return $this->fromManifestFile($moduleFile, PackageKind::Module, $directory);
        }

        if (is_file($extensionFile)) {
            return $this->fromManifestFile($extensionFile, PackageKind::Extension, $directory);
        }

        return $this->fromComposerExtra($directory);
    }

    /**
     * @return array{
     *     kind: PackageKind,
     *     id: string,
     *     name: string,
     *     version: string,
     *     description: string,
     *     provider: string,
     *     agovena: string,
     *     autoload: array<string, string>
     * }
     */
    private function fromManifestFile(string $file, PackageKind $kind, string $directory): array
    {
        /** @var array<string, mixed>|null $data */
        $data = json_decode((string) File::get($file), true);
        if (! is_array($data) || ! isset($data['id'], $data['name'], $data['provider'])) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.invalid_manifest'),
            ]);
        }

        $id = (string) $data['id'];
        $provider = (string) $data['provider'];

        return [
            'kind' => $kind,
            'id' => $id,
            'name' => (string) $data['name'],
            'version' => (string) ($data['version'] ?? '0.0.0'),
            'description' => (string) ($data['description'] ?? ''),
            'provider' => $provider,
            'agovena' => (string) ($data['agovena'] ?? '*'),
            'autoload' => $this->psr4($data['autoload'] ?? null, $provider, $directory),
        ];
    }

    /**
     * @return array{
     *     kind: PackageKind,
     *     id: string,
     *     name: string,
     *     version: string,
     *     description: string,
     *     provider: string,
     *     agovena: string,
     *     autoload: array<string, string>
     * }
     */
    private function fromComposerExtra(string $directory): array
    {
        $composerFile = $directory.DIRECTORY_SEPARATOR.'composer.json';
        if (! is_file($composerFile)) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.manifest_missing'),
            ]);
        }

        /** @var array<string, mixed>|null $composer */
        $composer = json_decode((string) File::get($composerFile), true);
        if (! is_array($composer)) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.invalid_manifest'),
            ]);
        }

        $extra = $composer['extra']['agovena'] ?? null;
        if (! is_array($extra) || ! isset($extra['kind'], $extra['id'], $extra['provider'])) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.manifest_missing'),
            ]);
        }

        $kind = PackageKind::tryFrom((string) $extra['kind']);
        if ($kind === null) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.invalid_kind'),
            ]);
        }

        $provider = (string) $extra['provider'];

        return [
            'kind' => $kind,
            'id' => (string) $extra['id'],
            'name' => (string) ($extra['name'] ?? $composer['name'] ?? $extra['id']),
            'version' => (string) ($composer['version'] ?? '0.0.0'),
            'description' => (string) ($composer['description'] ?? ''),
            'provider' => $provider,
            'agovena' => (string) ($extra['agovena'] ?? '*'),
            'autoload' => $this->psr4($composer['autoload'] ?? null, $provider, $directory),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function psr4(mixed $autoload, string $provider, string $directory): array
    {
        $psr4 = [];
        if (is_array($autoload) && isset($autoload['psr-4']) && is_array($autoload['psr-4'])) {
            foreach ($autoload['psr-4'] as $prefix => $path) {
                if (is_string($prefix) && is_string($path)) {
                    $psr4[$prefix] = $path;
                }
            }
        }

        if ($psr4 === []) {
            $ns = substr($provider, 0, (int) strrpos($provider, '\\') + 1);
            $src = is_dir($directory.DIRECTORY_SEPARATOR.'src') ? 'src/' : '';
            if ($ns !== '') {
                $psr4[$ns] = $src;
            }
        }

        return $psr4;
    }
}
