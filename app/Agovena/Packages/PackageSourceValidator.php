<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

use App\Enums\PackageKind;
use App\Enums\PackageSourceType;
use Composer\Semver\VersionParser;
use Illuminate\Validation\ValidationException;

final class PackageSourceValidator
{
    private const PACKAGE_NAME = '/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9]([_.-]?[a-z0-9]+)*$/';

    private const AGOVENA_ID = '/^[a-z][a-z0-9-]{0,62}$/';

    public function assert(PackageSource $source): void
    {
        if ($source->sourceType !== PackageSourceType::Bundled) {
            $this->assertConstraint($source->constraint);
        }

        match ($source->sourceType) {
            PackageSourceType::Bundled => $this->assertAgovenaId($source->locator),
            PackageSourceType::Path => $this->assertPath($source->locator),
            PackageSourceType::Composer => $this->assertComposerName($source->composerName ?? $source->locator),
            PackageSourceType::Vcs => $this->assertVcs($source),
        };
    }

    public function assertAgovenaId(string $id): void
    {
        if (preg_match(self::AGOVENA_ID, $id) !== 1) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.invalid_id', ['id' => $id]),
            ]);
        }
    }

    public function assertKind(PackageKind $expected, PackageKind $actual): void
    {
        if ($expected !== $actual) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.kind_mismatch', [
                    'expected' => $expected->value,
                    'actual' => $actual->value,
                ]),
            ]);
        }
    }

    public function assertComposerName(string $name): void
    {
        if (preg_match(self::PACKAGE_NAME, $name) !== 1) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.invalid_composer_name', ['name' => $name]),
            ]);
        }
    }

    public function assertConstraint(string $constraint): void
    {
        if ($constraint === '' || $constraint === '*') {
            return;
        }

        try {
            (new VersionParser)->parseConstraints($constraint);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.invalid_constraint', ['constraint' => $constraint]),
            ]);
        }
    }

    public function assertPath(string $path): string
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.invalid_path'),
            ]);
        }

        $resolved = realpath($path);
        if ($resolved === false || ! is_dir($resolved)) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.path_not_found'),
            ]);
        }

        if (! $this->isUnderAllowedPrefix($resolved)) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.path_not_allowed'),
            ]);
        }

        return $resolved;
    }

    public function assertVcs(PackageSource $source): void
    {
        $name = $source->composerName ?? '';
        if ($name === '') {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.composer_name_required'),
            ]);
        }
        $this->assertComposerName($name);
        $this->assertRepositoryUrl($source->locator);
    }

    public function assertRepositoryUrl(string $url): void
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 255) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.invalid_repository'),
            ]);
        }

        $parts = parse_url($url);
        if (! is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || ! isset($parts['host'], $parts['path'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.invalid_repository'),
            ]);
        }

        $host = strtolower($parts['host']);
        $allowed = array_map('strtolower', config('agovena.packages.allowed_hosts', []));
        if (! in_array($host, $allowed, true)) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.host_not_allowed', ['host' => $host]),
            ]);
        }

        $path = $parts['path'];
        if (preg_match('#^/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+(?:\.git)?$#', $path) !== 1) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.invalid_repository'),
            ]);
        }
    }

    private function isUnderAllowedPrefix(string $resolved): bool
    {
        $allowed = [
            $this->normalize(storage_path('app/packages')),
            $this->normalize(base_path('tests/fixtures/packages')),
        ];

        foreach (config('agovena.packages.extra_path_prefixes', []) as $prefix) {
            if (is_string($prefix) && $prefix !== '') {
                $real = realpath($prefix);
                if ($real !== false) {
                    $allowed[] = $this->normalize($real);
                }
            }
        }

        $candidate = $this->normalize($resolved);
        foreach ($allowed as $prefix) {
            if ($candidate === $prefix || str_starts_with($candidate, $prefix.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}
