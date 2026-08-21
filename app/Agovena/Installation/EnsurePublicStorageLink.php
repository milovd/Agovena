<?php

declare(strict_types=1);

namespace App\Agovena\Installation;

use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Ensures the public disk is reachable via public/storage when safe to do so.
 */
final class EnsurePublicStorageLink
{
    public function __construct(
        private readonly ?string $linkPath = null,
        private readonly ?string $targetPath = null,
    ) {}

    public function linkPath(): string
    {
        return $this->linkPath ?? public_path('storage');
    }

    public function targetPath(): string
    {
        return $this->targetPath ?? storage_path('app/public');
    }

    public function exists(): bool
    {
        $link = $this->linkPath();
        $target = $this->targetPath();

        if (! file_exists($link) && ! is_link($link)) {
            return false;
        }

        $targetReal = realpath($target);
        if ($targetReal === false) {
            return false;
        }

        if (is_link($link)) {
            $resolved = @readlink($link);
            if (! is_string($resolved)) {
                return false;
            }

            $absolute = $this->absolutePath($resolved, dirname($link));
            $linkReal = realpath($absolute);

            return $linkReal !== false && $linkReal === $targetReal;
        }

        // Windows junctions report as directories, not is_link().
        $linkReal = realpath($link);

        return $linkReal !== false && $linkReal === $targetReal;
    }

    /**
     * Create the public storage link when missing and safe. Idempotent when already linked.
     * Replaces a stale symlink/junction that no longer points at the current public disk.
     */
    public function ensure(): bool
    {
        if ($this->exists()) {
            return true;
        }

        $link = $this->linkPath();
        $target = $this->targetPath();

        if ($this->pathOccupied($link) && ! $this->removeStaleLink($link)) {
            return false;
        }

        try {
            File::ensureDirectoryExists($target);
            File::ensureDirectoryExists(dirname($link));
            app('files')->link($target, $link);

            return $this->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function removeStaleLink(string $link): bool
    {
        if (! $this->isReplaceableLink($link)) {
            return false;
        }

        if (@unlink($link)) {
            return true;
        }

        // Windows junctions look like directories; rmdir removes the junction, not the target.
        return @rmdir($link);
    }

    private function isReplaceableLink(string $link): bool
    {
        if (is_link($link)) {
            return true;
        }

        $resolved = realpath($link);
        if ($resolved === false) {
            return $this->pathOccupied($link);
        }

        $self = $this->canonicalSelf($link);
        if ($self === null) {
            return false;
        }

        return ! $this->samePath($resolved, $self);
    }

    private function pathOccupied(string $path): bool
    {
        if (is_link($path) || file_exists($path) || is_dir($path)) {
            return true;
        }

        return @lstat($path) !== false;
    }

    private function canonicalSelf(string $path): ?string
    {
        $parent = realpath(dirname($path));
        if ($parent === false) {
            return null;
        }

        return $parent.DIRECTORY_SEPARATOR.basename($path);
    }

    private function samePath(string $left, string $right): bool
    {
        $normalize = static fn (string $path): string => strtolower(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));

        return $normalize($left) === $normalize($right);
    }

    private function absolutePath(string $path, string $baseDir): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return $baseDir.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || (strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':');
    }
}
