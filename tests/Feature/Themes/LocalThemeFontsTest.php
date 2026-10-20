<?php

declare(strict_types=1);

use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('default theme layouts do not contact third-party font services', function (string $layout): void {
    $source = file_get_contents(base_path('themes/default/views/layouts/'.$layout.'.blade.php'));

    expect($source)
        ->not->toContain('fonts.googleapis.com')
        ->not->toContain('fonts.gstatic.com');
})->with(['admin', 'storefront', 'checkout']);

test('both theme entrypoints load the shared local font faces', function (string $entry, string $import): void {
    expect(file_get_contents(base_path('themes/default/resources/css/'.$entry.'.css')))
        ->toContain("@import '".$import."';");
})->with([
    ['admin', './admin/_fonts.css'],
    ['theme', './settings/_fonts.css'],
]);

test('local font faces retain normal and italic Latin coverage and their license', function (): void {
    $root = base_path('themes/default/resources/fonts');
    $css = file_get_contents(base_path('themes/default/resources/css/settings/_fonts.css'));

    expect(file_get_contents(base_path('themes/default/resources/css/admin/_fonts.css')))
        ->toContain("@import '../settings/_fonts.css';");

    expect($css)->toContain("font-family: 'DM Sans'", 'font-weight: 400 700', 'font-display: swap')
        ->not->toContain('https://');

    foreach (['normal', 'italic'] as $style) {
        foreach (['latin', 'latin-ext'] as $subset) {
            $filename = 'dm-sans-'.$style.'-'.$subset.'.woff2';
            $bytes = file_get_contents($root.'/'.$filename);

            expect($bytes)->toStartWith('wOF2')
                ->and(strlen($bytes))->toBeLessThan(100000)
                ->and($css)->toContain('../../fonts/'.$filename);
        }
    }

    expect(file_get_contents($root.'/OFL.txt'))->toContain('SIL OPEN FONT LICENSE', 'DM Sans Project Authors');
});

test('the rendered admin shell has no remote font stylesheet or preconnect', function (): void {
    $html = $this->actingAs($this->createStaff())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->getContent();

    expect(str_contains($html, 'fonts.googleapis.com'))->toBeFalse()
        ->and(str_contains($html, 'fonts.gstatic.com'))->toBeFalse();
});
