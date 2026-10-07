<?php

declare(strict_types=1);

use App\Agovena\Packages\OptionalPackagesPath;

/**
 * The frontend is split into per-domain files behind a few entry points
 * (Vite inputs, CSS indexes, Blade includes). These tests prove every piece
 * is still wired to the entry that ships it.
 */

/**
 * @return list<string>
 */
function frontendFiles(string $directory, string $suffix): array
{
    if (! is_dir($directory)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (str_ends_with($file->getFilename(), $suffix)) {
            $files[] = str_replace('\\', '/', $file->getPathname());
        }
    }
    sort($files);

    return $files;
}

/**
 * Blade views that use Alpine components: core, the default Theme and, when present, the optional packages.
 *
 * @return list<string>
 */
function frontendBladeFiles(): array
{
    $files = [
        ...frontendFiles(resource_path('views'), '.blade.php'),
        ...frontendFiles(base_path('themes'), '.blade.php'),
    ];

    $packages = OptionalPackagesPath::root();
    if ($packages !== null) {
        $files = [...$files, ...frontendFiles($packages, '.blade.php')];
    }

    return $files;
}

/**
 * Every module reachable from a JS entry through relative imports.
 *
 * @return list<string>
 */
function jsModuleGraph(string $entry): array
{
    $seen = [];
    $queue = [realpath($entry)];
    while ($queue !== []) {
        $path = array_shift($queue);
        if ($path === false || isset($seen[$path])) {
            continue;
        }
        $seen[$path] = true;
        foreach (jsRelativeImports($path) as $specifier) {
            $target = realpath(dirname($path).DIRECTORY_SEPARATOR.$specifier);
            expect($target)->not->toBeFalse("{$path} imports missing {$specifier}");
            $queue[] = $target;
        }
    }

    return array_keys($seen);
}

/**
 * @return list<string>
 */
function alpineComponentsRegisteredBy(string $entry): array
{
    $names = [];
    foreach (jsModuleGraph($entry) as $module) {
        preg_match_all("/Alpine\\.data\\('([A-Za-z]+)'/", (string) file_get_contents($module), $matches);
        $names = [...$names, ...$matches[1]];
    }

    return $names;
}

test('every storefront stylesheet partial is imported exactly once by the storefront index', function () {
    $components = base_path('themes/default/resources/css/components');
    $imports = cssImports($components.'/_store.css');

    foreach ($imports as $import) {
        expect(is_file($components.'/'.$import))->toBeTrue("_store.css imports missing {$import}");
    }

    $partials = array_map(
        static fn (string $file): string => './store/'.basename($file),
        frontendFiles($components.'/store', '.css'),
    );

    expect($imports)->toEqualCanonicalizing($partials)
        ->and(array_unique($imports))->toHaveCount(count($imports))
        ->and(cssImports(base_path('themes/default/resources/css/theme.css')))->toContain('./components/_store.css');
});

test('every Theme Admin skin partial is imported exactly once by the Theme admin stylesheet', function () {
    $css = base_path('themes/default/resources/css');
    $imports = cssImports($css.'/admin.css');

    $partials = array_map(
        static fn (string $file): string => './admin/'.basename($file),
        frontendFiles($css.'/admin', '.css'),
    );

    expect($imports)->toEqualCanonicalizing($partials)
        ->and(array_unique($imports))->toHaveCount(count($imports));
});

test('every core Admin stylesheet partial is imported exactly once by the Admin entry', function () {
    $css = resource_path('css');
    $imports = cssImports($css.'/admin.css');

    $partials = array_map(
        static fn (string $file): string => './'.substr($file, strlen(str_replace('\\', '/', $css)) + 1),
        frontendFiles($css.'/admin', '.css'),
    );

    expect($imports)->toEqualCanonicalizing($partials)
        ->and(array_unique($imports))->toHaveCount(count($imports));
});

