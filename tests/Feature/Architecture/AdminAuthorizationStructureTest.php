<?php

declare(strict_types=1);
use App\Agovena\Packages\OptionalPackagesPath;

it('requires every concrete Admin Livewire component to expose explicit server-side authorization evidence', function (): void {
    $root = dirname(__DIR__, 3).'/app/Livewire/Admin';
    $components = [];
    $authorizationEvidence = [
        'authorize' => '/\$this\s*->\s*authorize\s*\(/',
        'gate' => '/\bGate\s*::\s*(?:authorize|allows|denies|check)\s*\(/',
        'can' => '/(?:\$this|\$[A-Za-z_]\w*)\s*->\s*can\s*\(/',
        'policy' => '/\b(?:policy|authorizeForUser)\s*\(/',
        'middleware' => '/\b(?:middleware|\brouteMiddleware\b)\s*\(/',
    ];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($files as $file) {
        /** @var SplFileInfo $file */
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = str_replace('\\', '/', $file->getPathname());
        if (str_contains($path, '/app/Livewire/Admin/Auth/')) {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        if (! is_string($source) || ! preg_match('/\bclass\s+\w+/', $source)) {
            continue;
        }

        $tokens = token_get_all($source);
        $code = '';
        foreach ($tokens as $token) {
            $code .= is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING], true)
                ? ' '
                : (is_array($token) ? $token[1] : $token);
        }

        $matches = array_keys(array_filter(
            $authorizationEvidence,
            static fn (string $pattern): bool => preg_match($pattern, $code) === 1,
        ));

        $components[$path] = $matches;
    }

    $missing = array_keys(array_filter(
        $components,
        static fn (array $matches): bool => $matches === [],
    ));

    expect($components)->not->toBeEmpty()
        ->and($missing)->toBe([]);
});

/**
 * Admin components whose render() does not authorize.
 *
 * @param  list<string>  $roots
 * @return array{checked: int, missing: list<string>}
 */
function adminComponentsWithoutRenderAuthorization(array $roots): array
{
    $checked = 0;
    $missing = [];
    foreach ($roots as $root) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if ($file->getExtension() !== 'php' || str_contains($path, '/Livewire/Admin/Auth/')) {
                continue;
            }
            $source = (string) file_get_contents($path);
            if (preg_match('/function render\([^)]*\)[^{]*\{(.*?)\n    \}/s', $source, $render) !== 1) {
                continue;
            }
            $checked++;
            if (preg_match('/\$this\s*->\s*authorize\s*\(|\bGate\s*::\s*(?:authorize|allows|denies|check)\s*\(|abort_unless\s*\(/', $render[1]) !== 1) {
                $missing[] = $path;
            }
        }
    }

    return ['checked' => $checked, 'missing' => $missing];
}

// mount() only runs on the first page load. Livewire update requests (refresh,
// property updates) only call render(), so a permission revoked while a tab is
// open is enforced only when render() authorizes too.
it('re-checks authorization on every Livewire request for core Admin components that render data', function (): void {
    $result = adminComponentsWithoutRenderAuthorization([
        dirname(__DIR__, 3).'/app/Livewire/Admin',
        ...glob(dirname(__DIR__, 3).'/app/Agovena/*/Http/Livewire/Admin') ?: [],
    ]);

    expect($result['checked'])->toBeGreaterThan(0)
        ->and($result['missing'])->toBe([]);
});

it('re-checks authorization on every Livewire request for package Admin components that render data', function (): void {
    $packages = OptionalPackagesPath::root();
    if ($packages === null) {
        $this->markTestSkipped('optional-packages is not available.');
    }

    // First releases that authorize in render(); CI also tests Core against older,
    // released packages, which operators receive the fix for by updating.
    $firstFixedRelease = ['module.json' => '1.1.0', 'extension.json' => '1.0.1'];
    $roots = [];
    $released = [];
    foreach ([...glob($packages.'/modules/*', GLOB_ONLYDIR) ?: [], ...glob($packages.'/extensions/*/*', GLOB_ONLYDIR) ?: []] as $package) {
        $admin = $package.'/src/Http/Livewire/Admin';
        if (! is_dir($admin)) {
            continue;
        }
        foreach ($firstFixedRelease as $manifestFile => $fixedIn) {
            if (is_file($package.'/'.$manifestFile)) {
                $manifest = json_decode((string) file_get_contents($package.'/'.$manifestFile), true);
                if (version_compare((string) ($manifest['version'] ?? '0.0.0'), $fixedIn, '<')) {
                    $released[] = $manifest['id'].' '.$manifest['version'];
                } else {
                    $roots[] = $admin;
                }
            }
        }
    }

    $result = adminComponentsWithoutRenderAuthorization($roots);
    expect($result['missing'])->toBe([]);

    if ($released !== []) {
        $this->markTestSkipped('Released packages predate render() authorization: '.implode(', ', $released));
    }
    expect($result['checked'])->toBeGreaterThan(0);
});
