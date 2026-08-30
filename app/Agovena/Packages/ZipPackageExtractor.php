<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

use App\Enums\PackageKind;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use ZipArchive;

final class ZipPackageExtractor
{
    public function extract(string $zipPath, PackageKind $expectedKind): ResolvedPackageOrigin
    {
        if (is_link($zipPath) || ! is_file($zipPath)) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.zip_not_found'),
            ]);
        }

        $zip = new ZipArchive;
        $opened = $zip->open($zipPath);
        if ($opened !== true) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.zip_invalid'),
            ]);
        }

        $target = null;

        try {
            $this->assertArchiveSafe($zip);
            $target = $this->createExtractionDirectory();
            if (! $zip->extractTo($target)) {
                throw ValidationException::withMessages([
                    'package' => __('admin.packages.zip_extract_failed'),
                ]);
            }
            $this->assertExtractionTree($target);
        } catch (\Throwable $exception) {
            if ($target !== null && ! $this->deleteExtractionTree($target)) {
                throw new \RuntimeException('Package extraction cleanup did not complete.', 0, $exception);
            }

            throw $exception;
        } finally {
            $zip->close();
        }

        $manifest = $expectedKind === PackageKind::Module ? 'module.json' : 'extension.json';
        $packageRoot = $this->findPackageRoot($target, $manifest);
        if ($packageRoot === null) {
            if (! $this->deleteExtractionTree($target)) {
                throw new \RuntimeException('Package extraction cleanup did not complete.');
            }
            throw ValidationException::withMessages([
                'package' => __('admin.packages.zip_manifest_missing', [
                    'manifest' => $manifest,
                ]),
            ]);
        }

        return new ResolvedPackageOrigin($packageRoot, $target);
    }

    private function createExtractionDirectory(): string
    {
        $base = storage_path('app/packages/uploads');
        File::ensureDirectoryExists($base);
        if (is_link($base)) {
            throw new \RuntimeException('Package upload root may not use symbolic links.');
        }

        $resolvedBase = realpath($base);
        if ($resolvedBase === false) {
            throw new \RuntimeException('Package upload root is unavailable.');
        }
        $resolvedBase = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolvedBase), DIRECTORY_SEPARATOR);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $target = $resolvedBase.DIRECTORY_SEPARATOR.'zip_'.bin2hex(random_bytes(16));
            if (@mkdir($target, 0700)) {
                return $target;
            }
        }

        throw new \RuntimeException('Unable to create the temporary package extraction directory.');
    }

    private function assertArchiveSafe(ZipArchive $zip): void
    {
        $maxEntries = max(1, (int) config('agovena.packages.zip_max_entries', 1000));
        $maxCompressedBytes = max(1, (int) config('agovena.packages.zip_max_compressed_bytes', 50 * 1024 * 1024));
        $maxUncompressedBytes = max(1, (int) config('agovena.packages.zip_max_uncompressed_bytes', 100 * 1024 * 1024));

        if ($zip->numFiles > $maxEntries) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.zip_too_large'),
            ]);
        }

        $compressedBytes = 0;
        $uncompressedBytes = 0;
        $names = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            $stat = $zip->statIndex($index);
            if (! is_string($name) || ! is_array($stat)) {
                throw ValidationException::withMessages([
                    'package' => __('admin.packages.zip_invalid'),
                ]);
            }

            $normalized = $this->normalizeEntryName($name);
            $key = strtolower($normalized);
            if (isset($names[$key])) {
                throw ValidationException::withMessages([
                    'package' => __('admin.packages.zip_unsafe_entry'),
                ]);
            }
            $names[$key] = true;

            $compressedSize = $this->archiveStatSize($stat, 'comp_size');
            $uncompressedSize = $this->archiveStatSize($stat, 'size');
            if ($compressedSize > $maxCompressedBytes - $compressedBytes
                || $uncompressedSize > $maxUncompressedBytes - $uncompressedBytes
            ) {
                throw ValidationException::withMessages([
                    'package' => __('admin.packages.zip_too_large'),
                ]);
            }
            $compressedBytes += $compressedSize;
            $uncompressedBytes += $uncompressedSize;

            $opsys = 0;
            $attributes = 0;
            if ($zip->getExternalAttributesIndex($index, $opsys, $attributes)
                && $opsys === ZipArchive::OPSYS_UNIX
                && (($attributes >> 16) & 0xF000) === 0xA000
            ) {
                throw ValidationException::withMessages([
                    'package' => __('admin.packages.zip_unsafe_entry'),
                ]);
            }
        }
    }

    private function normalizeEntryName(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        if ($name === ''
            || strlen($name) > 4096
            || str_contains($name, "\0")
            || str_starts_with($name, '/')
            || str_starts_with($name, '//')
            || preg_match('#^[A-Za-z]:/#', $name) === 1
            || str_contains($name, '//')
        ) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.zip_unsafe_entry'),
            ]);
        }

        $parts = explode('/', rtrim($name, '/'));
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                throw ValidationException::withMessages([
                    'package' => __('admin.packages.zip_unsafe_entry'),
                ]);
            }
        }

        return implode('/', $parts);
    }

    private function assertExtractionTree(string $root): void
    {
        if (is_link($root)) {
            throw new \RuntimeException('Extracted package root may not use symbolic links.');
        }
        $resolvedRoot = realpath($root);
        if ($resolvedRoot === false) {
            throw new \RuntimeException('Extracted package root is unavailable.');
        }
        $resolvedRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolvedRoot), DIRECTORY_SEPARATOR);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isLink()) {
                throw new \RuntimeException('Extracted package trees may not contain symbolic links.');
            }
            $resolved = realpath($entry->getPathname());
            if ($resolved === false) {
                throw new \RuntimeException('Extracted package entry is unavailable.');
            }
            $resolved = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolved);
            if (! str_starts_with($resolved, $resolvedRoot.DIRECTORY_SEPARATOR)) {
                throw new \RuntimeException('Extracted package entry escaped the temporary root.');
            }
        }
    }

    private function findPackageRoot(string $extractedRoot, string $manifest): ?string
    {
        if (is_link($extractedRoot)) {
            return null;
        }

        if (is_file($extractedRoot.DIRECTORY_SEPARATOR.$manifest)
            && ! is_link($extractedRoot.DIRECTORY_SEPARATOR.$manifest)
        ) {
            return $extractedRoot;
        }

        $children = array_values(array_filter(
            File::directories($extractedRoot),
            static fn (string $child): bool => ! is_link($child),
        ));
        if (count($children) === 1
            && is_file($children[0].DIRECTORY_SEPARATOR.$manifest)
            && ! is_link($children[0].DIRECTORY_SEPARATOR.$manifest)
        ) {
            return $children[0];
        }

        foreach ($children as $child) {
            if (is_file($child.DIRECTORY_SEPARATOR.$manifest)
                && ! is_link($child.DIRECTORY_SEPARATOR.$manifest)
            ) {
                return $child;
            }
        }

        return null;
    }

    private function deleteExtractionTree(string $path): bool
    {
        if (is_link($path)) {
            return @unlink($path) || ! is_link($path);
        }
        if (! file_exists($path)) {
            return true;
        }
        if (is_dir($path)) {
            try {
                foreach (new \DirectoryIterator($path) as $entry) {
                    if ($entry->isDot()) {
                        continue;
                    }
                    if (! $this->deleteExtractionTree($entry->getPathname())) {
                        return false;
                    }
                }
            } catch (\Throwable) {
                return false;
            }

            $removed = @rmdir($path);
            clearstatcache(true, $path);

            return $removed || ! file_exists($path);
        }

        clearstatcache(true, $path);

        return @unlink($path) || ! file_exists($path);
    }

    /** @param array<array-key, mixed> $stat */
    private function archiveStatSize(array $stat, string $key): int
    {
        return max(0, (int) ($stat[$key] ?? 0));
    }
}
