<?php

declare(strict_types=1);

/*
 * Admin views keep layout in the ITCSS layers. Inline style attributes may only pass
 * validated dynamic values as CSS custom properties (for example an invoice accent).
 */
test('admin views do not hard-code layout in inline style attributes', function (): void {
    $offenders = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views/livewire/admin'), FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }
        foreach (file($file->getPathname()) ?: [] as $number => $line) {
            if (preg_match_all('/(?<![:\w-])style="([^"]*)"/', $line, $matches) === 0) {
                continue;
            }
            foreach ($matches[1] as $declarations) {
                foreach (array_filter(array_map('trim', explode(';', $declarations))) as $declaration) {
                    if (! str_starts_with($declaration, '--')) {
                        $offenders[] = str_replace(resource_path('views/'), '', $file->getPathname()).':'.($number + 1).' '.$declaration;
                    }
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});

test('the spacing utilities map to Admin space tokens', function (): void {
    $css = file_get_contents(resource_path('css/admin/utilities/_spacing.css'));

    expect(file_get_contents(resource_path('css/admin.css')))->toContain("@import './admin/utilities/_spacing.css';")
        ->and($css)->toContain('.ag-u-mt-3')->toContain('.ag-u-mt-4')->toContain('.ag-u-mb-4')->toContain('.ag-u-mb-6')
        ->toContain('.ag-u-cluster')->toContain('.ag-u-stack-3')
        ->not->toMatch('/:\s*-?\d*\.?\d+(rem|px)/');
});
