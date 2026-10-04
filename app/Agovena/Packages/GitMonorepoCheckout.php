<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

final class GitMonorepoCheckout implements MonorepoCheckout
{
    /** @var array<string, true> */
    private array $refreshed = [];

    public function __construct(
        private readonly MonorepoPackageMap $packageMap,
        private readonly ZipPackageExtractor $zipExtractor,
    ) {}

    public function resolve(string $repositoryUrl, string $ref, string $subdirectory, bool $refresh = true): string
    {
        $subdirectory = $this->packageMap->assertSubdirectory($subdirectory);
        $checkoutRoot = $this->checkoutRoot($repositoryUrl);
        $this->ensureCheckout($checkoutRoot, $repositoryUrl, $ref, $refresh);
        $this->verifyResolvedRef($checkoutRoot, $ref);

        $sourceRoot = $this->sourceRoot($checkoutRoot);
        $packagePath = $sourceRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $subdirectory);
        $resolved = realpath($packagePath);
        if ($resolved === false || ! is_dir($resolved)) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.monorepo_subdirectory_missing', ['path' => $subdirectory]),
            ]);
        }

        $root = realpath($sourceRoot);
        if ($root === false || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.monorepo_invalid_subdirectory'),
            ]);
        }

        return $resolved;
    }

    private function checkoutRoot(string $repositoryUrl): string
    {
        return storage_path('app/packages/monorepo-cache/'.hash('sha256', $repositoryUrl));
    }

    private function ensureCheckout(string $checkoutRoot, string $repositoryUrl, string $ref, bool $refresh): void
    {
        File::ensureDirectoryExists(dirname($checkoutRoot));

        $cacheKey = $checkoutRoot.'|'.$ref;
        if (! $refresh && is_dir($checkoutRoot.DIRECTORY_SEPARATOR.'.git')) {
            return;
        }
        if ($refresh && isset($this->refreshed[$cacheKey])) {
            return;
        }

        $gitDir = $checkoutRoot.DIRECTORY_SEPARATOR.'.git';
        if (is_dir($gitDir) && ! $this->originMatches($checkoutRoot, $repositoryUrl)) {
            File::deleteDirectory($checkoutRoot);
        }

        if (! is_dir($checkoutRoot.DIRECTORY_SEPARATOR.'.git')
            && ! is_file($checkoutRoot.DIRECTORY_SEPARATOR.'.agovena-archive-root')
        ) {
            if (is_dir($checkoutRoot)) {
                File::deleteDirectory($checkoutRoot);
            }

            try {
                $arguments = ['clone', '--depth', '1'];
                if (! $this->isImmutableCommit($ref)) {
                    $arguments[] = '--branch';
                    $arguments[] = $ref;
                }
                $arguments[] = $repositoryUrl;
                $arguments[] = $checkoutRoot;
                $this->run($arguments, dirname($checkoutRoot));

                if ($this->isImmutableCommit($ref)) {
                    $this->run(['fetch', '--depth', '1', 'origin', $ref], $checkoutRoot);
                    $this->run(['checkout', '--force', 'FETCH_HEAD'], $checkoutRoot);
                }
            } catch (\Throwable $gitException) {
                $this->installArchiveCheckout($checkoutRoot, $repositoryUrl, $ref, $gitException);
            }

            $this->refreshed[$cacheKey] = true;

            return;
        }

        if (! $refresh) {
            return;
        }

        try {
            $this->run(['fetch', '--tags', '--depth', '1', 'origin', $ref], $checkoutRoot);
            $this->run(['checkout', '--force', $ref], $checkoutRoot);
            $this->run(['reset', '--hard', 'FETCH_HEAD'], $checkoutRoot);
        } catch (\Throwable $gitException) {
            File::deleteDirectory($checkoutRoot);
            $this->installArchiveCheckout($checkoutRoot, $repositoryUrl, $ref, $gitException);
        }
        $this->refreshed[$cacheKey] = true;
    }

    private function sourceRoot(string $checkoutRoot): string
    {
        $marker = $checkoutRoot.DIRECTORY_SEPARATOR.'.agovena-archive-root';
        if (! is_file($marker)) {
            return $checkoutRoot;
        }

        $relative = trim((string) file_get_contents($marker));
        $checkout = realpath($checkoutRoot);
        $root = realpath($checkoutRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if ($relative === ''
            || str_starts_with($relative, DIRECTORY_SEPARATOR)
            || preg_match('#(^|[\\/])\.\.([\\/]|$)#', $relative) === 1
            || $checkout === false
            || $root === false
            || ! is_dir($root)
            || ! str_starts_with($root, $checkout.DIRECTORY_SEPARATOR)
        ) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.monorepo_checkout_failed', [
                    'error' => 'Downloaded monorepo archive root is invalid.',
                ]),
            ]);
        }

        return $root;
    }

    private function installArchiveCheckout(string $checkoutRoot, string $repositoryUrl, string $ref, \Throwable $gitException): void
    {
        $archiveUrl = $this->archiveUrl($repositoryUrl, $ref);
        if ($archiveUrl === null) {
            throw $gitException;
        }

        File::deleteDirectory($checkoutRoot);
        $archivePath = $checkoutRoot.'.zip';
        File::delete($archivePath);

        try {
            File::ensureDirectoryExists(dirname($checkoutRoot));
            $response = Http::timeout((float) config('agovena.packages.composer_timeout', 180))
                ->retry(2, 250)
                ->get($archiveUrl);
            if (! $response->successful()) {
                throw new \RuntimeException('Monorepo archive download returned HTTP '.$response->status().'.');
            }

            $body = $response->body();
            $maxBytes = max(1, (int) config('agovena.packages.zip_max_compressed_bytes', 50 * 1024 * 1024));
            if (strlen($body) > $maxBytes) {
                throw new \RuntimeException('Monorepo archive exceeds the configured compressed size limit.');
            }

            File::put($archivePath, $body);
            $this->zipExtractor->extractArchiveTo($archivePath, $checkoutRoot);

            $directories = array_values(array_filter(
                File::directories($checkoutRoot),
                static fn (string $path): bool => ! is_link($path),
            ));
            if (count($directories) !== 1) {
                throw new \RuntimeException('Downloaded monorepo archive has an unexpected root layout.');
            }

            $relative = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, substr($directories[0], strlen($checkoutRoot))), DIRECTORY_SEPARATOR);
            File::put($checkoutRoot.DIRECTORY_SEPARATOR.'.agovena-archive-root', $relative);
            File::put($checkoutRoot.DIRECTORY_SEPARATOR.'.agovena-archive-ref', $ref);
        } catch (\Throwable $archiveException) {
            File::deleteDirectory($checkoutRoot);
            throw ValidationException::withMessages([
                'package' => __('admin.packages.monorepo_checkout_failed', [
                    'error' => 'Git checkout failed and the HTTPS monorepo archive fallback also failed: '.$archiveException->getMessage(),
                ]),
            ]);
        } finally {
            File::delete($archivePath);
        }
    }

    private function archiveUrl(string $repositoryUrl, string $ref): ?string
    {
        $parts = parse_url($repositoryUrl);
        if (strtolower((string) ($parts['host'] ?? '')) !== 'github.com') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', trim((string) ($parts['path'] ?? ''), '/'))));
        if (count($segments) !== 2) {
            return null;
        }

        $repository = preg_replace('/\.git\z/i', '', $segments[1]);
        if (! is_string($repository) || $repository === '') {
            return null;
        }

        $encodedRef = str_replace('%2F', '/', rawurlencode($ref));

        return $this->isImmutableCommit($ref)
            ? "https://codeload.github.com/{$segments[0]}/{$repository}/zip/{$encodedRef}"
            : "https://codeload.github.com/{$segments[0]}/{$repository}/zip/refs/heads/{$encodedRef}";
    }

    private function originMatches(string $checkoutRoot, string $repositoryUrl): bool
    {
        $process = new Process(['git', '-c', 'core.longpaths=true', 'remote', 'get-url', 'origin'], $checkoutRoot, timeout: 30.0);
        $process->run();
        if (! $process->isSuccessful()) {
            return false;
        }

        return $this->normalizeRemoteUrl(trim($process->getOutput())) === $this->normalizeRemoteUrl($repositoryUrl);
    }

    private function verifyResolvedRef(string $checkoutRoot, string $requestedRef): void
    {
        if (! $this->isImmutableCommit($requestedRef)) {
            return;
        }

        $archiveRef = $checkoutRoot.DIRECTORY_SEPARATOR.'.agovena-archive-ref';
        if (is_file($archiveRef)) {
            $resolved = trim((string) file_get_contents($archiveRef));
            if (! hash_equals(strtolower(trim($requestedRef)), strtolower($resolved))) {
                throw ValidationException::withMessages([
                    'package' => __('admin.packages.monorepo_checkout_failed', [
                        'error' => 'Downloaded package archive did not match the requested immutable ref.',
                    ]),
                ]);
            }

            return;
        }

        $process = new Process(['git', '-c', 'core.longpaths=true', 'rev-parse', 'HEAD'], $checkoutRoot, timeout: 30.0);
        $process->run();
        $resolved = trim($process->getOutput());
        if (! $process->isSuccessful() || ! hash_equals(strtolower(trim($requestedRef)), strtolower($resolved))) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.monorepo_checkout_failed', [
                    'error' => 'Checked-out package commit did not match the requested immutable ref.',
                ]),
            ]);
        }
    }

    private function normalizeRemoteUrl(string $url): string
    {
        $url = strtolower(rtrim($url));
        if (str_ends_with($url, '.git')) {
            $url = substr($url, 0, -4);
        }

        return $url;
    }

    private function isImmutableCommit(string $ref): bool
    {
        return preg_match('/\A[0-9a-f]{40}\z/i', trim($ref)) === 1;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function run(array $arguments, string $workingDir): void
    {
        $command = array_merge(['git', '-c', 'core.longpaths=true'], $arguments);
        $process = new Process($command, $workingDir, timeout: (float) config('agovena.packages.composer_timeout', 180));
        $process->run();

        if (! $process->isSuccessful()) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.monorepo_checkout_failed', [
                    'error' => trim($process->getErrorOutput().' '.$process->getOutput()),
                ]),
            ]);
        }
    }
}
