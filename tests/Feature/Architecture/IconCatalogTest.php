<?php

declare(strict_types=1);

/*
 * <x-ag.icon> falls back to the settings glyph for an unknown name, so a typo or a
 * missing glyph renders a gear instead of failing. This test keeps every static icon
 * name used by core, the default Theme and first-party packages in the curated set.
 */

function agIconCatalog(): array
{
    $source = (string) file_get_contents(resource_path('views/components/ag/icon.blade.php'));
    preg_match_all("/^\\s*'([a-z0-9-]+)'\\s*=>/m", $source, $matches);

    return array_values(array_unique($matches[1]));
}

/**
 * @return array<string, list<string>> icon name => files using it
 */
function agIconUsages(): array
{
    $roots = [
        app_path(),
        resource_path('views'),
        base_path('themes/default/views'),
    ];

    $packages = config('agovena.packages.optional_packages_path') ?: base_path('../optional-packages');
    foreach (['modules', 'extensions'] as $kind) {
        if (is_dir($packages.DIRECTORY_SEPARATOR.$kind)) {
            $roots[] = $packages.DIRECTORY_SEPARATOR.$kind;
        }
    }

    $patterns = [
        '/<x-ag\.icon\b[^>]*?\sname="([a-z0-9-]+)"/',
        "/\\bicon:\\s*'([a-z0-9-]+)'/",
        "/'icon'\\s*=>\\s*'([a-z0-9-]+)'/",
        '/placeholder-icon="([a-z0-9-]+)"/',
    ];

    $usages = [];
    foreach ($roots as $root) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $contents, $matches);
                foreach ($matches[1] as $name) {
                    $usages[$name][] = $file->getPathname();
                }
            }
        }
    }

    return $usages;
}

test('every static icon name used by core, the default theme and first-party packages exists', function () {
    $catalog = agIconCatalog();
    $usages = agIconUsages();

    expect($usages)->not->toBeEmpty();

    $missing = [];
    foreach ($usages as $name => $files) {
        if (! in_array($name, $catalog, true)) {
            $missing[$name] = array_values(array_unique(array_map(
                fn (string $path): string => str_replace(base_path().DIRECTORY_SEPARATOR, '', $path),
                $files,
            )));
        }
    }

    expect($missing)->toBe([]);
});
