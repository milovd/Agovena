<?php

declare(strict_types=1);

/*
 * The product editor and settings hub hide tabs off-screen at phone widths when the tab bar
 * only scrolls sideways, without any hint that more tabs exist. On narrow screens the tabs
 * wrap so every section stays visible and reachable.
 */
test('admin tab bars wrap on narrow screens so no tab is hidden off-screen', function (): void {
    $css = (string) file_get_contents(base_path('themes/default/resources/css/admin/_tabs.css'));

    expect(preg_match('/@media \(max-width: 640px\) \{\s*\.ag-product-tabs \{(?<body>[^}]*)\}/', $css, $match))->toBe(1)
        ->and($match['body'])->toContain('flex-wrap: wrap;')
        ->and($match['body'])->toContain('overflow-x: visible;');
});
