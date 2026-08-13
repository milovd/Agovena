<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Agovena\Packages\ComposerInstallResult;
use App\Agovena\Packages\ComposerRunner;
use Illuminate\Validation\ValidationException;

final class FakeComposerRunner implements ComposerRunner
{
    /** @var array<string, string> package name => fixture directory */
    private array $packages = [];

    /** @var list<array{name: string, constraint: string, url: string|null}> */
    public array $required = [];

    /** @var list<string> */
    public array $removed = [];

    public function map(string $packageName, string $path): void
    {
        $this->packages[$packageName] = $path;
    }

    public function require(string $packageName, string $constraint, ?string $repositoryUrl = null): ComposerInstallResult
    {
        $this->required[] = [
            'name' => $packageName,
            'constraint' => $constraint,
            'url' => $repositoryUrl,
        ];

        $path = $this->packages[$packageName] ?? null;
        if ($path === null || ! is_dir($path)) {
            throw ValidationException::withMessages([
                'package' => 'Fake Composer package not mapped: '.$packageName,
            ]);
        }

        return new ComposerInstallResult($packageName, '1.0.0', $path);
    }

    public function remove(string $packageName): void
    {
        $this->removed[] = $packageName;
    }

    public function latestVersion(string $packageName): ?string
    {
        return isset($this->packages[$packageName]) ? '1.0.0' : null;
    }
}
