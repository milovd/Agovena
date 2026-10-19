<?php

declare(strict_types=1);

/*
 * English is the fallback locale: a key missing in English renders as a raw key, a key
 * missing in Dutch silently shows English. Both languages must define the same keys.
 * validation.php follows Laravel's per-locale package files and is checked by Laravel.
 */

/** @return list<string> */
function translationKeys(array $lines, string $prefix = ''): array
{
    $keys = [];
    foreach ($lines as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
        $keys = is_array($value) && $value !== [] ? [...$keys, ...translationKeys($value, $path)] : [...$keys, $path];
    }

    return $keys;
}

/** @return list<string> */
function translationGaps(string $langDirectory, array $skipFiles = []): array
{
    $gaps = [];
    foreach (['en' => 'nl', 'nl' => 'en'] as $from => $to) {
        foreach (glob($langDirectory.'/'.$from.'/*.php') ?: [] as $file) {
            if (in_array(basename($file), $skipFiles, true)) {
                continue;
            }
            $other = $langDirectory.'/'.$to.'/'.basename($file);
            $missing = array_diff(translationKeys((array) require $file), is_file($other) ? translationKeys((array) require $other) : []);
            foreach ($missing as $key) {
                $gaps[] = basename(dirname($langDirectory)).'/'.$to.'/'.basename($file).': '.$key;
            }
        }
    }

    return $gaps;
}

test('Core English and Dutch translations define the same keys', function (): void {
    expect(translationGaps(lang_path(), ['validation.php']))->toBe([]);
});

test('optional package English and Dutch translations define the same keys', function (): void {
    if (! is_dir(optionalModuleRoot('provisioning').'/resources/views/admin')) {
        $this->markTestSkipped('This optional-packages revision predates package-owned views and their translation pass.');
    }

    $gaps = [];
    foreach ([...glob(optionalPackagesRoot().'/modules/*/lang') ?: [], ...glob(optionalPackagesRoot().'/extensions/*/*/lang') ?: []] as $directory) {
        $gaps = [...$gaps, ...translationGaps($directory)];
    }

    expect($gaps)->toBe([]);
});