test('every storefront and Admin script module is reachable from its Vite entry', function () {
    $storefront = jsModuleGraph(resource_path('js/storefront.js'));
    $admin = jsModuleGraph(resource_path('js/admin.js'));

    foreach (frontendFiles(resource_path('js/storefront'), '.js') as $module) {
        expect($storefront)->toContain(realpath($module));
    }
    foreach (frontendFiles(resource_path('js/admin'), '.js') as $module) {
        expect($admin)->toContain(realpath($module));
    }
    foreach (frontendFiles(resource_path('js/shared'), '.js') as $module) {
        expect([...$storefront, ...$admin])->toContain(realpath($module));
    }
});

test('every register function a script module exports is called by its entry', function () {
    foreach (['storefront', 'admin'] as $surface) {
        $entry = (string) file_get_contents(resource_path("js/{$surface}.js"));
        foreach (frontendFiles(resource_path("js/{$surface}"), '.js') as $module) {
            preg_match_all('/^export function ((?:register|init)[A-Za-z]+)\(/m', (string) file_get_contents($module), $matches);
            foreach ($matches[1] as $function) {
                expect($entry)->toMatch('/^\s*'.$function.'\(/m');
            }
        }
    }
});

test('every Alpine component used in Blade is registered by the entry that loads it', function () {
    $storefront = alpineComponentsRegisteredBy(resource_path('js/storefront.js'));
    $admin = alpineComponentsRegisteredBy(resource_path('js/admin.js'));

    expect(array_unique($storefront))->toHaveCount(count($storefront))
        ->and(array_unique($admin))->toHaveCount(count($admin));

    $used = [];
    foreach (frontendBladeFiles() as $file) {
        preg_match_all('/x-data="([A-Za-z]+)[("]/', (string) file_get_contents($file), $matches);
        foreach ($matches[1] as $name) {
            $used[$name][] = $file;
        }
    }

    expect($used)->not->toBeEmpty();
    foreach ($used as $name => $files) {
        $registered = str_starts_with($name, 'storefront') ? $storefront : $admin;
        expect(in_array($name, $registered, true))->toBeTrue("{$name} used in {$files[0]} is not registered by its entry");
    }
});

test('every Blade include in core and the default Theme resolves to a view', function () {
    $themeViews = base_path('themes/default/views');
    $missing = [];

    foreach ([...frontendFiles(resource_path('views'), '.blade.php'), ...frontendFiles($themeViews, '.blade.php')] as $file) {
        preg_match_all("/@include(?:If|When|Unless|First)?\\(\\s*'([a-z0-9._:-]+)'/", (string) file_get_contents($file), $matches);
        foreach ($matches[1] as $view) {
            $resolves = str_starts_with($view, 'theme::')
                ? is_file($themeViews.'/'.str_replace('.', '/', substr($view, 7)).'.blade.php')
                : view()->exists($view);
            if (! $resolves) {
                $missing[] = "{$view} (in {$file})";
            }
        }
    }

    expect($missing)->toBe([]);
});

test('the default Theme provides every Theme entry view that core and optional packages render by name', function () {
    $roots = [base_path('app'), base_path('resources/views')];
    $packages = OptionalPackagesPath::root();
    if ($packages !== null) {
        $roots[] = $packages;
    }

    $entries = [];
    foreach ($roots as $root) {
        foreach (frontendFiles($root, '.php') as $file) {
            $source = (string) file_get_contents($file);
            preg_match_all("/->view\\('([a-z0-9._-]+)'\\)|'theme::([a-z0-9._-]+)'/", $source, $matches);
            foreach (array_filter([...$matches[1], ...$matches[2]]) as $view) {
                $entries[$view] = $file;
            }
            // Admin layouts resolve through the Admin Theme's prepended view location.
            preg_match_all("/(?:->layout\\(|Layout\\()'(layouts\\.admin(?:-guest)?)'/", $source, $layouts);
            foreach ($layouts[1] as $view) {
                $entries[$view] = $file;
            }
        }
    }

    expect($entries)->toHaveKeys(['layouts.storefront', 'layouts.checkout', 'layouts.admin', 'catalog.show', 'checkout.index', 'checkout.partials.address-suggestions']);

    $missing = [];
    foreach ($entries as $view => $file) {
        if (! is_file(base_path('themes/default/views/'.str_replace('.', '/', $view).'.blade.php'))) {
            $missing[] = "{$view} (rendered by {$file})";
        }
    }

    expect($missing)->toBe([]);
});
