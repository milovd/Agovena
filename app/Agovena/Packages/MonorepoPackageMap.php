<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

use App\Enums\PackageKind;
use Illuminate\Validation\ValidationException;

final class MonorepoPackageMap
{
    /**
     * @return array{kind: PackageKind, path: string}
     */
    public function resolve(string $packageKey, ?PackageKind $expectedKind = null): array
    {
        $this->assertPackageKey($packageKey);

        $packages = config('agovena.packages.monorepo.packages', []);
        if (! is_array($packages)) {
            $packages = [];
        }

        $entry = $packages[$packageKey] ?? null;
        if (! is_array($entry) || ! isset($entry['path'])) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.monorepo_unknown_package', ['key' => $packageKey]),
            ]);
        }

        $kind = PackageKind::tryFrom((string) ($entry['kind'] ?? ''));
        if ($kind === null) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.monorepo_invalid_mapping', ['key' => $packageKey]),
            ]);
        }

        if ($expectedKind !== null && $expectedKind !== $kind) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.kind_mismatch', [
                    'expected' => $expectedKind->value,
                    'actual' => $kind->value,
                ]),
            ]);
        }

        return [
            'kind' => $kind,
            'path' => $this->assertSubdirectory((string) $entry['path']),
        ];
    }

    public function defaultRepository(): string
    {
        $repository = config('agovena.packages.monorepo.repository');
        if (! is_string($repository) || trim($repository) === '') {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.monorepo_repository_missing'),
            ]);
        }

        return trim($repository);
    }

    public function assertPackageKey(string $packageKey): void
    {
        if (preg_match('/^[a-z][a-z0-9-]{0,62}$/', $packageKey) !== 1) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.invalid_id', ['id' => $packageKey]),
            ]);
        }
    }

    public function assertSubdirectory(string $subdirectory): string
    {
        $subdirectory = trim(str_replace('\\', '/', $subdirectory), '/');
        if ($subdirectory === ''
            || str_contains($subdirectory, '..')
            || preg_match('#^[a-z0-9][a-z0-9/_-]*$#i', $subdirectory) !== 1
        ) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.monorepo_invalid_subdirectory'),
            ]);
        }

        return $subdirectory;
    }
}
