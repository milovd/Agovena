<?php

declare(strict_types=1);

namespace App\Agovena\Packages;

use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

final class GitMonorepoCheckout implements MonorepoCheckout
{
    public function __construct(
        private readonly MonorepoPackageMap $packageMap,
    ) {}

    public function resolve(string $repositoryUrl, string $ref, string $subdirectory): string
    {
        $subdirectory = $this->packageMap->assertSubdirectory($subdirectory);
        $checkoutRoot = $this->checkoutRoot($repositoryUrl);
        $this->ensureCheckout($checkoutRoot, $repositoryUrl, $ref);

        $packagePath = $checkoutRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $subdirectory);
        $resolved = realpath($packagePath);
        if ($resolved === false || ! is_dir($resolved)) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.monorepo_subdirectory_missing', ['path' => $subdirectory]),
            ]);
        }

        $root = realpath($checkoutRoot);
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

    private function ensureCheckout(string $checkoutRoot, string $repositoryUrl, string $ref): void
    {
        File::ensureDirectoryExists(dirname($checkoutRoot));

        if (! is_dir($checkoutRoot.DIRECTORY_SEPARATOR.'.git')) {
            if (is_dir($checkoutRoot)) {
                File::deleteDirectory($checkoutRoot);
            }

            $this->run([
                'clone',
                '--no-checkout',
                $repositoryUrl,
                $checkoutRoot,
            ], dirname($checkoutRoot));
        }

        $this->run(['fetch', '--tags', 'origin'], $checkoutRoot);
        $this->run(['checkout', '--force', $ref], $checkoutRoot);
        $this->run(['reset', '--hard', 'HEAD'], $checkoutRoot);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function run(array $arguments, string $workingDir): void
    {
        $command = array_merge(['git'], $arguments);
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
