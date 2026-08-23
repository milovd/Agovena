<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Agovena\Packages\MonorepoCheckout;
use App\Agovena\Packages\MonorepoPackageMap;
use Illuminate\Validation\ValidationException;

final class FakeMonorepoCheckout implements MonorepoCheckout
{
    /** @var array<string, string> repository URL => monorepo root directory */
    private array $repositories = [];

    /** @var list<array{url: string, ref: string, subdirectory: string}> */
    public array $resolved = [];

    public function __construct(
        private readonly MonorepoPackageMap $packageMap,
    ) {}

    public function map(string $repositoryUrl, string $rootPath): void
    {
        $this->repositories[$repositoryUrl] = $rootPath;
    }

    public function resolve(string $repositoryUrl, string $ref, string $subdirectory): string
    {
        $this->resolved[] = [
            'url' => $repositoryUrl,
            'ref' => $ref,
            'subdirectory' => $subdirectory,
        ];

        $root = $this->repositories[$repositoryUrl] ?? null;
        if ($root === null || ! is_dir($root)) {
            throw ValidationException::withMessages([
                'package' => 'Fake monorepo not mapped: '.$repositoryUrl,
            ]);
        }

        $subdirectory = $this->packageMap->assertSubdirectory($subdirectory);
        $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $subdirectory);
        $resolved = realpath($path);
        if ($resolved === false || ! is_dir($resolved)) {
            throw ValidationException::withMessages([
                'package' => __('admin.packages.monorepo_subdirectory_missing', ['path' => $subdirectory]),
            ]);
        }

        return $resolved;
    }
}
