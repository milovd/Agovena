<?php

declare(strict_types=1);

use App\Agovena\Theme\ThemeManager;
use App\Agovena\Theme\ThemeSurface;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('default theme provides storefront and admin surfaces', function () {
    $theme = app(ThemeManager::class)->active();

    expect($theme->id)->toBe('default')
        ->and($theme->provides(ThemeSurface::Storefront))->toBeTrue()
        ->and($theme->provides(ThemeSurface::Admin))->toBeTrue()
        ->and(app(ThemeManager::class)->themeFor(ThemeSurface::Admin)->id)->toBe('default');
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
        ->assertSee(__('admin.product_name'), false);
});
