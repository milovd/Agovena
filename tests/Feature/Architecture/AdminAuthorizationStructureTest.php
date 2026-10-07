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

it('re-checks authorization on every Livewire request for Admin components that render data', function (): void {
    // mount() only runs on the first page load. Livewire update requests (refresh,
    // property updates) only call render(), so a permission revoked while a tab is
    // open is enforced only when render() authorizes too.
    $roots = [dirname(__DIR__, 3).'/app/Livewire/Admin', ...glob(dirname(__DIR__, 3).'/app/Agovena/*/Http/Livewire/Admin') ?: []];
    $packages = OptionalPackagesPath::root();
    if ($packages !== null) {
        $roots = [...$roots, ...glob($packages.'/{modules,extensions}/*/src/Http/Livewire/Admin', GLOB_BRACE) ?: [], ...glob($packages.'/extensions/*/*/src/Http/Livewire/Admin') ?: []];
    }

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

    expect($checked)->toBeGreaterThan(0)
        ->and($missing)->toBe([]);
});
