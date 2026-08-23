<?php

declare(strict_types=1);

use App\Agovena\Theme\ThemeManager;
use App\Agovena\Theme\ThemeManifest;
use App\Agovena\Theme\ThemeSurface;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('default theme provides storefront and admin surfaces', function () {
    $theme = app(ThemeManager::class)->active();

    $adminCss = file_get_contents(base_path($theme->adminCssEntry));

    expect($theme->id)->toBe('default')
        ->and($theme->provides(ThemeSurface::Storefront))->toBeTrue()
        ->and($theme->provides(ThemeSurface::Admin))->toBeTrue()
        ->and($theme->adminCssEntry)->toBe('themes/default/resources/css/admin.css')
        ->and($adminCss)->toContain('[data-theme="dark"]')
        ->and($adminCss)->toContain('--ag-color-bg: #0b1220')
        ->and(app(ThemeManager::class)->themeFor(ThemeSurface::Admin)->id)->toBe('default');
});

test('theme manifests expose a separate optional admin stylesheet', function () {
    $manifest = ThemeManifest::fromArray([
        'id' => 'custom',
        'name' => 'Custom',
        'css' => 'themes/custom/resources/css/theme.css',
        'admin_css' => 'themes/custom/resources/css/admin.css',
    ], 'fallback');

    expect($manifest->adminCssEntry)->toBe('themes/custom/resources/css/admin.css');
});

test('admin chrome layout resolves from the default theme', function () {
    $path = str_replace('\\', '/', app('view')->getFinder()->find('layouts.admin'));

    expect($path)->toContain('themes/default/views/layouts/admin.blade.php');
});

test('admin guest layout resolves from the default theme', function () {
    $path = str_replace('\\', '/', app('view')->getFinder()->find('layouts.admin-guest'));

    expect($path)->toContain('themes/default/views/layouts/admin-guest.blade.php');
});

test('admin dashboard still renders through the themed chrome', function () {
    $this->actingAs($this->createStaff())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('admin-shell', false)
        ->assertSee('data-theme="light"', false)
        ->assertSee('admin-theme-toggle', false)
        ->assertSee("localStorage.getItem('agovena.theme')", false)
        ->assertSee(__('admin.theme_to_dark'), false)
        ->assertSee(__('admin.product_name'), false);
});
