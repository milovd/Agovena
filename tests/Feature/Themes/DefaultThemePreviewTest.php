<?php

declare(strict_types=1);

use App\Agovena\Packages\PackageArtwork;
use App\Agovena\Theme\ThemeManager;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('the shipped default theme has a safe decodable preview', function (): void {
    $theme = app(ThemeManager::class)->find('default');

    expect($theme)->not->toBeNull();

    $path = app(PackageArtwork::class)->resolve($theme->basePath, $theme->previewReference, themePreview: true);

    expect($path)->not->toBeNull();

    $image = getimagesize($path);

    expect($image[2])->toBe(IMAGETYPE_PNG)
        ->and($image[0])->toBeGreaterThanOrEqual(640)
        ->and($image[1])->toBeGreaterThanOrEqual(360);
});

test('the default theme preview is visible only to authorized staff', function (): void {
    $url = route('admin.appearance.themes.preview', ['id' => 'default']);

    $this->get($url)->assertRedirect();
    $this->actingAs($this->createStaff(permissions: ['admin.access']))->get($url)->assertForbidden();
    $this->actingAs($this->createStaff(permissions: ['admin.access', 'theme.view']))
        ->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->get(route('admin.appearance.themes'))
        ->assertOk()
        ->assertSee('src="'.$url.'"', false);
});
