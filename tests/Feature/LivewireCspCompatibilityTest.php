<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

it('uses Livewire’s CSP-safe Alpine bundle', function (): void {
    expect(Config::get('livewire.csp_safe'))->toBeTrue();

    $page = $this->get('/');
    preg_match('/<script src="([^"]+\/livewire\.js[^"]*)"/', $page->getContent(), $matches);

    expect($matches[1] ?? null)->not->toBeNull();

    $response = $this->get($matches[1]);

    expect(app('livewire')->isCspSafe())->toBeTrue();
    $response->assertOk();
});

it('keeps project Alpine state outside inline expressions', function (): void {
    $violations = [];

    foreach ([base_path('themes'), resource_path('views')] as $directory) {
        foreach (File::allFiles($directory) as $file) {
            if ($file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = $file->getContents();
            if (preg_match('/x-data\s*=\s*"\s*\{/', $contents) === 1) {
                $violations[] = $file->getRelativePathname().': inline x-data object';
            }
            if (preg_match('/x-data\s*=\s*"[^\"]*\(/', $contents) === 1) {
                $violations[] = $file->getRelativePathname().': x-data function call';
            }
            if (str_contains($contents, 'x-init=')) {
                $violations[] = $file->getRelativePathname().': inline x-init';
            }
        }
    }

    expect($violations)->toBe([]);
});
