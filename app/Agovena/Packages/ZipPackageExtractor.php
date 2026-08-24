<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

use App\Enums\PackageKind;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use ZipArchive;

final class ZipPackageExtractor
{
    public function extract(string $zipPath, PackageKind $expectedKind): string
    {
        if (! is_file($zipPath)) {
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

        $target = storage_path('app/packages/uploads/'.uniqid('zip_', true));
        File::ensureDirectoryExists($target);

        try {
            if (! $zip->extractTo($target)) {
                throw ValidationException::withMessages([
                    'package' => __('admin.packages.zip_extract_failed'),
                ]);
            }
        } finally {
            $zip->close();
        }

        $manifest = $expectedKind === PackageKind::Module ? 'module.json' : 'extension.json';
        $packageRoot = $this->findPackageRoot($target, $manifest);
        if ($packageRoot === null) {
            File::deleteDirectory($target);
            throw ValidationException::withMessages([
                'package' => __('admin.packages.zip_manifest_missing', [
                    'manifest' => $manifest,
                ]),
            ]);
        }

        return $packageRoot;
    }

    private function findPackageRoot(string $extractedRoot, string $manifest): ?string
    {
        if (is_file($extractedRoot.DIRECTORY_SEPARATOR.$manifest)) {
            return $extractedRoot;
        }

        $children = File::directories($extractedRoot);
        if (count($children) === 1 && is_file($children[0].DIRECTORY_SEPARATOR.$manifest)) {
            return $children[0];
        }

        foreach ($children as $child) {
            if (is_file($child.DIRECTORY_SEPARATOR.$manifest)) {
                return $child;
            }
        }

        return null;
    }
}
